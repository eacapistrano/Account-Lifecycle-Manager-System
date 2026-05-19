<?php

namespace App\Models;

use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'external_account_id',
    'primary_email',
    'full_name',
    'department',
    'school_year',
    'graduation_date',
    'graduation_status',
    'degree_program',
    'suspended',
    'deletion_scheduled_at',
    'priority_flag',
    'compliance_notes',
    'raw_json',
    'last_imported_at',
    'graduation_warning_sent_at',
])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'suspended' => 'boolean',
            'priority_flag' => 'boolean',
            'deletion_scheduled_at' => 'datetime',
            'graduation_date' => 'date',
            'last_imported_at' => 'datetime',
            'graduation_warning_sent_at' => 'datetime',
            'raw_json' => 'array',
        ];
    }

    /**
     * @param  Builder<Student>  $query
     */
    public function scopeSearchAll(Builder $query, string $search): void
    {
        $needle = strtolower(trim($search));
        if ($needle === '') {
            return;
        }

        $term = '%'.addcslashes($needle, '%_\\').'%';

        $query->where(function (Builder $inner) use ($term, $needle): void {
            $inner
                ->where('primary_email', 'like', $term)
                ->orWhere('full_name', 'like', $term)
                ->orWhere('department', 'like', $term)
                ->orWhere('school_year', 'like', $term)
                ->orWhere('graduation_status', 'like', $term)
                ->orWhere('external_account_id', 'like', $term)
                ->orWhereRaw('CAST(graduation_date AS CHAR) LIKE ?', [$term])
                ->orWhereRaw('CAST(deletion_scheduled_at AS CHAR) LIKE ?', [$term])
                ->orWhereRaw('CAST(last_imported_at AS CHAR) LIKE ?', [$term]);

            if (str_contains($needle, 'suspend')) {
                $inner->orWhere('suspended', true);
            }

            if ($needle === 'active') {
                $inner->orWhere('suspended', false);
            }
        });
    }
}
