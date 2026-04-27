# AureusERP API Conventions

Use this guide for any plugin API. Treat Products as a baseline example, not a hard dependency.

## Baseline examples

Primary baseline:
- `plugins/webkul/products/routes/api.php`
- `plugins/webkul/products/src/Http/Controllers/API/V1/ProductController.php`
- `plugins/webkul/products/src/Http/Controllers/API/V1/CategoryController.php`
- `plugins/webkul/products/src/Http/Controllers/API/V1/TagController.php`
- `plugins/webkul/products/src/Http/Requests/ProductRequest.php`
- `plugins/webkul/products/src/Http/Requests/CategoryRequest.php`
- `plugins/webkul/products/src/Http/Resources/V1/ProductResource.php`
- `plugins/webkul/products/src/Http/Resources/V1/CategoryResource.php`

Cross-plugin consistency examples:
- `plugins/webkul/sales/routes/api.php`
- `plugins/webkul/accounts/routes/api.php`
- `plugins/webkul/support/routes/api.php`

Shared router macro:
- `app/Providers/AppServiceProvider.php` (`softDeletableApiResource`)

## 1) Route conventions

Use route group:
- `name`: `admin.api.v1.<plugin>.`
- `prefix`: `admin/api/v1/<plugin>`
- `middleware`: `auth:sanctum`

Use resource registration style:
- `Route::apiResource('resource-name', ResourceController::class);`
- `Route::softDeletableApiResource('resource-name', ResourceController::class);` for soft-deletable models.
- Nested resources when needed: `Route::apiResource('parents.children', ChildController::class);`

`softDeletableApiResource` automatically adds:
- `POST <resource-path>/{id}/restore` -> `restore`
- `DELETE <resource-path>/{id}/force` -> `forceDestroy`

## 2) Controller conventions

Namespace:
- `Webkul\<Plugin>\Http\Controllers\API\V1`

Class attributes:
- `#[Group('<Plugin> API Management')]`
- `#[Subgroup('<ResourcePlural>', 'Manage <resource> ...')]`
- `#[Authenticated]`
- Keep API `Group` naming singular by plugin domain (for example `Product API Management`, `Account API Management`, `Inventory API Management`).

Method-level conventions:
- Add `#[Endpoint(...)]` on every action.
- Add `#[QueryParam(...)]` on list/show actions when query options are supported.
- Add `#[UrlParam(...)]` for each route parameter.
- Add `#[ResponseFromApiResource(...)]` on resource responses.
- Add explicit `#[Response(...)]` for common status codes (401/404/422 and delete success).
- Authorize each action via `Gate::authorize(...)`.
- Use `QueryBuilder::for(...)` for list/show query shaping.
- Use `findOrFail` and `withTrashed()->findOrFail` where applicable.

Preferred CRUD behavior:
- `index`: QueryBuilder + `allowedFilters`/`allowedSorts`/`allowedIncludes` + `paginate()` + resource collection.
- `store`: validate, create, eager load needed relations, return resource with `message`, status `201`.
- `show`: QueryBuilder scoped by id (and parent ids for nested resources), then policy check.
- `update`: validate, update, return resource with `message`.
- `destroy`: delete and return JSON message.
- `restore`: restore soft-deleted model and return resource with `message`.
- `forceDestroy`: permanently delete and return JSON message.

## 3) FormRequest conventions

Namespace:
- `Webkul\<Plugin>\Http\Requests`

Rules conventions:
- Keep `authorize()` as `true`; enforce permissions in controller policies.
- Always express validation rules as arrays; avoid pipe-delimited rule strings.
- For update handling (`PUT/PATCH`), define `$isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');` and use per-field ternary rules (`$isUpdate ? ['sometimes', 'required'] : ['required']`) instead of `array_map` or string-rewrite transformations.
- Require partial update support in every FormRequest: fields required for create must be `['sometimes', 'required']` for update unless intentionally immutable.
- Use enum validation via rule objects in arrays (`Rule::enum(MyEnum::class)`) instead of composing `'in:...'` strings.
- Do not expose system-calculated or lifecycle-managed fields in request rules. Build request payloads from user-editable Filament form inputs only.
- For nested line arrays, validate only editable line fields; exclude computed totals/margins/status fields and any server-derived linkage fields unless explicitly editable.
- Use explicit table names in `exists:<table>,id`.
- Derive API request rules from Filament resources/forms/pages: mirror workflow-specific required fields, conditional visibility/requirements, and nested repeater payload structure.
- If the plugin resource extends a resource from another plugin, inherit the matching parent plugin request class (when available) and keep child rules as deltas instead of duplicating parent rules.

Documentation conventions:
- Implement `bodyParameters()` with concise descriptions and realistic examples.
- Keep `bodyParameters()` strictly aligned with writable request fields (no computed/system fields in docs).

## 4) Resource conventions

Namespace:
- `Webkul\<Plugin>\Http\Resources\V1`

`toArray()` conventions:
- Return scalar fields first (`id`, domain fields, FK ids, timestamps, `deleted_at` when relevant).
- Cast numeric/boolean fields where needed.
- Use `whenLoaded(...)` for optional relations.
- Use `Resource::collection($this->whenLoaded(...))` for relation collections.
- If the plugin resource extends a resource from another plugin, inherit the matching parent plugin HTTP resource class (when available) and only add/override child-specific serialization details.

## 5) Filament analysis requirements

Before generating API code, inspect Filament resources in the target plugin (and parent plugin if inherited):
- identify create/edit/view/list actions and any custom workflow actions that affect writable payload
- extract canonical field definitions, defaults, readonly/computed flags, and conditional behavior
- map nested structures (repeaters/builders) to request array validation and body parameter examples
- exclude system-managed/computed fields from request validation and API docs unless explicitly editable in Filament
- detect parent resource class inheritance and map corresponding parent API request/resource classes for inheritance reuse

## 6) Policy and soft delete alignment

If a route uses `Route::softDeletableApiResource`:
- Ensure model uses soft deletes.
- Ensure policy includes `restore()` and `forceDelete()`.
- Ensure controller includes `restore()` and `forceDestroy()`.

## 7) Optional plugin dependency handling

AureusERP plugins are installed independently. Some columns, tables, and features only exist when a companion plugin is present (for example `inventories` adds `warehouse_id`, `route_id`, and `inventories_*` tables to the `sales` plugin).

### Detection

Check whether a plugin is installed using:
```php
use Webkul\PluginManager\Package;

Package::isPluginInstalled('inventories');
```

Conditional migrations use `Schema::hasTable('...')` / `Schema::hasColumn('...', '...')` to add columns only when the companion plugin is present. Inspect those migrations to identify which fields are conditional.

### Controller guards

When an entire controller action depends on a companion plugin, return a graceful fallback before executing any query against the optional table:

```php
public function index(string $order)
{
    $orderModel = Order::findOrFail($order);

    Gate::authorize('view', $orderModel);

    if (! Package::isPluginInstalled('inventories')) {
        return OperationResource::collection(
            new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10)
        );
    }

    // ... actual query
}
```

### Factory guards

Factory `definition()` methods must not unconditionally include columns added by optional plugins. Use `Schema::hasColumn()` with the spread operator to conditionally include them:

```php
use Illuminate\Support\Facades\Schema;
use Webkul\PluginManager\Package;

// For fields that only exist when a companion plugin is installed:
...(Schema::hasColumn('sales_orders', 'warehouse_id') ? ['warehouse_id' => null] : []),

// For enum fields whose valid values depend on a plugin:
'qty_delivered_method' => Package::isPluginInstalled('inventories')
    ? QtyDeliveredMethod::STOCK_MOVE
    : QtyDeliveredMethod::MANUAL,
```

### FormRequest guards

Validation rules for optional-plugin fields must be wrapped in a conditional so they are only applied when the column exists:

```php
...(Schema::hasColumn('sales_orders', 'warehouse_id') ? [
    'warehouse_id' => ['nullable', 'integer', 'exists:inventories_warehouses,id'],
] : []),
```

### JsonResource guards

Optional-plugin fields in resources must be guarded to avoid referencing missing attributes:

```php
...(Package::isPluginInstalled('inventories') ? [
    'warehouse_id' => $this->warehouse_id,
] : []),
```

### Key rule

Never let a request reach a query that targets a table or column that may not exist. Always guard with `Package::isPluginInstalled()` or `Schema::hasColumn()` / `Schema::hasTable()` before accessing optional-plugin data.

## 8) Implementation checklist

1. Inspect Filament resources/forms/pages (including parent plugin resources when inherited) and extract workflow + writable payload contracts.
2. **Identify optional plugin dependencies**: check conditional migrations to find columns/tables added only when a companion plugin is installed.
3. Create/update `routes/api.php` with grouped routes and resource registrations.
4. Create controller(s) in `src/Http/Controllers/API/V1` with Scribe attributes, `Gate::authorize` calls, and `Package::isPluginInstalled()` guards for companion-plugin actions.
5. Create/update `FormRequest` classes in `src/Http/Requests` with array-based rules, per-field ternary update handling, partial update support, `bodyParameters()`, parent request inheritance when applicable, and `Schema::hasColumn()` guards for optional-plugin fields.
6. Create/update `JsonResource` classes in `src/Http/Resources/V1` with consistent output, `whenLoaded` relations, parent resource inheritance when applicable, and `Package::isPluginInstalled()` guards for optional-plugin fields.
7. Create/update factory `definition()` methods to use `Schema::hasColumn()` / `Package::isPluginInstalled()` guards for any columns or enum values that only exist when a companion plugin is present.
8. Ensure model relationships match `allowedIncludes` and resource relations.
9. Ensure policies include all actions used by `Gate::authorize`.
10. Ensure restore/force routes and methods exist for soft-deletable resources.
