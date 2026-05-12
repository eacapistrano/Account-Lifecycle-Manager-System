<?php

namespace App\Services;

use App\Models\Student;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class StudentImportService
{
    /**
     * @param  array<string, string>|null  $emailsByExternalAccountId  Optional map of external_account_id => email; overrides config CSV when non-null.
     * @return array{processed: int, skipped_no_email: int, duration_ms: int, error: null}
     */
    public function import(?array $emailsByExternalAccountId = null): array
    {
        $started = microtime(true);
        $connectionName = (string) config('student_import.connection');
        $table = (string) config('student_import.table');
        $chunkSize = (int) config('student_import.chunk_size', 500);
        $compositeNameColumns = array_values(array_filter((array) config('student_import.composite_full_name_columns', [])));
        $map = $this->resolvedColumnMap($compositeNameColumns);

        if ($map === [] || ! isset($map['external_account_id'])) {
            throw new InvalidArgumentException('student_import.column_map must include external_account_id.');
        }

        $emailMap = $emailsByExternalAccountId;
        if ($emailMap === null && (string) config('student_import.email_csv_path', '') !== '') {
            $emailMap = app(StudentEmailMapLoader::class)->loadFromPath((string) config('student_import.email_csv_path'));
        }

        $needsEmailMerge = ! isset($map['primary_email']);
        $generatePrimaryEmail = filter_var(config('student_import.generate_primary_email', false), FILTER_VALIDATE_BOOL);
        if ($needsEmailMerge && ($emailMap === null || $emailMap === []) && ! $generatePrimaryEmail) {
            throw new InvalidArgumentException(
                'Student import requires primary_email: map STUDENT_IMPORT_COL_EMAIL from the source table, set STUDENT_IMPORT_EMAIL_CSV_PATH, pass an email map to import(), or set STUDENT_IMPORT_GENERATE_PRIMARY_EMAIL=true (CEU email formula).'
            );
        }

        if (! $needsEmailMerge && $emailMap === []) {
            $emailMap = null;
        }

        $orderColumn = $this->resolveOrderColumn($map);
        $query = DB::connection($connectionName)->table($table);

        foreach (config('student_import.where', []) as $where) {
            if (! is_array($where) || count($where) < 2) {
                continue;
            }
            $column = $where[0];
            $operator = $where[1];
            $value = $where[2] ?? null;
            if (count($where) === 2) {
                $query->where($column, $operator);

                continue;
            }
            $query->where($column, $operator, $value);
        }

        $processed = 0;
        $skippedNoEmail = 0;

        $query->orderBy($orderColumn)->chunk($chunkSize, function ($rows) use (
            $map,
            $compositeNameColumns,
            $emailMap,
            $generatePrimaryEmail,
            $needsEmailMerge,
            &$processed,
            &$skippedNoEmail,
        ): void {
            $batch = [];

            foreach ($rows as $row) {
                $arr = (array) $row;
                $record = array_fill_keys(array_keys($map), null);

                foreach ($map as $appField => $sourceColumn) {
                    $raw = $arr[$sourceColumn] ?? null;

                    if ($appField === 'graduation_date' && $raw !== null && $raw !== '') {
                        $record[$appField] = $this->normalizeDate($raw);

                        continue;
                    }

                    if ($appField === 'suspended' && $raw !== null && $raw !== '') {
                        $record[$appField] = $this->normalizeBool($raw);

                        continue;
                    }

                    if ($raw === null) {
                        continue;
                    }

                    if (is_string($raw)) {
                        $raw = trim($raw);
                    }

                    if ($raw === '') {
                        continue;
                    }

                    $record[$appField] = $raw;
                }

                if ($compositeNameColumns !== []) {
                    $record['full_name'] = $this->buildCompositeFullName($arr, $compositeNameColumns);
                }

                $externalId = isset($record['external_account_id']) ? trim((string) $record['external_account_id']) : '';
                if ($externalId === '') {
                    $skippedNoEmail++;

                    continue;
                }
                $record['external_account_id'] = $externalId;

                $hasEmail = isset($record['primary_email']) && $record['primary_email'] !== '';
                if (! $hasEmail && $emailMap !== null) {
                    $merged = $emailMap[$externalId] ?? null;
                    if ($merged !== null && $merged !== '') {
                        $record['primary_email'] = $merged;
                        $hasEmail = true;
                    }
                }

                if (! $hasEmail && $generatePrimaryEmail && $needsEmailMerge) {
                    $lastNameColumn = (string) config('student_import.email_formula_last_name_column', 'SZLNAME');
                    $lastNameRaw = $arr[$lastNameColumn] ?? null;
                    $lastName = is_string($lastNameRaw) ? trim($lastNameRaw) : trim((string) ($lastNameRaw ?? ''));
                    if ($lastName !== '') {
                        try {
                            $record['primary_email'] = app(CeuEmailListFormulaGenerator::class)->generate($externalId, $lastName);
                            $hasEmail = true;
                        } catch (InvalidArgumentException) {
                            // treat as missing email
                        }
                    }
                }

                if (! $hasEmail || ! is_string($record['primary_email'])) {
                    $skippedNoEmail++;

                    continue;
                }

                if (filter_var($record['primary_email'], FILTER_VALIDATE_EMAIL) === false) {
                    $skippedNoEmail++;

                    continue;
                }

                $record['last_imported_at'] = now();
                $record['raw_json'] = json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $batch[] = $record;
                $processed++;
            }

            if ($batch === []) {
                return;
            }

            $updateColumns = array_values(array_diff(array_keys($batch[0]), ['external_account_id']));
            try {
                Student::upsert($batch, ['external_account_id'], $updateColumns);
            } catch (Throwable $e) {
                throw new InvalidArgumentException('Student upsert failed: '.$e->getMessage(), 0, $e);
            }
        });

        return [
            'processed' => $processed,
            'skipped_no_email' => $skippedNoEmail,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'error' => null,
        ];
    }

    /**
     * @param  array<int, string>  $compositeNameColumns
     * @return array<string, string>
     */
    private function resolvedColumnMap(array $compositeNameColumns): array
    {
        /** @var array<string, mixed> $map */
        $map = config('student_import.column_map', []);

        if ($compositeNameColumns !== []) {
            unset($map['full_name']);
        }

        $out = [];
        foreach ($map as $appField => $sourceColumn) {
            if (! is_string($appField) || ! is_string($sourceColumn) || $sourceColumn === '') {
                continue;
            }
            $out[$appField] = $sourceColumn;
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $map
     */
    private function resolveOrderColumn(array $map): string
    {
        $configured = trim((string) config('student_import.order_by_column', ''));
        if ($configured !== '') {
            return $configured;
        }

        return $map['external_account_id'];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $columns
     */
    private function buildCompositeFullName(array $row, array $columns): ?string
    {
        $parts = [];
        foreach ($columns as $column) {
            $raw = $row[$column] ?? null;
            if ($raw === null) {
                continue;
            }
            $s = trim((string) $raw);
            if ($s !== '') {
                $parts[] = $s;
            }
        }

        if ($parts === []) {
            return null;
        }

        return implode(' ', $parts);
    }

    private function normalizeDate(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->toDateString();
        }

        return CarbonImmutable::parse((string) $value)->toDateString();
    }

    private function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return ((int) $value) === 1;
        }

        $lower = strtolower(trim((string) $value));

        return in_array($lower, ['1', 'true', 'yes', 'y'], true);
    }
}
