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
     * @return array{processed: int, duration_ms: int, error: string|null}
     */
    public function import(): array
    {
        $started = microtime(true);
        $connectionName = (string) config('student_import.connection');
        $table = (string) config('student_import.table');
        $chunkSize = (int) config('student_import.chunk_size', 500);
        $map = $this->resolvedColumnMap();

        if ($map === [] || ! isset($map['external_account_id'])) {
            throw new InvalidArgumentException('student_import.column_map must include external_account_id.');
        }

        $sourceColumns = array_values($map);
        $orderColumn = $sourceColumns[0];

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

        $query->orderBy($orderColumn)->chunk($chunkSize, function ($rows) use ($map, &$processed): void {
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

                $record['last_imported_at'] = now();
                $record['raw_json'] = $arr;
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
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'error' => null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function resolvedColumnMap(): array
    {
        /** @var array<string, mixed> $map */
        $map = config('student_import.column_map', []);

        $out = [];
        foreach ($map as $appField => $sourceColumn) {
            if (! is_string($appField) || ! is_string($sourceColumn) || $sourceColumn === '') {
                continue;
            }
            $out[$appField] = $sourceColumn;
        }

        return $out;
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
