<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Student;

$count = Student::count();
$sample = Student::orderBy('id', 'desc')->take(10)->get(['external_account_id','primary_email','last_imported_at'])->toArray();

echo "students count: ". $count ."\n";
echo json_encode($sample, JSON_PRETTY_PRINT)."\n";
