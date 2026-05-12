<?php

return [

    'enabled' => filter_var(env('STUDENT_IMPORT_ENABLED', false), FILTER_VALIDATE_BOOL),

    'connection' => env('STUDENT_IMPORT_DB_CONNECTION', 'source_students'),

    'table' => env('STUDENT_IMPORT_TABLE', 'external_students'),

    /*
    | Source column for ORDER BY when chunking (defaults to external id source column).
    */
    'order_by_column' => env('STUDENT_IMPORT_ORDER_BY_COLUMN', ''),

    /*
    | Optional: comma-separated CARES/SIS columns used to build full_name (e.g. SZFNAME,SZMNAME,SZLNAME).
    | When non-empty, any column_map entry for full_name is ignored for the purpose of mapping.
    */
    'composite_full_name_columns' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('STUDENT_IMPORT_COMPOSITE_FULL_NAME', '')),
    ))),

    /*
    | Optional path to CSV for primary_email when the source table has no email column.
    | Headers must include the columns named in email_csv_id_column and email_csv_email_column.
    */
    'email_csv_path' => env('STUDENT_IMPORT_EMAIL_CSV_PATH', ''),

    'email_csv_id_column' => env('STUDENT_IMPORT_EMAIL_CSV_ID_COLUMN', 'SZSTUID'),

    'email_csv_email_column' => env('STUDENT_IMPORT_EMAIL_CSV_EMAIL_COLUMN', 'primary_email'),

    /*
    | When true, and primary_email is not mapped from the source, missing emails are built using the
    | CEU "Email List Creation Formula" (last name without spaces + YY from ID + RIGHT(ID,n) + domain).
    */
    'generate_primary_email' => filter_var(env('STUDENT_IMPORT_GENERATE_PRIMARY_EMAIL', false), FILTER_VALIDATE_BOOL),

    'email_formula_last_name_column' => env('STUDENT_IMPORT_EMAIL_FORMULA_LAST_NAME_COLUMN', 'SZLNAME'),

    'email_formula_id_suffix_length' => max(1, min(20, (int) env('STUDENT_IMPORT_EMAIL_FORMULA_ID_SUFFIX_LENGTH', 5))),

    /*
    | Optional: [[min_id_length, suffix_length], ...] first matching rule wins (sparser rows in the sheet used RIGHT(ID,7)).
    | Example: [[12, 5], [14, 7]] — IDs with length >= 14 use 7-char suffix.
    */
    'email_formula_id_suffix_lengths' => [],

    'email_formula_mnl_year_prefix' => env('STUDENT_IMPORT_EMAIL_FORMULA_MNL_YEAR_PREFIX', '2025'),

    'email_domain_mnl' => env('STUDENT_IMPORT_EMAIL_DOMAIN_MNL', '@mnl.ceu.edu.ph'),

    'email_domain_default' => env('STUDENT_IMPORT_EMAIL_DOMAIN_DEFAULT', '@ceu.edu.ph'),

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
