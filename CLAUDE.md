# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

Laravel 11 · Filament 5 · Livewire 4 · PHP 8.3+ · Tailwind 4 · Pest 4 / PHPUnit 12.

`AGENTS.md` contains the full Laravel Boost guidelines (PHP style, Filament 4/v3-to-v4 notes, Livewire v3, Pint, Pest) — follow them. `AGENTS.md` currently lists Filament v4 / Livewire v3, but `composer.json` pins `filament/filament ^5.0` and `livewire/livewire ^4.0` — trust `composer.json`.

## Common commands

```bash
# First-time setup (runs migrations + all seeders + shield permissions + admin user prompt,
# writes storage/installed marker). Use --force to bypass the "already installed" guard
# (will prompt "REINSTALL" and wipe the DB via migrate:fresh).
php artisan erp:install [--admin-name= --admin-email= --admin-password=]

# Per-plugin install/uninstall (every plugin auto-registers <shortname>:install and
# <shortname>:uninstall commands — see Package::hasInstallCommand in the plugin's
# ServiceProvider). These run the plugin's migrations + settings + seeders and refresh
# Filament Shield permissions.
php artisan <plugin>:install      # e.g. sales:install, inventories:install
php artisan <plugin>:uninstall

# Dev loop: server + queue + pail (logs) + vite, concurrently.
composer run dev
# Or individually: php artisan serve / npm run dev / npm run build

# Tests
php artisan test                                       # all suites
php artisan test --testsuite=SaleFeature               # one of the 7 suites in phpunit.xml
php artisan test plugins/webkul/sales/tests/Feature    # by path
php artisan test --filter=OrderTest                    # by name
# E2E (Playwright, separate package): cd tests/e2e-pw && npm test

# Formatting (required before finalizing changes — Laravel preset + aligned => + concat_space none)
vendor/bin/pint --dirty
```

## Plugin architecture (the big picture)

**Everything non-core lives in `plugins/webkul/<name>/`.** The root `composer.json` uses `wikimedia/composer-merge-plugin` to merge every `plugins/*/*/composer.json` into the root autoload — so PSR-4 namespaces like `Webkul\Sale\`, `Webkul\Accounting\` resolve to `plugins/webkul/<name>/src/`. After editing any plugin's `composer.json`, run `composer dump-autoload`.

Each plugin has:
- `src/<Name>ServiceProvider.php` — extends `Webkul\PluginManager\PackageServiceProvider` (Spatie-based). Declares migrations, settings, seeders, dependencies, translations, routes, install/uninstall commands.
- `src/<Name>Plugin.php` — implements `Filament\Contracts\Plugin`. Registered against the admin panel via `Panel::configureUsing(...)` inside `packageRegistered()`. **Guards itself with `if (! Package::isPluginInstalled($this->getId())) return;`** so resources/pages/widgets are only discovered when the plugin row exists in the `plugins` table.
- `database/migrations/`, `database/settings/`, `database/seeders/` — filenames are listed explicitly in the ServiceProvider (not auto-discovered). Plugins with `->isCore()` load migrations unconditionally; installable plugins load them only after `Package::isPluginInstalled()` is true.
- `routes/api.php` (optional) — loaded via `->hasRoutes(['api'])`. Admin APIs live under `admin/api/v1/<plugin>/*` guarded by `auth:sanctum`.
- `config/filament-shield.php` (optional) — merged into the root Shield config by `PackageServiceProvider::mergeShieldConfig()`.

All service providers are registered in `bootstrap/providers.php`. The `plugins` DB table (managed by `Webkul\PluginManager\Models\Plugin` / `Package`) is the source of truth for what's installed; `Package::$plugins` is a static cache read once from that table.

**Plugin dependencies** are declared via `->hasDependencies([...])` and cascade during `:install`. Uninstall does NOT cascade.

### Convention: adding behavior to a plugin

- Filament resources go in `plugins/<vendor>/<plugin>/src/Filament/Resources/` (auto-discovered by `$plugin->discoverResources(...)` — see any `*Plugin.php`). Same for `Pages/`, `Clusters/`, `Widgets/`.
- API controllers: `src/Http/Controllers/API/V1/`, routes in `routes/api.php`. Use `Route::softDeletableApiResource(...)` (macro from `Webkul\Support\Traits\HasRouterMacros`) to get `restore` + `force-destroy` endpoints for free.
- Pest feature tests: `plugins/<vendor>/<plugin>/tests/Feature/...`. Auto-wired to Pest via `tests/Pest.php` glob (`../plugins/*/*/tests/Feature`) and use `DatabaseTransactions`. Each such plugin test dir is also declared as its own PHPUnit testsuite in `phpunit.xml`.

## Panels

Two Filament panels in `app/Providers/Filament/`: `AdminPanelProvider` (`/admin`, default, Shield + Sanctum MFA) and `CustomerPanelProvider`. Plugins gate their resource discovery on `$panel->getId() == 'admin'` — check existing `*Plugin.php` before assuming a feature should appear on the customer panel.

Navigation groups (Sales, Purchase, Invoice, Accounting, etc.) are defined centrally in `AdminPanelProvider::panel()`; plugins attach their resources to these group labels via `->group('Sales')`.

## Testing notes

- `phpunit.xml` only lists 7 suites (Account, Partner, Purchase, Inventory, Sale, Project, Support). Other plugins' tests under `plugins/*/*/tests/Feature` still run via the Pest glob in `tests/Pest.php`, but won't show up when filtering by `--testsuite=`.
- Tests run against your configured DB (phpunit.xml comments out the sqlite `:memory:` defaults) and rely on the schema already being migrated. Run `php artisan erp:install` or `php artisan migrate` before the first test run.
- `tests/e2e-pw/` is a separate npm package for Playwright — do not mix its deps into the root `package.json`.

## Things that bite

- Editing a plugin's migration list in its ServiceProvider does not re-run migrations. Use `php artisan migrate --path=plugins/webkul/<plugin>/database/migrations/<file>.php` or re-run `<plugin>:install` (which skips already-applied migrations via `hasMigrationAlreadyRun`).
- `storage/installed` gates `erp:install`. Delete it (or pass `--force`) to re-run setup without wiping data manually.
- Plugin resources silently disappear from the admin UI if the `plugins` table row is missing or `is_installed=false` — the `*Plugin::register()` early-return is the cause, not a bug in Filament discovery.
- `composer-merge-plugin` means `composer install` / `composer update` is the only way to pick up a new plugin's composer.json; a bare `dump-autoload` after adding a plugin directory is not enough.
