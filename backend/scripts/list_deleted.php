<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AuditEvent;

$rows = AuditEvent::where('module', 'student_deletion')
    ->where('action', 'student.delete')
    ->orderByDesc('id')
    ->take(500)
    ->get(['id', 'target_account_id', 'payload', 'created_at']);

echo $rows->toJson(JSON_PRETTY_PRINT);
