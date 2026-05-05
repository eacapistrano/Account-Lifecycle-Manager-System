<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class SchedulerJobsTest extends TestCase
{
    public function test_scheduler_registers_policy_and_suspended_jobs(): void
    {
        $events = collect(app(Schedule::class)->events());

        $policyEvent = $events->first(fn ($event) => $event->description === 'automation:policy-evaluation');
        $suspendedEvent = $events->first(fn ($event) => $event->description === 'automation:suspended-due-dates');

        $this->assertNotNull($policyEvent);
        $this->assertNotNull($suspendedEvent);
        $this->assertSame((string) config('automation.schedule.policy_evaluation_cron'), $policyEvent->expression);
        $this->assertSame((string) config('automation.schedule.suspended_due_date_cron'), $suspendedEvent->expression);
    }
}
