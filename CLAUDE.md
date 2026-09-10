# Tech CRM — Project Context

## What this is
Multi-tenant Lead Generation & CRM backend (Laravel), inspired by GoHighLevel.
Core loop: FB Ad → Landing Page/Form → Lead → Pipeline (Opportunity) →
Automated Nurture (SMS/Email) → Booking Calendar → Appointment →
Confirmation → Review Request.

## Stack
- Laravel (latest), MySQL, Redis (queues), Eloquent ORM
- Multi-tenancy: single DB, `location_id` column + global scope on every tenant table
- Twilio for SMS, [Mailgun/SES] for email
- Queues for delayed campaign steps, Events/Listeners for the Trigger engine

## Repo
GitHub: mohit-coded/tech-crm (private), main branch

## Conventions
- Follow standard Laravel conventions (PSR-12, Eloquent relationships over raw queries)
- Every tenant-scoped model must use the `BelongsToLocation` trait/global scope
- Migrations: one feature per PR, always include down()
- Write feature tests for controllers, unit tests for services
- Every controller/relation change must be covered by a feature test before being marked complete — Phase 1b caught a missing relation this way
- `PipelineStage` has no `location_id`/`BelongsToLocation` scope of its own (see below) — any code that accepts a `PipelineStage` from outside its own pipeline context must verify tenancy explicitly
- When manually checking tenant ownership on a `BelongsToLocation`-scoped model resolved from a foreign key (not route-model binding), bypass the model's own global scope explicitly (`withoutGlobalScopes()`) to get the true value for comparison — otherwise the scope may return null for cross-tenant records instead of the real value, breaking the check or throwing instead of cleanly 403ing
- `routes/api.php` now exists (created via `php artisan install:api`, after Breeze's auth scaffolding install) and is registered in `bootstrap/app.php`. Breeze owns `web.php` now (dashboard/profile/`auth.php`) and artisan installers regenerate it, so anything custom added there is at risk of being overwritten and needs re-adding, as happened once before — but that's about *file ownership*, not where a route belongs; see the next bullet for the actual routing rule
- **Routing convention — `web.php` vs `api.php`:** any endpoint called via `fetch()`/XHR from our own Blade views (session-authenticated JS running on our own authenticated pages, same-origin) belongs in `routes/web.php`, inside the `auth` middleware group, not in `routes/api.php`. `api.php`'s routes are stateless by design (no session middleware, no CSRF) — that's for token-authenticated external API consumers, which this project doesn't have yet. The Kanban stage-move route (`PATCH /api/opportunities/{opportunity}/stage`) was originally placed in `api.php` and looked fine in feature tests, but a real browser `fetch()` got 401 there because the session cookie is never recognized without the `web` middleware group's session/cookie middleware — it now lives in `web.php` (same URL path, just routed differently; the `/api/...` prefix is cosmetic, not a statement about which routes file it's in). **Gotcha:** this class of bug is invisible to `actingAs()`-based feature tests — `actingAs()` sets the authenticated user directly on the app instance and bypasses the HTTP middleware pipeline entirely, so a route in the wrong middleware group still "passes" in tests while failing for a real browser. There's no automated coverage for this; it has to be caught by reasoning about which middleware group a route needs, or by testing with real HTTP requests (not `actingAs()`)
- Rollback/failure-path tests should force a real failure (e.g. dropping a required column mid-test) rather than mocking, where practical — this is how we proved the registration transaction actually rolls back
- **Public/unauthenticated routes and `BelongsToLocation`:** the trait's global scope is inert on these routes, not blocking — there's no `Auth::user()` for it to key off, so a tenant-scoped query with no explicit filter runs fully unscoped across every tenant's rows, not zero rows. This is a different failure mode from the `withoutGlobalScopes()` convention above (which bypasses-and-rechecks a scope that *would* otherwise apply, to get the true value for a comparison) — on a public route there's nothing to bypass, the scope was never going to help, so any tenant-scoped query here needs an explicit `location_id` filter. See `FunnelPublicController@store`, which resolves the funnel via `withoutGlobalScopes()` (correctly — it needs the true row regardless of tenant) but then filters the `Contact`/`Pipeline`/`Opportunity` queries by `$funnel->location_id` explicitly, because none of that scoping happens automatically with no authenticated user.
- **Factory gotcha:** a factory `definition()` default of `Model::factory()` (a nested factory relation) for a `BelongsToLocation` foreign key defeats the trait's `creating()` auto-fill, because auto-fill only fires when the attribute is genuinely absent — a nested factory always supplies one. `ContactFactory`/`FunnelFactory` deliberately omit `location_id` from their defaults so auto-fill works in tests that rely on it; `OpportunityFactory`/`PipelineFactory` don't, so tests using those must explicitly pass `'location_id' => null` to opt back into auto-fill (see the pattern in `OpportunityTest`).

## Build order (Phase 1 complete: 1a multi-tenancy foundation + 1b auth wiring/multi-location membership. Phase 2 complete: Opportunities/Pipeline, including the Kanban stage-move API. Phase 3 complete: Funnels/landing pages + lead capture, admin CRUD + public routes. Phase 8 basic Dashboard built with real data — see below; broader reporting still open.)
1. Auth + multi-tenant locations + Contacts/CRM base
2. Opportunities/Pipeline (Kanban)
3. Funnels/landing pages + lead capture
4. Scheduling/Calendar
5. Conversations (Twilio SMS)
6. Campaigns + Trigger engine
7. Reputation/review requests
8. Dashboard/reporting
9. FB Lead Ads + Google Business integrations

### Multi-location membership (Phase 1b)
- `location_user` pivot table (user_id, location_id, role) tracks which
  locations a user belongs to and their role there.
- Registration (`RegisteredUserController@store`) now creates a fully
  working tenant per signup, not just a bare `Location`: it takes a
  required "Business Name" field and, in a single DB transaction,
  creates the `User`, creates a `Location` named after it, attaches
  the user to that `Location` via `location_user` with role `owner`,
  sets the user's `current_location_id` to it, then creates a default
  `Pipeline` (`name: "Main Pipeline"`, `is_default: true`, explicit
  `location_id` since `Auth::login()` hasn't run yet so
  `BelongsToLocation`'s auto-fill has nothing to key off) and seeds it
  with the same 5 ordered stages as `DatabaseSeeder` (New Leads, Hot
  Leads, Booking Requested, Booking Confirmed, Service(s) Sold) — so a
  new signup has somewhere to put Opportunities immediately. If any
  step fails the whole thing rolls back — no orphaned `User`,
  `Location`, or partial `Pipeline`. See
  `tests/Feature/Auth/RegistrationTest.php`.
- `users.current_location_id` still represents the user's single
  *currently active* location — that's unchanged and is still what
  `BelongsToLocation`'s global scope keys off of.
- Access control over *which* locations a user is allowed to switch
  into is enforced separately, via the `location_user` pivot
  (`User::locations()` / `Location::users()`), not via
  `current_location_id`. `LocationSwitchController@switch` checks
  pivot membership (403 if none) before updating
  `current_location_id`.
- **UI-complete:** the location switcher isn't just an API anymore —
  `resources/views/layouts/navigation.blade.php` has a dropdown next
  to the user menu (Alpine-driven, via the shared `x-dropdown`
  component) showing `Auth::user()->currentLocation->name` as the
  trigger and listing `Auth::user()->locations` with role, greying
  out the current one and posting the rest to `locations.switch`
  (named route, still `POST /locations/switch/{location}`). Switching
  redirects back to the referring page via `back()`, not always to
  `/dashboard`. Covered by
  `tests/Feature/LocationSwitcherNavigationTest.php`.

### Contacts CRUD (Phase 1)
- `ContactController` (`resources` route, `Route::resource('contacts',
  ContactController::class)->except('show')`, inside the `auth`
  middleware group) is complete: index, create, store, edit, update,
  destroy. `show` is intentionally excluded — nothing links to it and
  it isn't part of this feature, so the route is left out rather than
  registered against a method that doesn't exist.
- Tenant isolation needs no manual `location_id` filtering anywhere in
  the controller: `index` queries `Contact` directly (the
  `BelongsToLocation` global scope handles it), and `edit`/`update`/
  `destroy` rely on implicit route-model binding, which resolves
  through that same global scope — a cross-tenant contact id simply
  fails to bind and 404s before the method body runs. `store` also
  omits `location_id`, relying on `BelongsToLocation`'s `creating()`
  auto-fill from `Auth::user()->current_location_id`, since (unlike
  registration) the user is already authenticated at that point.
- Views live in `resources/views/contacts/` (`index`, `create`,
  `edit`, shared `_form` partial) and reuse the Breeze card styling
  from `dashboard.blade.php` and the `x-input-label`/`x-text-input`/
  `x-input-error`/`x-primary-button` components from the auth views.
  "Contacts" is linked in the main nav next to "Dashboard"
  (desktop and mobile).
- Covered by `tests/Feature/ContactControllerTest.php`: index only
  lists the acting user's own-location contacts, store scopes via the
  trait, and edit/update/destroy all 404 (not silently succeed) on a
  contact from another location.

### Opportunities/Pipeline (Phase 2)
- `pipelines` and `opportunities` are `BelongsToLocation` tenant tables
  as usual. `Pipeline::stages()` is ordered by `position`.
- Stage changes on an `Opportunity` must go through
  `Opportunity::moveToStage(PipelineStage $stage)` — it's the only
  path that fires `OpportunityStageChanged` (old stage id, new stage
  id). Don't update `pipeline_stage_id` via raw attribute assignment
  or mass update elsewhere, or the event won't fire.
- **PipelineStage tenant-check rule:** `pipeline_stages` has no
  `location_id` column and `PipelineStage` does not use
  `BelongsToLocation` — it's only scoped indirectly, via
  `pipeline_id` → `Pipeline` → `location_id`. That means a
  `PipelineStage` fetched directly (e.g. route-model-bound from a
  request) is **not** automatically filtered to the current tenant
  the way `Contact`/`Pipeline`/`Opportunity` are. Any code that takes
  a `PipelineStage` from outside its own pipeline's `stages()`
  relation — controllers especially — must explicitly check
  `$stage->pipeline->location_id` against the acting user's
  `current_location_id` before using it. `Opportunity::moveToStage()`
  does not currently perform this check itself; it trusts the caller,
  so this must be enforced at the controller layer.
- **Kanban stage-move API:** `PATCH /api/opportunities/{opportunity}/stage`
  (`OpportunityStageController@update`, `auth` middleware) is that
  controller layer. It validates `pipeline_stage_id` exists, loads the
  `PipelineStage`, checks the stage's true pipeline `location_id`
  (via `Pipeline::withoutGlobalScopes()` — see the tenant-ownership
  convention above) against the acting user's `current_location_id`,
  aborts 403 on mismatch, then calls `moveToStage()` and returns the
  fresh opportunity with `stage` loaded as JSON. Covered by
  `tests/Feature/OpportunityStageControllerTest.php`. Registered in
  `routes/web.php` (not `api.php`) — see the routing convention above;
  it's called via same-origin `fetch()` from
  `resources/views/opportunities/board.blade.php` with a session
  cookie and the `X-CSRF-TOKEN` header read from the `csrf-token`
  meta tag, so it needs the `web` middleware group's session/CSRF
  handling. The URL path keeps its `/api/...` prefix for historical
  reasons even though it's not in `api.php` — the prefix is just part
  of the path string, not a routing-file indicator.

### Funnels (Phase 3)
- `FunnelController` (`Route::resource('funnels',
  FunnelController::class)->except('show')`, inside the `auth`
  middleware group) is the admin CRUD: index, create, store, edit,
  update, destroy. Same shape and same tenant-isolation reasoning as
  Contacts CRUD — route-model binding runs through `Funnel`'s
  `BelongsToLocation` scope, and `store` relies on the trait's
  `creating()` auto-fill rather than setting `location_id` manually.
  `slug` is validated unique and `alpha_dash`, ignoring the funnel's
  own row on update.
- Public lead capture is genuinely unauthenticated: `GET /f/{slug}`
  (`funnels.public.show`) and `POST /f/{slug}/submit`
  (`funnels.public.submit`), both in `FunnelPublicController`, live
  outside the `auth` middleware group entirely.
- **This is where the public-route scoping convention above actually
  bites.** `FunnelPublicController::publishedFunnel()` resolves the
  funnel via `Funnel::withoutGlobalScopes()->where('slug',
  ...)->where('is_published', true)->firstOrFail()` — bypassing the
  scope because there's no `Auth::user()` for it to key off anyway —
  and 404s equally for an unpublished or a nonexistent slug, so a
  slug's existence is never leaked either way. From there, the new
  `Contact` and the `Opportunity` it creates both set `location_id`
  explicitly from `$funnel->location_id`, and the `Pipeline` lookup
  (the location's default pipeline, falling back to its first
  pipeline) is filtered by `location_id` explicitly too — none of
  that scoping is automatic on this route.
- Covered by `tests/Feature/FunnelControllerTest.php` (admin CRUD
  tenant isolation, same shape as `ContactControllerTest`) and
  `tests/Feature/FunnelPublicControllerTest.php` (published vs.
  unpublished vs. nonexistent slugs all resolve correctly; a
  submission creates exactly one `Contact` and `Opportunity` scoped
  to the funnel's own location; submitting funnel A's form never
  creates data in funnel B's location).

### Dashboard (Phase 8, basic version)
- `GET /dashboard` (`DashboardController@index`) replaced the old
  "You're logged in!" placeholder with real data: total open
  (`status = 'open'`) opportunities count, sum of `monetary_value`
  for open opportunities (pipeline value), open-opportunity counts
  grouped by pipeline stage, and the 5 most recently created
  opportunities (with `contact` and `stage` eager-loaded).
- No manual `location_id` filtering anywhere in the controller — it
  relies entirely on `Opportunity`'s existing `BelongsToLocation`
  global scope, same as every other tenant query.
- View is `resources/views/dashboard.blade.php`: stat cards for count
  and pipeline value, a list for the stage breakdown, a list for
  recent opportunities — reusing the existing Breeze card styling
  (`bg-white dark:bg-gray-800 ... shadow-sm sm:rounded-lg`) from the
  rest of the layout.
- Covered by `tests/Feature/DashboardTest.php`, which is the
  important test here: it seeds two different locations with their
  own opportunities and asserts the dashboard for one user's location
  shows only that location's counts/values/stages/recents — i.e. it
  verifies the scope actually isolates dashboard data across tenants,
  not just that the numbers render.
- Broader reporting (beyond this basic dashboard) is still open under
  Phase 8.
