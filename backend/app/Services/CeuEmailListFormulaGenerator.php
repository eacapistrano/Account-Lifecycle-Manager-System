<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Replicates the "Email List Creation Formula" spreadsheet:
 *
 * - C: SUBSTITUTE(LName," ","")
 * - D: RIGHT(ID, suffix_length)
 * - F: LEFT(ID, 4)
 * - G: RIGHT(F, 2)
 * - E: CONCATENATE(C, G, D)
 * - H: LOWER(E)
 * - I: IF(F = mnl_year, "@mnl...", "@ceu...")
 * - J: H & I
 */
class CeuEmailListFormulaGenerator
{
    public function generate(string $studentId, string $lastName): string
    {
        $studentId = trim($studentId);
        $lastNameNoSpaces = $this->normalizeLastName($lastName);

        if ($studentId === '' || $lastNameNoSpaces === '') {
            throw new InvalidArgumentException('CEU email formula requires non-empty student ID and last name.');
        }

        $suffixLength = $this->suffixLengthForId($studentId);

        $idSuffix = $this->excelRight($studentId, $suffixLength);
        $yearPrefix = $this->excelLeft($studentId, 4);

        if (mb_strlen($yearPrefix) < 4) {
            throw new InvalidArgumentException('CEU email formula expects at least 4 leading characters on student ID (LEFT(ID,4)).');
        }

        $yy = $this->excelRight($yearPrefix, 2);
        $localPart = mb_strtolower($lastNameNoSpaces.$yy.$idSuffix, 'UTF-8');

        $mnlYearPrefix = (string) config('student_import.email_formula_mnl_year_prefix', '2025');
        $domain = $yearPrefix === $mnlYearPrefix
            ? (string) config('student_import.email_domain_mnl', '@mnl.ceu.edu.ph')
            : (string) config('student_import.email_domain_default', '@ceu.edu.ph');

        return $localPart.$domain;
    }

    private function suffixLengthForId(string $studentId): int
    {
        $lengths = config('student_import.email_formula_id_suffix_lengths');
        if (is_array($lengths) && $lengths !== []) {
            $idLen = mb_strlen($studentId);
            foreach ($lengths as $rule) {
                if (! is_array($rule) || count($rule) < 2) {
                    continue;
                }
                $minLen = (int) ($rule[0] ?? 0);
                $useSuffix = (int) ($rule[1] ?? 5);
                if ($minLen > 0 && $idLen >= $minLen) {
                    return max(1, min(20, $useSuffix));
                }
            }
        }

        $default = (int) config('student_import.email_formula_id_suffix_length', 5);

        return max(1, min(20, $default));
    }

    private function normalizeLastName(string $lastName): string
    {
        $lastName = trim($lastName);

        $lastName = preg_replace('/(?:,\s*)?jr\.?\s*$/iu', '', $lastName);
        $lastName = str_replace(['ñ', 'Ñ'], 'n', $lastName);
        $lastName = str_replace(' ', '', $lastName);

        return $lastName;
    }

    private function excelLeft(string $value, int $n): string
    {
        if ($n <= 0) {
            return '';
        }

        return mb_substr($value, 0, $n);
    }

    private function excelRight(string $value, int $n): string
    {
        if ($n <= 0) {
            return '';
        }
        $len = mb_strlen($value);

        if ($len <= $n) {
            return $value;
        }

        return mb_substr($value, $len - $n, $n);
    }
}
