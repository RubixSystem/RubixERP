# Plugin Patterns

## Core Build Pattern

Use this baseline for every plugin module:

- `src/<Module>ServiceProvider.php` extends `Webkul\PluginManager\PackageServiceProvider`
- `src/<Module>Plugin.php` implements `Filament\Contracts\Plugin`
- `composer.json` exposes plugin service provider under `extra.laravel.providers`
- `config/filament-shield.php` registers resource/page ACL scope
- `src/Policies/*` maps permission keys to abilities
- `database/migrations/*` and optionally `database/settings/*`
- `src/Settings/*` + settings pages when config flags are needed

## Service Provider Pattern

Common chain in `configureCustomPackage(Package $package)`:

- `->name('plugin-name')`
- `->hasViews()` and `->hasTranslations()` when UI module
- `->hasRoute('web')` / `->hasRoute('api')` / `->hasRoutes([...])`
- `->hasMigrations([...])->runsMigrations()`
- `->hasSettings([...])->runsSettings()` when needed
- `->hasSeeder('...DatabaseSeeder')` when plugin seeds data
- `->hasDependencies([...])` when plugin depends on other plugins
- `->hasInstallCommand(fn (InstallCommand $command) => $command->installDependencies()->runsMigrations()->runsSeeders())`
- `->hasUninstallCommand(...)`
- `->icon('...')`

Provider registration pattern:

- `Panel::configureUsing(fn (Panel $panel) => $panel->plugin(ModulePlugin::make()));`
- Package registration pattern:
- `composer.json` must include plugin namespace in `autoload.psr-4` and provider entry in `extra.laravel.providers`.

## Dependency Pattern

Always configure dependencies in both metadata and install runtime:

1. Metadata dependency list:
- `->hasDependencies(['invoices'])`

2. Install command behavior:
- `->installDependencies()`

Representative modules using this pattern:

- `purchases` depends on `invoices`
- `sales` depends on `invoices`, `payments`
- `inventories` depends on `products`
- `time-off` depends on `employees`
- `accounts` depends on `products`
- `accounting` depends on `accounts`

## Plugin Class Registration Pattern

Always guard plugin registration with installation state:

- `if (! Package::isPluginInstalled($this->getId())) { return; }`

Panel-aware discovery:

- Admin-only modules: discover from `src/Filament/...`
- Admin + customer modules: split into
`src/Filament/Admin/...` and `src/Filament/Customer/...`

Representative dual-panel modules:

- `purchases`
- `website`
- `blogs`

## Admin + Front (Customer) Setup

Use `purchases` as baseline for panel split:

- In `register(Panel $panel)`, use two branches:
- `when($panel->getId() == 'customer', ...)`
- `when($panel->getId() == 'admin', ...)`
- Configure `discoverResources`, `discoverPages`, `discoverClusters`, `discoverWidgets` per branch.
- Add admin settings quick links with `NavigationItem` and `->visible(fn () => Page::canAccess())`.
- Align front/customer routes and views (`routes/web.php`, `resources/views`, Livewire) when plugin serves customer-facing screens.

If frontend/customer auth UX must be customized, follow `website`:

- Configure panel auth page classes during plugin registration (`login`, `registration`, `passwordReset`).
- Add customer top/footer render hooks and navigation.

## ACL and Roles Pattern

ACL registration is plugin-local and merged by `PackageServiceProvider`:

- Put ACL map in `config/filament-shield.php`.
- Define `resources.manage` permission prefixes per resource.
- Use `resources.exclude` and `pages.exclude` for non-permission targets (clusters/pages).

Permission key naming is centralized in:

- `plugins/webkul/plugin-manager/src/PermissionManager.php`

Role synchronization and custom handling lives in security module:

- `plugins/webkul/security/src/Models/Role.php`
- `plugins/webkul/security/src/Filament/Resources/RoleResource.php`

## Settings Pattern

When plugin needs configurable feature flags/options:

1. Add settings migration in `database/settings/*.php` (`SettingsMigration`)
2. Add settings class in `src/Settings/*` extending `Spatie\LaravelSettings\Settings`
3. Add settings page extending `Filament\Pages\SettingsPage`
4. Attach page to proper navigation cluster/group (often support settings cluster)

Representative examples:

- `projects/src/Settings/TaskSettings.php`
- `projects/src/Filament/Clusters/Settings/Pages/ManageTasks.php`
- `purchases/src/Settings/OrderSettings.php`

## Dashboard and Widgets Pattern

For dashboard-like plugin pages:

- Extend `Filament\Pages\Dashboard`
- Use page shield traits
- Provide widgets via `getWidgets()` / `getHeaderWidgets()`

Representative examples:

- `time-off/src/Filament/Pages/Dashboard.php`
- `projects/src/Filament/Pages/Dashboard.php`

## Table Views Pattern

Enable saved/preset table views on list pages:

- Add `use Webkul\TableViews\Filament\Concerns\HasTableViews;`
- Implement `getPresetTableViews(): array`
- Return presets using `PresetView::make(...)->modifyQueryUsing(...)`

Representative examples:

- `purchases/.../OrderResource/Pages/ListOrders.php`
- `projects/.../ProjectResource/Pages/ListProjects.php`
- many list pages across accounts, inventories, recruitments, sales, invoices

## Translation Pattern

### Folder and file structure

Keep translation files in:

- `plugins/<vendor>/<plugin>/resources/lang/en`

Common subpaths used in this codebase:

- `filament/resources/...`
- `filament/admin/...`
- `filament/customer/...`
- `filament/clusters/...`
- `filament/pages/...`
- `filament/widgets/...`
- `models/...`
- `enums/...`
- `app.php` for plugin-level labels/messages

Mirror Filament class location in translation path where practical. Keep filenames kebab-case and domain-specific (resource/page/action).

### Resource and page translation file layout

Use one file for the resource and separate files for page-level strings:

- `resources/lang/en/filament/resources/<resource>.php`
- `resources/lang/en/filament/resources/<resource>/pages/create-<resource>.php`
- `resources/lang/en/filament/resources/<resource>/pages/edit-<resource>.php`
- `resources/lang/en/filament/resources/<resource>/pages/list-<resources>.php`
- `resources/lang/en/filament/resources/<resource>/pages/view-<resource>.php`

For panel-scoped plugins, include panel segment in the path:

- `resources/lang/en/filament/admin/...`
- `resources/lang/en/filament/customer/...`

For cluster-scoped modules, include cluster path:

- `resources/lang/en/filament/admin/clusters/<cluster>/resources/<resource>.php`
- `resources/lang/en/filament/admin/clusters/<cluster>/resources/<resource>/pages/*.php`

### Nested key conventions

Use predictable nested arrays grouped by UI surface:

- `navigation.title`, `navigation.group`
- `form.sections.<section>.title`
- `form.sections.<section>.fieldsets.<fieldset>.title`
- `form.sections.<section>.fields.<field>.label`
- `form.sections.<section>.fields.<field>.helper-text`
- `table.columns.<column>.label` (or scalar labels where plugin already uses scalar format)
- `table.groups.<group>.label` (or scalar labels where already used)
- `table.filters.<filter>.label`
- `table.record-actions.<action>.notifications.<success|error>.title|body`
- `table.toolbar-actions.<action>.notifications.<success|error>.title|body`
- `table.empty-state.heading|description`
- `infolist.sections.<section>.title`
- `infolist.sections.<section>.entries.<entry>.label|helper-text`

Use `filters` spelling consistently (never `fileters`).

### Usage convention

Consume keys with fully-qualified plugin namespace:

- `__('purchases::filament/admin/clusters/orders/resources/order.table.columns.reference')`
- `__('projects::filament/resources/project.form.sections.general.fields.name')`
- `__('products::filament/resources/product/pages/create-product.title')`

Follow existing plugin style in the same module (some plugins use scalar leaf values; others use `label`/`helper-text` objects). Prefer consistency within that plugin file over mixing styles.

## Extending Base Resources Pattern

Prefer inheritance when module reuses domain behavior:

- Extend base resources from source plugin and override only deltas.
- Keep child module small: pages mapping, cluster assignment, extra actions, view tweaks.

Frequent examples:

- accounting/invoices/sales/purchases resources extending accounts/products/contacts/recruitments base resources.

## Install and Validation Flow

1. Run `<plugin>:install --no-interaction`.
2. Verify dependency plugins are installed automatically.
3. Confirm plugin DB row is installed/active.
4. Verify admin/customer panel navigation + pages.
5. Verify ACL permissions are generated and visible in role management.
6. Verify settings migrations and settings pages load.
7. Verify list page preset table views work.
