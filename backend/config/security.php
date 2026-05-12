<?php

return [

    'delete_confirmation_phrase' => env('DELETE_CONFIRMATION_PHRASE', 'DELETE STUDENT ACCOUNTS'),

    'bulk_account_ids_max' => (int) env('BULK_ACCOUNT_IDS_MAX', 500),

    /*
     * When true, delete jobs validate targets and complete successfully but do not
     * remove Workspace users or local student rows (for API verification only).
     */
    'student_delete_dry_run' => (bool) env('STUDENT_DELETE_DRY_RUN', false),

];
