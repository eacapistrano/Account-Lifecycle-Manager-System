<?php

use App\Http\Middleware\AppendAuditEvent;
use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\SecurityHeaders;
use App\Jobs\EvaluatePoliciesJob;
use App\Jobs\ImportStudentsJob;
use App\Jobs\ProcessSuspendedDueDatesJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            AssignCorrelationId::class,
        ]);
        $middleware->api(append: [
            SecurityHeaders::class,
        ]);
        $middleware->alias([
            'audit.append' => AppendAuditEvent::class,
            'permission' => EnsurePermission::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->job(EvaluatePoliciesJob::class)
            ->cron((string) config('automation.schedule.policy_evaluation_cron', '*/15 * * * *'));
        $schedule->job(ProcessSuspendedDueDatesJob::class)
            ->cron((string) config('automation.schedule.suspended_due_date_cron', '*/15 * * * *'));

        if (config('student_import.enabled')) {
            $schedule->job(ImportStudentsJob::class)
                ->cron((string) config('student_import.schedule_cron', '0 2 * * *'));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
