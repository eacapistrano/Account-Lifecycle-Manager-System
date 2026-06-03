<?php

namespace Tests\Feature;

use App\Jobs\EvaluatePoliciesJob;
use App\Mail\GraduationAccountDeletionNoticeMail;
use App\Mail\GraduationAccountNoticeMail;
use App\Models\Policy;
use App\Models\Student;
use App\Models\User;
use App\Services\AutomationNotifier;
use App\Services\ScopedPolicyEvaluator;
use App\Services\StudentGraduationPolicyEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GraduationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_accepts_student_graduation_policy(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/policies', [
            'name' => 'Graduation lifecycle',
            'action' => 'suspend',
            'rule_json' => [
                'type' => 'student_graduation',
                'graduation_status' => 'graduated',
                'suspend_after_days' => 60,
                'warning_days_before_suspend' => 14,
            ],
            'is_active' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('rule_json.type', 'student_graduation');
    }

    public function test_graduation_policy_sends_warning_then_suspends_after_sixty_days(): void
    {
        Mail::fake();

        $policy = Policy::query()->create([
            'name' => 'Graduation test',
            'action' => 'suspend',
            'rule_json' => [
                'type' => 'student_graduation',
                'graduation_status' => 'graduated',
                'suspend_after_days' => 60,
                'warning_days_before_suspend' => 14,
            ],
            'is_active' => true,
            'last_status' => 'idle',
        ]);

        $warnStudent = Student::factory()->create([
            'graduation_status' => 'graduated',
            'graduation_date' => now()->subDays(50)->toDateString(),
            'suspended' => false,
            'graduation_warning_sent_at' => null,
        ]);

        $suspendStudent = Student::factory()->create([
            'graduation_status' => 'graduated',
            'graduation_date' => now()->subDays(61)->toDateString(),
            'suspended' => false,
            'graduation_warning_sent_at' => null,
        ]);

        (new EvaluatePoliciesJob)->handle(
            app(ScopedPolicyEvaluator::class),
            app(StudentGraduationPolicyEvaluator::class),
            app(AutomationNotifier::class),
        );

        Mail::assertSent(GraduationAccountNoticeMail::class);

        $warnStudent->refresh();
        $suspendStudent->refresh();

        $this->assertNotNull($warnStudent->graduation_warning_sent_at);
        $this->assertFalse($warnStudent->suspended);
        $this->assertTrue($suspendStudent->suspended);
        $this->assertSame('executed', $policy->fresh()->last_status);
    }

    public function test_graduation_policy_sends_deletion_warning_before_scheduled_deletion(): void
    {
        Mail::fake();

        $policy = Policy::query()->create([
            'name' => 'Graduation deletion warning test',
            'action' => 'suspend',
            'rule_json' => [
                'type' => 'student_graduation',
                'graduation_status' => 'graduated',
                'suspend_after_days' => 60,
                'warning_days_before_suspend' => 14,
                'permanent_delete_after_days' => 30,
                'warning_days_before_delete' => 7,
            ],
            'is_active' => true,
            'last_status' => 'idle',
        ]);

        $deleteWarnStudent = Student::factory()->create([
            'graduation_status' => 'graduated',
            'graduation_date' => now()->subDays(90)->toDateString(),
            'suspended' => true,
            'deletion_scheduled_at' => now()->addDays(5),
            'graduation_warning_sent_at' => now()->subDays(30),
            'graduation_deletion_warning_sent_at' => null,
        ]);

        (new EvaluatePoliciesJob)->handle(
            app(ScopedPolicyEvaluator::class),
            app(StudentGraduationPolicyEvaluator::class),
            app(AutomationNotifier::class),
        );

        Mail::assertSent(GraduationAccountDeletionNoticeMail::class, function (GraduationAccountDeletionNoticeMail $mail) use ($deleteWarnStudent): bool {
            return $mail->hasTo($deleteWarnStudent->primary_email);
        });

        $deleteWarnStudent->refresh();
        $this->assertNotNull($deleteWarnStudent->graduation_deletion_warning_sent_at);
    }

    public function test_next_run_includes_graduation_preview_counts(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Student::query()->delete();

        $policy = Policy::query()->create([
            'name' => 'Graduation preview',
            'action' => 'suspend',
            'rule_json' => [
                'type' => 'student_graduation',
                'graduation_status' => 'graduated',
                'suspend_after_days' => 60,
                'warning_days_before_suspend' => 14,
            ],
            'is_active' => true,
            'last_status' => 'idle',
        ]);

        $warnStudent = Student::factory()->create([
            'graduation_status' => 'graduated',
            'graduation_date' => now()->subDays(50)->toDateString(),
            'suspended' => false,
            'graduation_warning_sent_at' => null,
        ]);
        $this->assertNull($warnStudent->graduation_warning_sent_at);
        Student::factory()->create([
            'graduation_status' => 'graduated',
            'graduation_date' => now()->subDays(61)->toDateString(),
            'suspended' => false,
        ]);

        $response = $this->getJson('/api/policies/'.$policy->id.'/next-run');

        $response
            ->assertOk()
            ->assertJsonPath('policy_type', 'student_graduation')
            ->assertJsonPath('graduation_preview.eligible_warnings', 1)
            ->assertJsonPath('graduation_preview.eligible_suspensions', 1)
            ->assertJsonPath('graduation_preview.eligible_deletion_warnings', 0);
    }

    public function test_next_run_preview_uses_policy_day_offsets(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Student::query()->delete();

        $policy = Policy::query()->create([
            'name' => 'Graduation preview custom days',
            'action' => 'suspend',
            'rule_json' => [
                'type' => 'student_graduation',
                'graduation_status' => 'graduated',
                'suspend_after_days' => 30,
                'warning_days_before_suspend' => 7,
            ],
            'is_active' => true,
            'last_status' => 'idle',
        ]);

        $warnStudent = Student::factory()->create([
            'graduation_status' => 'graduated',
            'graduation_date' => now()->subDays(25)->toDateString(),
            'suspended' => false,
            'graduation_warning_sent_at' => null,
        ]);
        $this->assertNull($warnStudent->graduation_warning_sent_at);
        Student::factory()->create([
            'graduation_status' => 'graduated',
            'graduation_date' => now()->subDays(50)->toDateString(),
            'suspended' => false,
        ]);

        $response = $this->getJson('/api/policies/'.$policy->id.'/next-run');

        $response
            ->assertOk()
            ->assertJsonPath('graduation_preview.eligible_warnings', 1)
            ->assertJsonPath('graduation_preview.eligible_suspensions', 1)
            ->assertJsonPath('graduation_preview.eligible_deletion_warnings', 0);
    }
}
