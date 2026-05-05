<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_index_filters_by_actor_action_module_and_date(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        Sanctum::actingAs($admin);

        AuditEvent::query()->create([
            'actor_user_id' => $admin->id,
            'module' => 'policy_execution',
            'action' => 'policy.auto_delete',
            'target_account_id' => 'student-1',
            'correlation_id' => '11111111-1111-1111-1111-111111111111',
            'success' => true,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        AuditEvent::query()->create([
            'actor_user_id' => $admin->id,
            'module' => 'student_deletion',
            'action' => 'student.suspend',
            'target_account_id' => 'student-2',
            'correlation_id' => '22222222-2222-2222-2222-222222222222',
            'success' => true,
        ]);

        $response = $this->getJson('/api/audit-events?module=policy_execution&action=auto_delete&actor_email=admin@example.com&from='
            .now()->subDays(2)->toDateString().'&to='.now()->toDateString());

        $response
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.module', 'policy_execution')
            ->assertJsonPath('data.data.0.action', 'policy.auto_delete');
    }

    public function test_csv_export_uses_same_filters(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        Sanctum::actingAs($admin);

        AuditEvent::query()->create([
            'actor_user_id' => $admin->id,
            'module' => 'student_deletion',
            'action' => 'student.delete',
            'target_account_id' => 'student-a',
            'correlation_id' => '33333333-3333-3333-3333-333333333333',
            'success' => true,
        ]);

        AuditEvent::query()->create([
            'actor_user_id' => $admin->id,
            'module' => 'policy_execution',
            'action' => 'policy.evaluate',
            'target_account_id' => 'student-b',
            'correlation_id' => '44444444-4444-4444-4444-444444444444',
            'success' => true,
        ]);

        $response = $this->get('/api/audit-events/export/csv?module=student_deletion&action=delete&actor_email=admin@example.com');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('student.delete', $content);
        $this->assertStringNotContainsString('policy.evaluate', $content);
    }
}
