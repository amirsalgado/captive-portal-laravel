# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

A Laravel 10 + Livewire 3 (with Volt for auth pages) captive portal application. The public-facing side is a single form (`/`) where guests register their name, phone number, and birth date to gain network access; every submission also logs a `Visit`. An authenticated admin dashboard (`/dashboard`) shows visit stats and a birthday-alert widget for clients whose birthday is today. UI copy/validation messages are in Spanish.

## Commands

Standard Laravel/Composer/npm workflow (no repo-specific test/build scripts beyond these):

```sh
composer install
npm install

php artisan migrate          # run migrations
php artisan db:seed          # seeds roles + a default admin user (admin@example.com / password)

php artisan serve            # dev server
npm run dev                  # Vite dev server (run alongside `php artisan serve`)
npm run build                # production asset build

php artisan test             # run full test suite (PHPUnit)
php artisan test --filter=TestName   # run a single test
./vendor/bin/phpunit tests/Feature/Auth/AuthenticationTest.php  # run a single test file

./vendor/bin/pint             # code style fixer (Laravel Pint)
```

phpunit.xml has `DB_CONNECTION`/`DB_DATABASE` sqlite overrides commented out, so tests currently run against whatever DB connection is configured in `.env` — not an isolated in-memory sqlite DB. Keep this in mind when running tests locally (they will read/write the dev database) unless you uncomment those lines.

## Architecture

**Auth pages vs. app pages**: Login/register/password-reset/email-verification pages are Livewire Volt single-file components routed directly in `routes/auth.php` (`Volt::route(...)`) using views under `resources/views/livewire/pages/auth/`. Everything else (the captive-portal form, admin dashboard) uses conventional class-based Livewire components under `app/Livewire/`.

**Authorization is role-based via a `roles`/`role_user` pivot**, not a `role` column:
- `User::hasRole($role)` checks the `roles()` belongsToMany relation — this is the correct/current way to check roles.
- `AdminPolicy::accessAdminPanel()` checks `$user->hasRole('admin')` (fixed — it previously checked a non-existent `$user->role` attribute, which made the `access-admin-panel` gate always fail). The `/dashboard` route is now also gated by `can:access-admin-panel` middleware (in addition to `auth`/`verified`), so non-admin authenticated users get a 403 there.
- `AuthServiceProvider::$policies` maps `User::class` to both `AdminPolicy` and `UserPolicy` in the same array — the second entry (`UserPolicy`) silently overwrites the first, so `AdminPolicy` is not registered as a model policy. This doesn't affect the `access-admin-panel` gate above (it's wired directly via `Gate::define(..., [AdminPolicy::class, 'accessAdminPanel'])`), but it does mean `Gate::authorize($user, 'viewAny', User::class)`-style calls resolve to `UserPolicy`, not `AdminPolicy`. `UserPolicy`'s methods (`viewAny`, `view`, `create`, `update`, `delete`) all just proxy to `hasRole('admin')`.

**Namespace inconsistency**: `App\Livewire\BirthdayAlerts` (file at `app/Livewire/BirthdayAlerts.php`) is declared under `namespace App\Http\Livewire`, not `App\Livewire`. It still resolves correctly via Livewire's `<livewire:birthday-alerts />` tag (used in `resources/views/admin/dashboard.blade.php`) because of directory-based auto-discovery, but don't assume the namespace matches the directory when navigating this component.

**Core domain models** (`app/Models/`):
- `Client` — a captive-portal guest (`full_name`, `phone_number`, `birth_date`). Looked up/upserted by `phone_number` on each portal submission.
- `Visit` — belongs to `Client`; one row created per portal form submission (`visited_at`).
- `Role` / `User` — many-to-many via `role_user` pivot.

**`CaptivePortalForm` submit flow** (`app/Livewire/CaptivePortalForm.php`): validates, finds-or-creates a `Client` by `phone_number`, creates a `Visit`, resets the form, and flashes a session message/type — all wrapped in a try/catch that logs and shows a generic Spanish error on failure (validation errors themselves are not caught, they render inline as usual).

**Admin dashboard** (`AdminController@index`): computes visit counts (total/today/week/month/year) via `Cache::remember` with a 300s TTL and flat cache keys (`visit_count`, `today_visits`, etc. — not scoped per-user/date), plus a full client list with `withCount('visits')`.

Livewire/Volt views live under `resources/views/livewire/`; Blade layout components (`AppLayout`, `GuestLayout`) live under `app/View/Components/` with templates in `resources/views/components/layouts/`.
