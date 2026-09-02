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

`phpunit.xml` sets `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:`, so the test suite runs isolated from the dev database (`pdo_sqlite` must be enabled). 3 pre-existing tests fail independent of this — see "Orphaned Breeze scaffolding" below.

If route/view changes don't seem to take effect locally, check for a stale `bootstrap/cache/routes-v7.php` (or `route:cache` in general) and run `php artisan route:clear` — it's untracked/gitignored but persists across sessions once created, and silently shadows `routes/web.php`.

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

**`CaptivePortalForm` submit flow** (`app/Livewire/CaptivePortalForm.php`): validation (`$this->validate()`) runs *outside* the try/catch — `ValidationException` extends `\Exception`, so it must not be caught there or it gets swallowed and replaced by the generic error flash instead of rendering inline field errors (this was a real bug, fixed). After validation: finds-or-creates a `Client` by `phone_number`, creates a `Visit`, resets the form, and flashes a session message/type, with persistence wrapped in its own try/catch that logs and shows a generic Spanish error on failure.

**Admin dashboard** (`AdminController@index`): delegates visit-count stats (total/today/week/month/year) to `App\Services\VisitStatsService::summary()`, which caches each count individually via `Cache::remember` (300s TTL, flat keys `visit_count`, `today_visits`, etc. — not scoped per-user/date). Despite the "visits" naming, these counts are all based on `Client.created_at` (new clients), not `Visit.visited_at` (portal submissions) — a pre-existing naming/semantics quirk, not something recently changed. `Client::visits()` (hasMany `Visit`) is required for the controller's `withCount('visits')` call — it was missing until fixed alongside this.

**Orphaned Breeze scaffolding**: `resources/views/livewire/layout/navigation.blade.php` and `resources/views/profile.blade.php` (+ `app/Livewire/Forms`/`profile` Livewire components) are leftover from the original Breeze starter kit and are not included by any live layout — `resources/views/layouts/app.blade.php` (used by the admin dashboard) and `resources/views/components/layouts/app.blade.php` don't reference them. `navigation.blade.php` also calls `route('dashboard')`/`route('profile')`, but the real route is named `admin.dashboard` and no `/profile` route exists at all. This makes `tests/Feature/Auth/AuthenticationTest.php` (`navigation menu can be rendered`, `users can logout`) and `tests/Feature/ProfileTest.php` fail — pre-existing, unrelated to the domain logic (`CaptivePortalForm`, `VisitStatsService`, `access-admin-panel`). Fixing this means picking one of: wire the nav/profile views into the real layout, or delete the dead views/tests — a product decision, not made here.

Livewire/Volt views live under `resources/views/livewire/`; Blade layout components (`AppLayout`, `GuestLayout`) live under `app/View/Components/` with templates in `resources/views/components/layouts/`.
