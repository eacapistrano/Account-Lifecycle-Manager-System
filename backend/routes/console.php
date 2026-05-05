<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\EvaluatePoliciesJob;
use App\Jobs\ProcessSuspendedDueDatesJob;

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
