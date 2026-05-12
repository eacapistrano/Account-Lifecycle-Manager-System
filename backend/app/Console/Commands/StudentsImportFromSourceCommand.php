<?php

namespace App\Console\Commands;

use App\Services\StudentEmailMapLoader;
use App\Services\StudentImportService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class StudentsImportFromSourceCommand extends Command
{
    protected $signature = 'students:import-from-source
                            {--email-csv= : Path to CSV with student id and primary email (headers from STUDENT_IMPORT_EMAIL_CSV_* config)}';

    protected $description = 'Import students from the configured external table (e.g. CARES lgrrs), merging emails from CSV when the source has no email column.';

    public function handle(StudentImportService $importer, StudentEmailMapLoader $emailLoader): int
    {
        if (! config('student_import.enabled')) {
            $this->error('Student import is disabled. Set STUDENT_IMPORT_ENABLED=true and configure SOURCE_DB_* and student_import.');

            return self::FAILURE;
        }

        $emailMap = null;
        $emailPath = (string) ($this->option('email-csv') ?: config('student_import.email_csv_path', ''));
        if ($emailPath !== '') {
            $emailMap = $emailLoader->loadFromPath($emailPath);
            $this->info('Loaded '.count($emailMap).' emails from '.$emailPath);
        }

        try {
            $stats = $importer->import($emailMap);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Processed: '.$stats['processed']);
        $this->info('Skipped (no or invalid email): '.($stats['skipped_no_email'] ?? 0));
        $this->info('Duration ms: '.$stats['duration_ms']);

        return self::SUCCESS;
    }
}
