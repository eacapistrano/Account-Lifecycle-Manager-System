<?php

return [

    'enabled' => filter_var(env('STUDENT_IMPORT_ENABLED', false), FILTER_VALIDATE_BOOL),

    'connection' => env('STUDENT_IMPORT_DB_CONNECTION', 'source_students'),

    'table' => env('STUDENT_IMPORT_TABLE', 'external_students'),

    'chunk_size' => max(50, min(2000, (int) env('STUDENT_IMPORT_CHUNK_SIZE', 500))),

    'lock_ttl' => (int) env('STUDENT_IMPORT_LOCK_TTL', 900),

    'schedule_cron' => env('STUDENT_IMPORT_CRON', '0 2 * * *'),

    /*
    | Additional static WHERE clauses: [['column', 'op', 'value'], ...]
    | Values come from this config file only — never from request input.
    */
    'where' => [],

    /*
    | Map app Student attributes => source column names on the external table.
    */
    'column_map' => [
        'external_account_id' => env('STUDENT_IMPORT_COL_EXTERNAL_ID', 'external_account_id'),
        'primary_email' => env('STUDENT_IMPORT_COL_EMAIL', 'primary_email'),
        'full_name' => env('STUDENT_IMPORT_COL_FULL_NAME', 'full_name'),
        'department' => env('STUDENT_IMPORT_COL_DEPARTMENT', 'department'),
        'school_year' => env('STUDENT_IMPORT_COL_SCHOOL_YEAR', 'school_year'),
        'graduation_date' => env('STUDENT_IMPORT_COL_GRADUATION_DATE', 'graduation_date'),
        'graduation_status' => env('STUDENT_IMPORT_COL_GRADUATION_STATUS', 'graduation_status'),
        'degree_program' => env('STUDENT_IMPORT_COL_DEGREE', 'degree_program'),
        'suspended' => env('STUDENT_IMPORT_COL_SUSPENDED', ''),
    ],

];
