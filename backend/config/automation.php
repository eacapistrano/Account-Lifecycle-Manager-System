<?php

return [
    'schedule' => [
        'policy_evaluation_cron' => env('POLICY_EVALUATION_CRON', '*/15 * * * *'),
        'suspended_due_date_cron' => env('SUSPENDED_DUE_DATE_CRON', '*/15 * * * *'),
    ],

    'notifications' => [
        'enabled' => (bool) env('AUTOMATION_NOTIFICATIONS_ENABLED', false),
        'recipients' => array_values(array_filter(array_map(
            static fn (string $email): string => trim($email),
            explode(',', (string) env('AUTOMATION_NOTIFICATION_RECIPIENTS', ''))
        ))),
    ],
];
