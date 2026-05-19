<?php

use App\Jobs\EvaluatePoliciesJob;
use App\Jobs\ImportStudentsJob;
use App\Jobs\ProcessSuspendedDueDatesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new EvaluatePoliciesJob)
    ->cron((string) config('automation.schedule.policy_evaluation_cron', '*/15 * * * *'))
    ->name('automation:policy-evaluation')
    ->withoutOverlapping();

Schedule::job(new ProcessSuspendedDueDatesJob)
    ->cron((string) config('automation.schedule.suspended_due_date_cron', '*/15 * * * *'))
    ->name('automation:suspended-due-dates')
    ->withoutOverlapping();

if (config('student_import.enabled')) {
    Schedule::job(new ImportStudentsJob)
        ->cron((string) config('student_import.schedule_cron', '0 2 * * *'))
        ->name('automation:student-import')
        ->withoutOverlapping();
}
