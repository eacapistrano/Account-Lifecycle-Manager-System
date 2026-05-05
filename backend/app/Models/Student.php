<?php

namespace App\Models;

use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
            'raw_json' => 'array',
        ];
    }
}
