# API Test Case Guidance

## Scope

Use this guide for Pest feature tests of plugin API endpoints under `plugins/*/*/tests`.

## Workflow

1. Read routes + controller actions and map endpoint surface.
2. Read request validation rules and policy permission names.
3. Confirm plugin bootstrap strategy:
- Non-system plugin: call `TestBootstrapHelper::ensurePluginInstalled('<plugin>')`.
- System plugin (`support`, `partners`): do not run plugin install helper.
4. Build setup data and payloads with factories.
5. Add tests in order:
- unauthenticated
- forbidden
- index success
- store success + validation
- show success + not found
- update success + forbidden
- delete success + forbidden
- restore / force-delete if soft-deletable
6. Run formatting and focused tests.

## Core Conventions

1. Use API methods: `getJson`, `postJson`, `patchJson`, `deleteJson`.
2. Use factory-first setup:
- records via `Model::factory()->create()`
- payloads via `Model::factory()->make()->toArray()`
- override only conflicting fields (FK/enum/required/unique)
3. Keep helper responsibilities strict:
- `SecurityHelper`: auth/guards/Sanctum/permissions
- `TestBootstrapHelper`: ERP bootstrap + plugin install + non-security setup
4. Do not use `RefreshDatabase` or `LazilyRefreshDatabase` in test files.
5. Use route helper functions, not hardcoded URLs.

## Assertions

1. Prefer specific HTTP assertions:
- `assertOk`, `assertCreated`, `assertUnauthorized`, `assertForbidden`, `assertUnprocessable`, `assertNotFound`
2. Centralize JSON structure constants when repeated.
3. For list endpoints with seeded data, assert structure and non-empty data, not exact counts.
4. Respect `whenLoaded()` behavior for optional relationships.

## Pest Wiring

1. Ensure `tests/Pest.php` globally wires:
- `Tests\\TestCase`
- `Illuminate\\Foundation\\Testing\\DatabaseTransactions`
- plugin feature test paths
2. Avoid repeated `uses()` in individual test files when already globally wired.

## Guardrails

1. Do not use skip-as-solution.
2. Prefer test/helper fixes before app code edits.
3. Avoid raw inserts when factories exist.
4. Never hardcode seeded IDs.
5. Keep install/bootstrap non-interactive.
