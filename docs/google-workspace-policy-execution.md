# Google Workspace Policy Execution

This system evaluates lifecycle policies in Laravel jobs, then calls the Google Workspace Admin SDK Directory API through `StudentAccountLifecycleService`.

## Flow

1. Create or edit policies in the Policy Execution page.
2. Run `php artisan schedule:run` on a scheduler, or click `Run All Policies Now`.
3. `EvaluatePoliciesJob` evaluates active policies.
4. Matching students are sent to `StudentAccountLifecycleService`.
5. The lifecycle service calls Google Workspace when the relevant `GOOGLE_WORKSPACE_*` flag is enabled and dry-run is off.

Suspension uses `users.patch` with the `suspended` field. Deletion uses `users.delete`.

## Google Cloud Setup

1. In Google Cloud Console, create or choose a project.
2. Enable the Admin SDK API for that project.
3. Create a service account.
4. Enable domain-wide delegation on that service account.
5. Create and download a JSON key file.
6. Store the JSON key outside the public web root. A practical local path is:

```text
backend/storage/app/google/service-account.json
```

Do not commit the JSON key.

## Google Admin Console Setup

In Google Admin Console, authorize the service account client ID for domain-wide delegation.

Use this OAuth scope:

```text
https://www.googleapis.com/auth/admin.directory.user
```

That scope is required for both user suspension updates and user deletion.

## Laravel Environment

Start in dry-run mode:

```dotenv
GOOGLE_WORKSPACE_CREDENTIALS_PATH=storage/app/google/service-account.json
GOOGLE_WORKSPACE_IMPERSONATE_EMAIL=super-admin@your-domain.edu
GOOGLE_WORKSPACE_SCOPES=https://www.googleapis.com/auth/admin.directory.user

GOOGLE_WORKSPACE_SUSPEND_ENABLED=true
GOOGLE_WORKSPACE_SUSPEND_DRY_RUN=true
GOOGLE_WORKSPACE_SUSPEND_USER_KEY=primary_email

GOOGLE_WORKSPACE_DELETE_ENABLED=false
GOOGLE_WORKSPACE_DELETE_USER_KEY=primary_email
STUDENT_DELETE_DRY_RUN=true
```

After changing env values:

```powershell
php artisan optimize:clear
```

## Queue And Scheduler

Policy execution is queued. In development, run:

```powershell
php artisan queue:work --tries=3
```

For scheduled automation, run Laravel's scheduler every minute from Windows Task Scheduler or production cron:

```powershell
php artisan schedule:run
```

## Safe Rollout

1. Keep `GOOGLE_WORKSPACE_SUSPEND_DRY_RUN=true`.
2. Create a test student in your database whose `primary_email` is a real test Google Workspace user.
3. Create a narrow policy matching only that test student.
4. Queue a policy run.
5. Check logs and audit events.
6. Set `GOOGLE_WORKSPACE_SUSPEND_DRY_RUN=false`.
7. Queue the same narrow policy again and confirm the user is suspended in Google Admin.
8. Only enable deletion after suspension works and after you have a retention/data-transfer process.

Deletion requires both:

```dotenv
GOOGLE_WORKSPACE_DELETE_ENABLED=true
STUDENT_DELETE_DRY_RUN=false
```

Keep deletion disabled until you are ready for irreversible account removal.
