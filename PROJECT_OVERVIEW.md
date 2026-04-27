# Rubix ERP — Project Overview

> An Enterprise Resource Planning (ERP) platform built on Laravel 11 and FilamentPHP 5, forked from AureusERP.

---

## 1. What Is Rubix ERP?

Rubix ERP is a fork of [AureusERP](https://github.com/aureuserp/aureuserp) — an MIT-licensed ERP system targeting small-to-medium businesses (SMEs) and large-scale enterprises. It centralises operations — sales, purchasing, inventory, accounting, HR, projects, and more — into a single admin panel without vendor lock-in.

**Core philosophy:**
- Plugin-based modularity — install only what you need
- Developer-first — clean PSR-12 code, 100 % typed, event-driven hooks, REST API

**Upstream docs:** <https://docs.aureuserp.com>
**Upstream dev docs:** <https://devdocs.aureuserp.com>

---

## 2. Technology Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.3+ |
| Framework | Laravel 11 |
| Admin Panel | FilamentPHP 5 |
| Frontend Components | Livewire 4, Alpine.js |
| CSS | Tailwind CSS 4 |
| Database | MySQL 8.0+ or SQLite 3.8.3+ |
| Auth & Permissions | FilamentShield (Spatie) + Laravel Sanctum |
| PDF | DOMPDF |
| Excel | Maatwebsite/Excel |
| API Docs | Scribe |
| Tests | Pest 4 / PHPUnit 12 |

---

## 3. Architecture

### 3.1 Plugin-Based Monorepo

Every feature lives in `plugins/webkul/<name>/`. The root `composer.json` uses `wikimedia/composer-merge-plugin` to auto-merge each plugin's `composer.json`, resolving PSR-4 namespaces like `Webkul\Sale\` to `plugins/webkul/sales/src/`.

Each plugin is self-contained:

```
plugins/webkul/<name>/
├── src/
│   ├── <Name>ServiceProvider.php   # extends PackageServiceProvider (Spatie-based)
│   ├── <Name>Plugin.php            # implements Filament\Contracts\Plugin
│   ├── Filament/Resources/         # Filament pages, clusters, widgets
│   ├── Http/Controllers/API/V1/    # REST API controllers
│   └── Models/
├── database/
│   ├── migrations/
│   ├── settings/
│   └── seeders/
├── routes/api.php
└── composer.json
```

The `plugins` DB table (managed by `Webkul\PluginManager\Models\Plugin`) is the single source of truth for which plugins are installed. Each `*Plugin::register()` guards itself with:

```php
if (! Package::isPluginInstalled($this->getId())) return;
```

This ensures resources and pages are silently hidden when a plugin is not installed — not a bug in Filament discovery.

### 3.2 Two Filament Panels

| Panel | Path | Purpose |
|---|---|---|
| **Admin** | `/admin` | Staff/back-office — all ERP features, Shield RBAC, MFA, database notifications |
| **Customer** | `/` | Customer-facing portal — simple profile, language switcher |

Navigation groups in the Admin panel (13 total): Dashboard · Contact · Sale · Purchase · Invoice · Accounting · Inventory · Project · Employee · Time-off · Recruitment · Website · Plugin · Settings.

### 3.3 Plugin Dependency Chain

```
products ──► accounts ──► invoices ──► purchases
                     └──► payments ──► sales ──────┐
                     └──► accounting               │
inventories ──► products                           │
employees ──► recruitments                         │
employees ──► time-off                             │
projects  ──► timesheets                           │
website   ──► blogs                                │
                                                   └─ (sales also requires payments)
```

Dependencies are resolved automatically during `:install`. Uninstall does NOT cascade.

### 3.4 API

Plugins expose REST endpoints under `admin/api/v1/<plugin>/*` guarded by `auth:sanctum`. The router macro `Route::softDeletableApiResource(...)` adds `restore` and `force-destroy` endpoints automatically.

---

## 4. Plugin Catalogue

### Core Plugins (auto-installed, always active)

| Plugin | Key responsibility |
|---|---|
| **plugin-manager** | Plugin install/uninstall lifecycle, permission refresh |
| **support** | Global infrastructure: currencies, countries, companies, activities, UTM, calendars, email templates, RTL |
| **security** | Users, teams, invitations, MFA, role-based access control (FilamentShield) |
| **partners** | Vendors/partners, bank accounts, addresses, industries, titles |
| **chatter** | Internal threaded messaging, followers, file attachments |
| **fields** | Custom field definitions per model |
| **table-views** | Saved table filter/column views and favourites |
| **analytics** | Business intelligence widgets, dashboards, KPIs |
| **full-calendar** | Calendar event visualisation (Alpine.js component) |

### Financial Management

| Plugin | Key features |
|---|---|
| **accounts** | Chart of accounts, journals, taxes, fiscal positions, bank statements, payment methods, reconciliation (52 migrations) |
| **accounting** | Journal entries, financial reports, period closing (depends on `accounts`) |
| **invoices** | Customer invoices, vendor bills, credit notes, due dates, early-payment discounts (depends on `accounts`) |
| **payments** | Payment methods, payment tokens, transaction tracking (depends on `accounts`) |

### Operations & Supply Chain

| Plugin | Key features |
|---|---|
| **products** | Product catalog, variants, attributes, packagings, price rules, supplier lists (17 migrations) |
| **inventories** | Real-time stock, warehouses, locations, routes, lots/serial numbers, scraps, reordering rules, barcode, inventory valuation (68 migrations) |
| **purchases** | Purchase orders, vendor management, RFQs, requisitions, price comparison, supplier ratings (depends on `invoices`) |
| **sales** | Quotations, sales orders, price lists, discounts, advance payments, sales teams (depends on `invoices` + `payments`) |

### Human Resources

| Plugin | Key features |
|---|---|
| **employees** | Employee records, departments, job positions, skills, resumes (timeline), work schedules, employment types (18 migrations) |
| **recruitments** | Job postings, applicant tracking, hiring stages, interviewer assignment, degree/source analytics, rejection reasons (depends on `employees`) |
| **time-off** | Leave types, allocations, accrual plans, approval workflows (depends on `employees`) |
| **timesheets** | Work-hour logging per project/task (depends on `projects`) |

### Project & Collaboration

| Plugin | Key features |
|---|---|
| **projects** | Projects, milestones, tasks, kanban/list views, file sharing, chatter integration, custom fields (13 migrations) |
| **contacts** | Customer/vendor contact book; integrates with Partners |

### Content & Web

| Plugin | Key features |
|---|---|
| **website** | Customer portal pages (home, about, FAQ, policy), SEO meta, draft/publish, customer accounts |
| **blogs** | Blog posts, categories, tags, media, SEO, built-in search (depends on `website`) |

---

## 5. Key Workflows

### 5.1 Procure-to-Pay

1. Create a **Purchase Requisition** → convert to **RFQ** → receive vendor quotation → confirm **Purchase Order**
2. Receive goods → **Inventory** receipt (with lot/serial tracking if enabled)
3. Vendor sends **Invoice** → match against PO → post to **Accounts** journal
4. Record **Payment** → bank reconciliation in **Accounting**

### 5.2 Quote-to-Cash

1. Create **Quotation** (Sales) → send to customer → customer confirms → **Sales Order**
2. Trigger **Delivery** (Inventory) → ship goods
3. Generate **Customer Invoice** → send → customer pays
4. **Payment** recorded → reconcile in Accounting

### 5.3 Hire-to-Retire

1. Create **Job Position** (Employees) → publish **Job Posting** (Recruitments)
2. Candidates apply → move through **Hiring Stages** → offer accepted
3. Onboard as **Employee** → set work schedule, department, skills
4. Track **Timesheets**, **Time-off**, performance reviews

### 5.4 Project Delivery

1. Create **Project** → define **Milestones** → add **Tasks**
2. Assign team members → log work via **Timesheets**
3. Communicate via **Chatter** (threaded, file attachments)
4. Track progress on kanban/list views with custom fields and saved table views

---

## 6. Installation & Setup

### Requirements

- PHP 8.3+
- MySQL 8.0+ or SQLite 3.8.3+
- Composer 2+
- Node.js 18+

### First-Time Install

```bash
git clone <rubix-erp-repo-url>
cd rubix-erp  # or whatever the cloned directory is named
composer install
cp .env.example .env          # configure DB_* credentials
php artisan erp:install       # migrations + seeders + Shield + admin account prompt
npm install && npm run build
php artisan serve
```

The `erp:install` command:
- Runs all core plugin migrations and seeders
- Generates roles and permissions (FilamentShield)
- Creates the admin user (interactive prompt or `--admin-email`, `--admin-password` flags)
- Writes `storage/installed` marker (use `--force` to re-run; triggers `migrate:fresh`)

### Installing Optional Plugins

```bash
php artisan sales:install
php artisan inventories:install
# etc.
```

Dependencies are auto-installed and cascaded. Each command is registered by the plugin's ServiceProvider as `<shortname>:install` / `<shortname>:uninstall`.

### Dev Loop

```bash
composer run dev    # php artisan serve + queue + pail + vite concurrently
vendor/bin/pint --dirty   # format before committing
php artisan test          # full test suite
```

---

## 7. Security & Access Control

- **FilamentShield** auto-generates permissions per Filament resource (view, create, edit, delete, restore, force-delete)
- **Teams** — users belong to teams; data can be scoped per team/company
- **MFA** — app-based TOTP via the Admin panel
- **API** — Laravel Sanctum tokens; endpoints guarded by `auth:sanctum`
- Supports GDPR/ISO compliance patterns via activity logging and audit trails

---

## 8. Extensibility

### Adding a Resource

Place a Filament resource class under `plugins/<vendor>/<plugin>/src/Filament/Resources/`. It is auto-discovered by `$plugin->discoverResources(...)` in `*Plugin.php`.

### Extending via Events

Laravel events fire at key lifecycle moments (order confirmed, invoice posted, employee hired, etc.). Listen to these to extend behaviour without touching core code.

### Custom Fields

The **Fields** plugin lets admins add custom fields to any entity through the UI — no migration required for simple attribute additions.

### REST API

Every plugin with an `api.php` route file exposes endpoints. Use Scribe (`php artisan scribe:generate`) to generate API docs.

---

## 9. Testing

Tests live in `plugins/<vendor>/<plugin>/tests/Feature/` and use `DatabaseTransactions` (no fixtures wiped between runs). They are auto-registered in Pest via the glob `../plugins/*/*/tests/Feature` in `tests/Pest.php`.

Seven suites are named in `phpunit.xml`: Account, Partner, Purchase, Inventory, Sale, Project, Support.

```bash
php artisan test                          # all suites
php artisan test --testsuite=SaleFeature  # named suite
php artisan test --filter=OrderTest       # by class/method name
```

E2E tests (Playwright) live in `tests/e2e-pw/` as a separate npm package.

---

## 10. Common Gotchas

| Situation | What to do |
|---|---|
| Plugin resources vanish from UI | Check `plugins` table — `is_installed` must be `true` for the plugin row |
| Migration change not reflected | Run `php artisan migrate --path=plugins/webkul/<name>/database/migrations/<file>.php` or reinstall the plugin |
| Added a new plugin directory | Run `composer install` (not just `dump-autoload`) to pick up the merged `composer.json` |
| `erp:install` says "already installed" | Delete `storage/installed` or pass `--force` (will `migrate:fresh` — destructive) |
| Need to re-run setup without wiping data | Use individual plugin `:install` commands; they skip already-applied migrations |
