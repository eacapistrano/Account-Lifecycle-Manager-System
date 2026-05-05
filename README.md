# Google Bulk Deletion

Web application for **IT administrators** to run **bulk suspend and delete** workflows against **student account records** stored in this system, with **append-only audit logging**, **policy-based automation**, and optional **import** from a read-only external student registry database.

- **Backend:** [Laravel](https://laravel.com/docs) 13 JSON API (Laravel Sanctum), queued jobs, scheduler hooks for policy evaluation and suspended-account due dates.
- **Frontend:** [React](https://react.dev) 19 + [TypeScript](https://www.typescriptlang.org) + [Vite](https://vite.dev).

For objectives, use cases, and architecture detail, see [`docs/SYSTEM.md`](docs/SYSTEM.md).

## Features

- **Authentication:** Sanctum-protected API; login rate limiting.
- **Students:** Filter by department, school year, graduation fields; paginated listing.
- **Bulk actions:** Queue **suspend** or **delete** by external account identifiers; **delete** requires a configured confirmation phrase (see environment).
- **Policies:** Create and schedule rules; automated evaluation via cron-driven jobs.
- **Suspended accounts:** List and update priority; due-date processing job.
- **Audit:** Query audit events; export **CSV** or **PDF** (permission-gated).
- **Import:** Optional chunked import from a configured external read-only database connection.
- **Authorization:** Roles and granular permissions for sensitive routes.

## Repository layout

| Path | Description |
|------|-------------|
| [`backend/`](backend/) | Laravel API, migrations, jobs, config |
| [`frontend/`](frontend/) | React SPA (calls the Laravel API) |
| [`docs/SYSTEM.md`](docs/SYSTEM.md) | System design and use cases |

## Requirements

- **PHP** 8.3+ and [Composer](https://getcomposer.org)
- **Node.js** (current LTS recommended) and npm
- **Database:** SQLite (default in `.env.example`) or MySQL-compatible (see `backend/.env.example`)

## Local development

### Backend

From `backend/`:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Run the API (and use a **queue worker** so bulk suspend/delete jobs execute):

```bash
php artisan serve
```

In another terminal:

```bash
php artisan queue:work
```

Optional: run the combined dev script defined in `composer.json` (see [`backend/composer.json`](backend/composer.json) `scripts.dev`).

### Frontend

From `frontend/`:

```bash
npm install
npm run dev
```

The SPA defaults to **`http://localhost:8000/api`** for API calls unless you set `VITE_API_URL` (see [`frontend/src/lib/api.ts`](frontend/src/lib/api.ts)).

### Environment highlights

Copy and edit `backend/.env.example`. Notable keys include:

- **`DELETE_CONFIRMATION_PHRASE`** — must match for bulk delete requests.
- **`BULK_ACCOUNT_IDS_MAX`** — cap on IDs per bulk request.
- **`STUDENT_IMPORT_*` / `SOURCE_DB_*`** — optional external registry import.
- **`CORS_ALLOWED_ORIGINS`** — restrict browser origins in production.

## Tests

From `backend/`:

```bash
composer test
```

Or:

```bash
php artisan test
```

## Security

This codebase can perform **destructive, high-impact** operations. Run with **least privilege**, **strong secrets**, **locked-down CORS**, and **audited** admin access. Review `docs/SYSTEM.md` before production use.

### Public repository checklist

Enabling [GitHub secret scanning](https://docs.github.com/en/code-security/secret-scanning/about-secret-scanning) on the repository adds an extra safety net for accidental token commits.

Before every push, confirm you are **not** committing:

- **Environment files** — only `*.env.example` (and similar templates) belong in git; keep real `.env`, `.env.local`, and `.env.production` local or in your host’s secret store. This repo’s [`.gitignore`](.gitignore) is intended to block them.
- **Database files** — e.g. `*.sqlite`, dumps; use `.gitignore` and do not commit production data.
- **Keys and credential JSON** — private keys (`.pem`, etc.), Composer `auth.json` with tokens, cloud service-account JSON.
- **Editor plans / scratch** — `.cursor/plans/` is ignored by default so plan files with machine-specific paths are not added by mistake.

Quick sanity check before pushing (should print **nothing** if no stray env files are tracked—`*.env.example` files are fine):

```bash
git ls-files | grep -E '\.env$' || true
```

On Windows without `grep`, inspect **staged files** in your Git client or run `git diff --cached --name-only` and confirm `.env` is not listed.

## License

The Laravel framework components in `backend/` follow Laravel’s [MIT license](https://opensource.org/licenses/MIT). Add a root `LICENSE` file if you want an explicit license for the whole repository.
