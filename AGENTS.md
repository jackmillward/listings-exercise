# AGENTS.md

Guidance for AI coding agents (and humans) working in this repository.

`README.md` covers what this is and how to run it; `TASK.md` is the exercise brief. **Read both
before starting.** This file covers only how to work here: the checks you must pass, and the
standards the code follows.

This is Laravel + Inertia + Vue 3. **There is no JSON API** — controllers return
`Inertia::render(...)` with props, and API Resources define the shape of those props. Auth is
stubbed; every request resolves as the seeded demo user, so build user-scoped work against
`$request->user()` / `auth()->user()`.

## Checks

Run from the repository root, in this order, after **every** change:

```bash
vendor/bin/pint                  # 1. code style (fixes; run it, don't use --test)
vendor/bin/phpstan analyse       # 2. static analysis — Larastan, level 5
php artisan test                 # 3. PHPUnit
```

All three must pass before a change is done. A change that cannot pass one of them is not finished.

## Workflow rules

1. **Every change needs tests.** No exception — including refactors and front-end tweaks that
   change server-side output. If a change genuinely can't be tested, say so explicitly in your
   summary rather than skipping it silently.
2. **Never silence static analysis to get green.** Don't lower `phpstan.neon`'s `level`, add to
   `ignoreErrors`, or add `@phpstan-ignore` comments. Fix the code, or narrow an ignore in
   `phpstan.neon` with a comment explaining why.
3. **Leave no debugging leftovers** — `dd`, `dump`, `ray`, commented-out code.
4. **Don't commit** `.env`, `vendor/`, `node_modules/`, `public/build`, or
   `database/database.sqlite`. Don't commit at all unless asked.

## Standards

Applies across PHP, Vue and CSS.

- **Pint's `laravel` preset is the style authority.** Never hand-tune formatting — run Pint and
  let it fix things.
- **Comments explain *why*, not *what*.** Match the existing comments; they exist because the
  alternative is a subtle bug.
- **PHPDoc on anything with a non-obvious type** — return types, array shapes, generic
  annotations. Larastan reads it, so omitting it produces errors on correct code.
- **Fail loudly, validate at the edge.** Invalid input is rejected at the boundary rather than
  guarded for defensively further in.

## PHP

- **Enums** for closed value sets (`ListingStatus`, `PropertyType`). Add `label()` for display and,
  where a set drives a `<select>`, a static `options()` returning
  `list<array{value: string, label: string}>`.
- **Form Requests** for anything user-supplied, with an explicit `rules(): array<string, mixed>`.
  Controllers contain no validation logic. Use `Illuminate\Validation\Rules\Enum` for enum-typed
  input, and `nullable` so blank query-string values (`?max_price=`) don't fail validation.
- **API Resources** define prop shape: a `@mixin Model` docblock and a typed `toArray()`.
  Resources are **unwrapped** (`JsonResource::withoutWrapping()`), so props arrive as flat arrays;
  paginated collections keep `data` / `links` / `meta`.
- **Models** carry an `@property` block covering every attribute and cast, plus `@property-read`
  for relations. `casts()` tells Eloquent how to hydrate, but static analysis can't infer that from
  a string map — without the annotations, level 5 errors on every `$listing->status`. Relations
  and scopes need generics: `@return HasMany<Listing, $this>`, `@param Builder<Listing> $query`.
- **Factories carry the states.** Add a state per enum case when you add one, and keep derived
  attributes derived (`city` follows the branch's region, so a listing is always in the area its
  branch covers).
- **Eloquent scopes** for reusable query constraints (`Listing::query()->live()`), so every caller
  shares one definition of a concept.
- **Use `?->`** wherever a value can legitimately be null (`listed_at` is nullable until a listing
  goes live). Do not use it to hide errors.
- **Migrations** are anonymous classes returning `Migration` with `up()`/`down()`, `down()` uses
  `dropIfExists`, and every column you filter or order by gets an index — with a comment saying why.
- **Middleware** is registered in `bootstrap/app.php`; there is no `app/Http/Kernel.php`.
- **Routes** live in `routes/web.php`, named, one controller per resource. Page-returning methods
  are typed `Inertia\Response` — import that, not `Illuminate\Http\Response`. Route-model binding
  is by id, so guard visibility in the controller (`abort_unless(...)`), not in the route.

## Frontend (Vue / Inertia)

- `<script setup>` throughout, with explicit `defineProps` types and `required` where the prop is
  genuinely required.
- **A page's component name must match its file path exactly** (`Inertia::render('Listings/Index')`
  → `pages/Listings/Index.vue`), case-sensitively. A mismatch renders nothing and raises no error.
- **Filter state lives in the query string, never in component state.** Any new filter follows the
  pattern end to end: validate in a FormRequest → apply in the controller → echo back in the
  `filters` prop → bind in the filter component. Pages seed a form ref from `filters` and re-issue
  `router.get()` with `preserveState` / `preserveScroll` / `replace`, dropping blank values so the
  URL stays clean.
- **Reusable pieces go in `components/`**, wrapping page content in the shared layout.
- **Formatting helpers are centralised** in `resources/js/format.js` (`en-GB`) rather than
  inlined per component.
- **Props every page needs go in `HandleInertiaRequests::share()`**, not in each controller.
- **Tailwind classes stay on the element.** The project has no scoped CSS.

## Testing

- **`tests/Feature/`** for anything touching HTTP, a controller, a FormRequest, or a model
  relationship. **`tests/Unit/`** for pure logic — enum methods, condition objects, predicates
  testable against a hydrated model without persisting it.
- Every feature test uses `RefreshDatabase` and extends `Tests\TestCase`, which stubs the `@vite`
  directives so tests don't need a built asset manifest.
- **Assert on Inertia props, not rendered HTML.**

  ```php
  $this->get('/')
      ->assertOk()
      ->assertInertia(fn (AssertableInertia $page) => $page
          ->component('Listings/Index')
          ->has('listings.data', 3)
          ->where('listing.id', $listing->id)
      );
  ```

- **One assertion per behaviour**, so a failure points at exactly one thing.
- **Name tests for the behaviour**: `test_show_does_not_expose_non_live_listings`. When several
  states should behave identically, use a `#[DataProvider]` attribute rather than copy-pasting.
- **Don't depend on seed data.** Use factories, or call `$this->seed()` explicitly when the seed is
  what's under test.
- **Pin anything a factory randomises** that the assertion depends on — e.g. `live()` picks a
  random `listed_at`, so ordering tests must set it.
- **Set `per_page=100`** when asserting a count above the default page size of 15; reach for
  `viewData('page')['props']` when you need the raw payload.
- **POST tests assert both** the redirect and the persisted row.
- **The auth resolver returns `null` until the database is seeded**, so tests touching `auth.user`
  must `$this->seed()` first. If a test needs a *second* user (ownership, isolation), create one
  with `User::factory()->create()` and `actingAs()` it — that replaces the stub's resolver, so the
  test proves ownership honestly.
- **There's no front-end test runner.** Front-end behaviour is covered indirectly through props and
  the query-string contract in feature tests. Don't add a JS test framework without saying so.

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

