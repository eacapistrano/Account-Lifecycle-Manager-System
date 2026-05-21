<?php

$defaultUserScope = 'https://www.googleapis.com/auth/admin.directory.user';

return [

    'delete_enabled' => (bool) env('GOOGLE_WORKSPACE_DELETE_ENABLED', false),

    /*
     * Absolute path, or path relative to the application base path, to the
     * service account JSON key (domain-wide delegation enabled in Admin Console).
     */
    'credentials_path' => env('GOOGLE_WORKSPACE_CREDENTIALS_PATH', ''),

    /*
     * Workspace super admin (or delegated admin) email to impersonate via
     * domain-wide delegation.
     */
    'impersonate_email' => env('GOOGLE_WORKSPACE_IMPERSONATE_EMAIL', ''),

    /*
     * Which local field is sent as Directory API userKey when deleting.
     * Options: external_account_id | primary_email
     */
    'delete_user_key' => env('GOOGLE_WORKSPACE_DELETE_USER_KEY', 'external_account_id'),

    'suspend_enabled' => (bool) env('GOOGLE_WORKSPACE_SUSPEND_ENABLED', false),
    'suspend_dry_run' => (bool) env('GOOGLE_WORKSPACE_SUSPEND_DRY_RUN', true),
    'suspend_user_key' => env('GOOGLE_WORKSPACE_SUSPEND_USER_KEY', 'external_account_id'),

    /*
     * Comma-separated OAuth scopes authorized for the service account Client ID
     * under Domain-wide delegation. Default: admin.directory.user
     */
    'scopes' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('GOOGLE_WORKSPACE_SCOPES', $defaultUserScope))
    ))) ?: [$defaultUserScope],

];
