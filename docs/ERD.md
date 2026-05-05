# Entity Relationship Diagram

This document reflects the database schema defined by Laravel migrations under `backend/database/migrations`. Relationships show **declared foreign keys**; logical-only links are called out in the notes.

## Domain core (account lifecycle)

```mermaid
erDiagram
    users ||--o| roles : "role_id FK"
    roles }o--o{ permissions : "permission_role"
    users ||--o{ audit_events : "actor_user_id FK (SET NULL)"
    users ||--o{ bulk_action_operations : "actor_user_id FK (SET NULL)"

    users {
        bigint id PK
        string name
        string email UK
        timestamp email_verified_at
        string password
        string remember_token
        bigint role_id FK "nullable in migration"
        timestamps created_at updated_at
    }

    roles {
        bigint id PK
        string slug UK
        string name
        boolean is_system
        timestamps created_at updated_at
    }

    permissions {
        bigint id PK
        string slug UK
        string name
        string description
        timestamps created_at updated_at
    }

    permission_role {
        bigint permission_id PK_FK
        bigint role_id PK_FK
    }

    students {
        bigint id PK
        string external_account_id UK
        string primary_email
        string full_name
        string department
        string school_year
        boolean suspended
        timestamp deletion_scheduled_at
        boolean priority_flag
        text compliance_notes
        json raw_json
        timestamp last_imported_at
        date graduation_date
        string graduation_status
        string degree_program
        timestamps created_at updated_at
    }

    policies {
        bigint id PK
        string name
        string action
        json rule_json
        timestamp execution_at
        string cron_expression
        boolean is_active
        timestamp last_evaluated_at
        string last_status
        text hold_reason
        timestamps created_at updated_at
    }

    audit_events {
        bigint id PK
        bigint actor_user_id FK "nullable"
        string module
        string action
        string target_account_id "indexed, logical student ref"
        json payload
        uuid correlation_id
        string ip_address
        boolean success
        text error_message
        timestamps created_at updated_at
    }

    bulk_action_operations {
        bigint id PK
        uuid operation_id UK
        string action
        string status
        unsigned_int total
        unsigned_int processed
        unsigned_int ok
        unsigned_int failed
        bigint actor_user_id FK "nullable"
        timestamp requested_at
        timestamp started_at
        timestamp completed_at
        text error
        timestamps created_at updated_at
    }
```

### Notes

- **`students`** are not linked with an FK to **`users`**; they represent external directory accounts (for example `external_account_id`).
- **`audit_events.target_account_id`** is an indexed string, not a FK; it may align with **`students.external_account_id`** in application logic only.
- **`policies`** have no FKs to students or users in the schema.

## Laravel / auth / queue infrastructure

```mermaid
erDiagram
    users ||--o{ personal_access_tokens : "tokenable morph"

    users {
        bigint id PK
    }

    password_reset_tokens {
        string email PK
        string token
        timestamp created_at
    }

    sessions {
        string id PK
        bigint user_id "indexed, no FK in migration"
        string ip_address
        text user_agent
        longtext payload
        int last_activity
    }

    personal_access_tokens {
        bigint id PK
        string tokenable_type
        bigint tokenable_id
        text name
        string token UK
        text abilities
        timestamp last_used_at
        timestamp expires_at
        timestamps created_at updated_at
    }

    jobs {
        bigint id PK
        string queue
        longtext payload
        smallint attempts
        unsigned_int reserved_at
        unsigned_int available_at
        unsigned_int created_at
    }

    job_batches {
        string id PK
        string name
        int total_jobs
        int pending_jobs
        int failed_jobs
        longtext failed_job_ids
        mediumtext options
        int cancelled_at
        int created_at
        int finished_at
    }

    failed_jobs {
        bigint id PK
        string uuid UK
        text connection
        text queue
        longtext payload
        longtext exception
        timestamp failed_at
    }

    cache {
        string key PK
        mediumtext value
        bigint expiration
    }

    cache_locks {
        string key PK
        string owner
        bigint expiration
    }
```

## Migration sources

| Area | Migration files |
|------|------------------|
| Users, sessions, password resets | `0001_01_01_000000_create_users_table.php` |
| Roles & permissions | `2026_05_05_200000_create_roles_and_permissions_tables.php` |
| Students | `2026_05_04_120200_create_students_table.php`, `2026_05_05_100000_rename_student_and_audit_columns.php` |
| Policies | `2026_05_04_120300_create_policies_table.php` |
| Audit | `2026_05_04_120400_create_audit_events_table.php`, `2026_05_05_100000_rename_student_and_audit_columns.php` |
| Bulk actions | `2026_05_04_215400_create_bulk_action_operations_table.php` |
| API tokens | `2026_05_04_120000_create_personal_access_tokens_table.php` |
| Jobs | `0001_01_01_000002_create_jobs_table.php` |
| Cache | `0001_01_01_000001_create_cache_table.php` |

Render the Mermaid diagrams in GitHub, GitLab, VS Code (Mermaid preview), or any compatible viewer.
