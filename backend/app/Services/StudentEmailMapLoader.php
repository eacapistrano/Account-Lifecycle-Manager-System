<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class StudentEmailMapLoader
{
    /**
     * @return array<string, string> external_account_id => primary_email
     */
    public function loadFromPath(string $absolutePath): array
    {
        if (! is_readable($absolutePath)) {
            throw new InvalidArgumentException('Email CSV is not readable: '.$absolutePath);
        }

        $idColumn = (string) config('student_import.email_csv_id_column', 'SZSTUID');
        $emailColumn = (string) config('student_import.email_csv_email_column', 'primary_email');

        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException('Could not open email CSV: '.$absolutePath);
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false || $header === [null] || $header === ['']) {
                throw new InvalidArgumentException('Email CSV is empty or missing a header row.');
            }

            $header = array_map(static fn ($h) => trim((string) $h), $header);
            $idIndex = array_search($idColumn, $header, true);
            $emailIndex = array_search($emailColumn, $header, true);

            if ($idIndex === false) {
                throw new InvalidArgumentException("Email CSV header must include id column [{$idColumn}]. Found: ".implode(', ', $header));
            }
            if ($emailIndex === false) {
                throw new InvalidArgumentException("Email CSV header must include email column [{$emailColumn}]. Found: ".implode(', ', $header));
            }

            $map = [];
            $line = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $line++;
                if ($row === [null] || $row === false) {
                    continue;
                }
                $externalId = trim((string) ($row[$idIndex] ?? ''));
                $emailRaw = trim((string) ($row[$emailIndex] ?? ''));
                if ($externalId === '' || $emailRaw === '') {
                    continue;
                }
                if (filter_var($emailRaw, FILTER_VALIDATE_EMAIL) === false) {
                    Log::warning('student_import.email_csv_skipped_invalid_email', [
                        'line' => $line,
                        'external_account_id' => $externalId,
                    ]);

                    continue;
                }
                $map[$externalId] = strtolower($emailRaw);
            }

            return $map;
        } finally {
            fclose($handle);
        }
    }
}
