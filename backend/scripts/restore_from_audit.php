<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AuditEvent;
use App\Models\Student;

$events = AuditEvent::where('module', 'student_deletion')
    ->where('action', 'student.delete')
    ->orderByDesc('id')
    ->get(['payload']);

$records = [];
foreach ($events as $event) {
    $p = $event->payload ?? [];
    if (!is_array($p)) continue;
    $ext = $p['external_account_id'] ?? null;
    $email = $p['primary_email'] ?? ($p['email'] ?? null);
    if (!is_string($ext) || trim($ext) === '') continue;
    if (!is_string($email) || trim($email) === '') {
        // skip if no email present — import prefers emails
        continue;
    }

    $records[$ext] = [
        'external_account_id' => $ext,
        'primary_email' => $email,
        'raw_json' => json_encode($p, JSON_UNESCAPED_UNICODE),
        'last_imported_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
        'suspended' => false,
        'priority_flag' => false,
    ];
}

$batch = array_values($records);
if (count($batch) === 0) {
    echo "No eligible audit payloads found to restore.\n";
    exit(0);
}

// Determine update columns (all except external_account_id)
$updateColumns = array_values(array_diff(array_keys($batch[0]), ['external_account_id']));

// Upsert in chunks
$chunkSize = 500;
$inserted = 0;
foreach (array_chunk($batch, $chunkSize) as $chunk) {
    Student::upsert($chunk, ['external_account_id'], $updateColumns);
    $inserted += count($chunk);
}

echo sprintf("Restored/updated %d student rows from audit payloads.\n", $inserted);
