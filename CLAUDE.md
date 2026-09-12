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
- **Resolving a child model with no `location_id` of its own** (like `PipelineStage`, `AvailabilityRule`): prefer looking it up through its already-tenant-verified parent's relation — `$parent->children()->find($id)`, e.g. `$calendar->availabilityRules()->find($ruleId)` — over a bare `Model::find($id)`. This achieves the same tenant safety as the `withoutGlobalScopes()`-then-compare check documented above, more simply, whenever the parent relation itself is the natural scoping boundary: a foreign/stale child id just won't be found through the wrong parent's relation and is silently excluded, with no separate comparison step needed. Reach for the explicit `withoutGlobalScopes()`-then-compare form instead when there's no such parent relation to scope through (e.g. `OpportunityStageController` needs the `PipelineStage`'s pipeline location compared against the acting user directly, since it isn't resolving through an already-verified parent).
- **`exists`/`unique` validation rules are not Eloquent-aware:** they query the referenced table directly, completely bypassing `BelongsToLocation`'s global scope. This is a distinct risk from the `withoutGlobalScopes()`/parent-relation conventions above — those are both about *query resolution* (fetching a model instance); this is about *validation*, where there's no model instance or query-builder scope involved at all, just a raw existence check against the table. A plain `'exists:calendars,id'` (or `Rule::exists('calendars', 'id')` with no constraint) on a tenant-scoped foreign key like `calendar_id` or `pipeline_id` will happily validate an id belonging to a *different* tenant as legitimate. Any `exists` rule referencing a `BelongsToLocation`-scoped foreign key must constrain it explicitly: `Rule::exists('calendars', 'id')->where(fn ($q) => $q->where('location_id', Auth::user()->current_location_id))`. See `FunnelController::validated()`'s `calendar_id` rule.
- **Session data referencing a tenant-owned record must be re-verified before use, same as a request-body id:** a session value isn't inherently more trustworthy than one submitted in a form — shared devices, session fixation, and (concretely, here) one visitor plausibly having two different funnels' submissions active in the same session all mean it can't be trusted on its own. Never use a session-stored id (e.g. a `Contact` id) directly; re-query it scoped to the current tenant/location and use the result, not the raw id. See `FunnelPublicController::sessionContactFor()`, which re-checks a session-stored `Contact` id against the *current* funnel's `location_id` on every read — proven by a test that plants a session value from location A's funnel under location B's funnel's own session key and confirms it's ignored rather than honored.
- **Template for wrapping any third-party API (established with Twilio, Phase 5a):** define an injectable interface (`SmsSender`), bind the real implementation to it as a lazy container singleton (a closure, not eagerly constructed at boot) in `AppServiceProvider::register()`, and have the rest of the app depend on the interface — never a static facade. "Lazy" matters here: the closure only runs (constructing the real SDK client) when something actually resolves the interface, so as long as tests bind a fake to the same interface *before* anything resolves it, the real client/credentials are never touched and no test can accidentally make a live network call. See `App\Services\SmsSender`/`TwilioSmsSender`/`SmsSendResult` and `Tests\Fakes\FakeSmsSender`. Apply the same shape to the next third-party integration (email, FB Lead Ads, etc.) rather than reaching for a facade or a `new Client(...)` inline.

## Build order (Phase 1 complete: 1a multi-tenancy foundation + 1b auth wiring/multi-location membership. Phase 2 complete: Opportunities/Pipeline, including the Kanban stage-move API. Phase 3 complete: Funnels/landing pages + lead capture, admin CRUD + public routes. Phase 4 complete: Calendars + Availability Rules (admin) plus the public booking flow (Phase 4b) — see below. Phase 5a built + code-reviewed: Conversations data model + outbound SMS sending (admin only, no public webhook yet), but unverified against a real Twilio send — blocked by a trial-account restriction, see below. Phase 8 basic Dashboard built with real data — see below; broader reporting still open.)
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
- **Funnel-Calendar linking is complete:** `funnels.calendar_id`
  (nullable FK, `nullOnDelete`) plus `Funnel::calendar(): BelongsTo`
  links a funnel to the `Calendar` its leads should book into —
  chosen from a "Booking Calendar" dropdown (populated from
  `Calendar::orderBy('name')->get()`, already tenant-scoped) on the
  create/edit forms, with a "None" option. This is the concrete case
  for the new `exists`-rule convention above: `calendar_id` is
  validated with `Rule::exists('calendars', 'id')->where(...
  'location_id', Auth::user()->current_location_id)`, not a bare
  `exists:calendars,id`, specifically so a cross-tenant calendar id
  can't validate as legitimate. Covered by two more cases in
  `FunnelControllerTest`: assigning a same-location calendar succeeds,
  and assigning another location's calendar id is rejected
  (`assertSessionHasErrors('calendar_id')`) with `calendar_id`
  confirmed still `null` on the funnel afterward.

### Calendars + Availability Rules (Phase 4, foundational)
- Admin-only foundation for scheduling — no public booking page yet,
  just the `Calendar` and `AvailabilityRule` data model and an admin
  CRUD to manage them. `calendars` (`location_id`, `name`,
  `duration_minutes` default 30, nullable `timezone`, `is_active`
  default true) is a normal `BelongsToLocation` tenant table.
  `availability_rules` (`calendar_id` FK cascadeOnDelete,
  `day_of_week` 0-6, `start_time`, `end_time`) has no `location_id` of
  its own — same situation as `PipelineStage`, scoped only indirectly
  via `calendar_id` → `Calendar` → `location_id`. `Calendar hasMany
  AvailabilityRule` (`availabilityRules()`, ordered by day then start
  time); `AvailabilityRule belongsTo Calendar`.
- `CalendarController` (`Route::resource('calendars',
  CalendarController::class)->except('show')`, inside the `auth`
  middleware group) is the admin CRUD: index, create, store, edit,
  update, destroy. Same shape and tenant-isolation reasoning as
  Contacts/Funnels CRUD — route-model binding through `Calendar`'s
  `BelongsToLocation` scope, `store` relies on the trait's
  `creating()` auto-fill.
- **Availability rules are managed inline on the calendar edit page,
  not via separate endpoints** — chosen over AJAX/mini-endpoints as
  the simplest correct option for a foundational, no-JS-required
  feature: one `<form>` on `calendars/edit.blade.php` submits the
  calendar fields together with the rules in a single `PUT
  /calendars/{calendar}` request. Existing rules render as rows keyed
  by rule id (`rules[{id}][day_of_week|start_time|end_time|remove]`)
  with a "Remove" checkbox; a fixed 3 blank `new_rules[i][...]` rows
  below them are silently skipped if left empty. Adding more than 3
  rules in one sitting means saving and reopening the edit page for
  another batch — an accepted limitation at this stage.
  `CalendarController::syncAvailabilityRules()` applies all of it
  (update/remove/create) in one pass after the calendar itself saves.
- **This is the model case for the new "resolve through the parent
  relation" convention above:** existing rule rows are looked up via
  `$calendar->availabilityRules()->find($ruleId)`, not
  `AvailabilityRule::find($ruleId)` — since `$calendar` is already
  route-model-bound and tenant-verified, a rule id belonging to
  another calendar (same tenant or a different one) simply isn't
  found through that relation and is silently skipped, never updated
  or deleted.
- Covered by `tests/Feature/CalendarControllerTest.php`: index/store
  isolation and edit/update/destroy 404s, same shape as
  `FunnelControllerTest`/`ContactControllerTest`, plus rule-specific
  cases — adding a rule, removing a rule, and (the important one)
  submitting another location's rule id through your own calendar's
  update, asserting it's left completely untouched rather than
  modified or deleted.
- **`AvailabilitySlotCalculator` (data layer, no UI/controller yet):**
  `app/Services/AvailabilitySlotCalculator.php`'s
  `getAvailableSlots(Calendar $calendar, Carbon $date): array`
  computes bookable slots for a calendar day — matches
  `AvailabilityRule`s for that day-of-week, generates candidate slots
  at `duration_minutes` intervals (never one that would extend past
  `end_time`), excludes slots overlapping a non-cancelled
  `Appointment` via proper interval overlap (not exact-match), and
  excludes already-past slots when the date is today. All math runs
  in the resolved timezone (calendar → location → UTC fallback chain
  — the final `?? 'UTC'` is defensive: `locations.timezone` is `NOT
  NULL DEFAULT 'UTC'` and `calendars.location_id` is required, so
  that branch can't actually be reached today, only guarded against).
  This introduced the `appointments` table (`location_id`,
  `calendar_id`, `contact_id`, UTC `starts_at`/`ends_at`, `status`
  enum `requested`/`booked`/`cancelled` — `requested` was added later,
  see Phase 4b below) purely as a data dependency at the time — no
  controller, routes, or views for it yet (that came with Phase 4b).
- **Known performance trade-off, left as-is for this foundational
  pass:** `AvailabilitySlotCalculator::bookedIntervals()` fetches
  *all* of a calendar's non-cancelled appointments rather than
  filtering by a date range around the requested day, then does the
  overlap check in PHP. Correctness was prioritized over query
  scope/precision here — date-range filtering across a stored-UTC
  column against a local calendar day has timezone-boundary edge
  cases that are easy to get subtly wrong, and appointment volume per
  calendar is expected to stay small at this stage. Flagged as the
  place to add a `whereBetween('starts_at', [...])` (bounded a day on
  each side of the local date, in UTC, to stay correct across any
  offset) once a calendar's appointment history grows enough for this
  to matter.
- Covered by `tests/Unit/AvailabilitySlotCalculatorTest.php` — no
  HTTP, no controllers (uses `Tests\TestCase` + `RefreshDatabase` only
  because the service reads real Eloquent relations). Cases: a single
  rule with no appointments; multiple rules on the same day producing
  correctly separated slot groups; an existing appointment excluding
  only the slots it overlaps (plus proving a *cancelled* appointment
  doesn't block); a partial, non-boundary-aligned overlap still
  excluded; the last slot that exactly fits a window included; a slot
  that would extend past `end_time` never generated; today's date
  with past slots excluded and future ones kept; a day with no
  matching rules returning `[]`; a DST spring-forward day still
  bounding slots to the rule's local start/end times; and the
  calendar → location timezone fallback.

### Public Booking Flow (Phase 4b)
- Completes the loop `AvailabilitySlotCalculator` was built for: a
  genuinely public, unauthenticated booking flow layered onto the
  existing funnel public routes, in the same route group and same
  `publishedFunnel()` resolution (`withoutGlobalScopes()`,
  published-only, identical 404 for unpublished/nonexistent slugs) as
  `show`/`store`. Three routes/methods on `FunnelPublicController`:
  `GET /f/{slug}/book` (`book`), `POST /f/{slug}/book/confirm`
  (`confirmBooking`), `GET /f/{slug}/book/confirmed`
  (`bookingConfirmed`). No `calendar_id` on the funnel → `book()`
  renders a "booking is not available for this offer" view instead of
  erroring.
- **Date-picker design — plain GET + `?date=...`, not a JS/fetch JSON
  endpoint:** picking a date re-renders `book()` with the slot list
  from `AvailabilitySlotCalculator`. Chosen over the Kanban-board-style
  fetch() pattern because it needs no new JSON endpoint or Alpine glue
  and is trivially testable with a plain HTTP GET — a full page
  round-trip per date pick is an accepted v1 trade-off here, same
  "simplest correct option" reasoning as the inline availability-rules
  form on the calendar edit page.
- **Carrying the lead's `Contact` through to booking — session-linked,
  re-verified, with a case-insensitive email fallback (updated after an
  initial version of this that only did a fresh email lookup — see the
  bug writeup below):** `store()` stashes the just-created `Contact`'s
  id in the session, scoped by funnel slug —
  `session(["funnel_contact_id.{$funnel->slug}" => $contact->id])` —
  so a visitor with two different funnel submissions active in one
  session doesn't collide. `sessionContactFor()` reads that value and
  re-verifies it against the *current* funnel's `location_id` before
  ever returning it (see the new session-data convention above).
  `book()` uses it to pre-fill the name/email/phone fields (still
  editable) instead of making the visitor retype everything.
  `confirmBooking()` uses the re-verified session `Contact` directly
  when present — sidestepping email matching entirely — and only falls
  back to a fresh lookup, matched case-insensitively
  (`whereRaw('LOWER(email) = ?', [strtolower($email)])`), for a
  visitor with no session link at all (e.g. a shared/bookmarked link
  straight to `/f/{slug}/book`).
- **The critical race-condition guard:** `confirmBooking()` re-runs
  `AvailabilitySlotCalculator` for the submitted date immediately
  before booking (not trusting whatever was shown when the page
  loaded) and only proceeds if the submitted `start_time` is still in
  the fresh list; otherwise it redirects back to `book()` (same date)
  with a `start_time` session error and creates nothing. Slot times
  returned by the calculator are in the calendar's resolved timezone —
  explicitly `->setTimezone('UTC')` before being stored, since
  Eloquent's datetime cast stores whatever timezone the Carbon
  instance already holds rather than converting for you (see the
  `starts_at`/`ends_at` comment on `Appointment`).
- **Known concurrency limitation, accepted for this pass:** the
  re-validation above closes the "page was loaded a while ago, slot
  got taken since" case, but not true simultaneous writes — two
  requests can both pass the "is this slot still available" read at
  the same instant and both proceed to create an `Appointment` for it,
  since there's no DB-level constraint stopping that and no row lock
  taken during the check. Future hardening: a unique constraint on
  `(calendar_id, starts_at)` scoped to non-cancelled appointments (a
  partial/filtered unique index, since `cancelled` rows must be
  allowed to coexist with a new booking at the same time), or a
  pessimistic lock (`lockForUpdate()`) held across the re-check and
  the `Appointment::create()` inside `confirmBooking()`'s transaction.
- Contact/Appointment creation, and the Opportunity stage move, all
  run inside one `DB::transaction()`. `confirmBooking()` looks up the
  lead's existing `Opportunity` (by `location_id` + `contact_id`) and,
  if the pipeline has a stage literally named **"Booking Requested"**
  (confirmed against the actual seeded name in `DatabaseSeeder` and
  `RegisteredUserController`), moves it there via `moveToStage()` —
  the only sanctioned way to change stage, so `OpportunityStageChanged`
  still fires. New appointments are created with `status: 'requested'`
  (added to the enum — see the appointments-table note above).
- The controller never reads a client-supplied `calendar_id` or
  `location_id` anywhere in `confirmBooking()` — it always uses
  `$funnel->calendar_id`/`$funnel->location_id`, so there's no code
  path for a submitted cross-tenant calendar id to do anything at all.
- Covered by `tests/Feature/FunnelBookingTest.php`: the booking page
  rendering real slots from the real `AvailabilitySlotCalculator` (not
  a mock); the no-calendar message; a valid booking creating exactly
  one `Appointment` with the correct `location_id`/`calendar_id`; the
  Opportunity moving to "Booking Requested" without duplicating the
  `Contact`/`Opportunity`; **the double-booking race test** — load the
  page, create a conflicting `Appointment` in between, then submit the
  now-stale selection and assert it's rejected with nothing created;
  a cross-tenant `calendar_id`/`location_id` submitted in the request
  body being silently ignored, with the resulting appointment still
  pointing at the funnel's own tenant; the email-casing regression
  (submit as `Test@Example.com`, book as `test@example.com`, exactly
  one `Contact`, appointment's `contact_id` matches the Opportunity's);
  the same case-insensitive match with no session link at all (proves
  the `whereRaw` fallback directly); the booking page pre-filling
  name/email/phone from the same-session submission; and a session
  value from funnel A's location planted under funnel B's own session
  key being ignored rather than honored (the re-verification test).
- **Thank-you-page link to booking is complete:** the original lead
  capture flow (`funnels/public.blade.php`'s `session('submitted')`
  state) had no way to reach `/f/{slug}/book` at all — fixed by adding
  a "Book Your Appointment" link there, guarded by the exact same
  `$funnel->calendar_id && $funnel->calendar` condition
  `FunnelPublicController@book` itself uses, so a funnel with no
  calendar keeps the plain thank-you message rather than showing a
  pointless/broken link. Covered by two more cases in
  `FunnelPublicControllerTest`, both driving the real `submit` →
  thank-you-page flow rather than injecting session state directly:
  the link appears when the funnel has a calendar, and is absent when
  it doesn't.
- **Bug: case-sensitive email matching silently orphaned bookings from
  their lead.** Manual testing found that submitting a lead as
  "Test1@gmail.com" and then booking as "test1@gmail.com" created a
  *second*, unlinked `Contact` — because `confirmBooking()`'s original
  `Contact` lookup was a plain `where('email', $email)`, and email
  addresses aren't case-sensitive but that comparison was. The
  Opportunity stage-move to "Booking Requested" then silently never
  ran, since it's keyed off the *original* contact's `contact_id`,
  which the appointment was never attached to. Root cause wasn't
  really "the lookup should be case-insensitive" (though it should be,
  and now is — see above) — it was that the booking form made a human
  retype an email at all, which is exactly where casing/typo drift
  comes from. The actual fix is the session-carry-through described
  above; the case-insensitive `whereRaw` is the secondary hardening for
  visitors who never went through the lead form in this session to
  begin with.

### Conversations (Phase 5a — data model + outbound SMS, no public webhook yet; built + code-reviewed, UNVERIFIED against a real send — see below)
- `messages` (`location_id` `BelongsToLocation`, `contact_id` FK,
  `direction` enum `inbound`/`outbound`, `body` text, nullable
  `twilio_sid`, `status` enum `queued`/`sent`/`delivered`/`failed`/
  `received` default `queued`) is a normal tenant table.
  `Message belongsTo Contact`; `Contact::messages(): HasMany`. Also
  added a nullable `locations.twilio_phone_number` — not consumed by
  anything yet (the sender always uses `config('services.twilio.
  phone_number')` as the `from` number), just the column for when
  per-location numbers are wired in.
- **`SmsSender` is the template for wrapping any third-party API** —
  see the new Conventions entry above for the general shape. Concretely
  here: `App\Services\SmsSender` (interface) / `TwilioSmsSender`
  (real implementation, wraps `Twilio\Rest\Client`) / `SmsSendResult`
  (value object — `successful`, `sid`, `errorMessage`). `send()` never
  throws: `TwilioSmsSender` catches any Twilio exception internally
  and returns a failed `SmsSendResult` instead, so callers have one
  uniform way to branch on outcome.
- `SendSmsMessage implements ShouldQueue` (`app/Jobs/`) takes an
  already-created `Message` (status `queued`, `location_id` already
  set — queued jobs run with no authenticated user, so nothing here
  can rely on `Auth::user()` or `BelongsToLocation`'s auto-fill).
  `handle(SmsSender $sender)` resolves the sender via the container
  (swappable in tests), updates `status`/`twilio_sid` to `sent` on
  success, and on failure — whether a returned failed result or a
  genuinely unexpected exception — logs via `Log::error()` and sets
  `status: 'failed'`, never letting anything throw out of the job.
- `MessageController@store` (`POST /messages`, `auth` middleware, no
  view yet — that's Part C) creates the `Message` and dispatches
  `SendSmsMessage`. Same `exists`-rule avoidance as the established
  convention: `contact_id` is validated as a plain integer, not
  `exists:contacts,id`, and resolved via `Contact::findOrFail()`
  instead — which runs through the live `BelongsToLocation` scope
  here (the user is authenticated, unlike a public route), so a
  cross-tenant `contact_id` 404s before any `Message` is created.
  `location_id` is set explicitly from
  `Auth::user()->current_location_id`.
- **Tests never make a real Twilio API call:** `Tests\Fakes\
  FakeSmsSender` is bound in place of `TwilioSmsSender` for every test
  that touches sending, and because the real binding in
  `AppServiceProvider` is a lazy singleton closure, the real
  `Twilio\Rest\Client` is never constructed unless something actually
  resolves `SmsSender::class` first — which these tests never let
  happen. Covered by `tests/Feature/MessageControllerTest.php`
  (`Queue::fake()` + `assertPushed` for a valid send with the right
  `location_id`/`contact_id`; a cross-tenant `contact_id` 404s,
  creates no `Message`, and never dispatches the job) and
  `tests/Feature/SendSmsMessageTest.php` (calls
  `(new SendSmsMessage($message))->handle($fakeSender)` directly, no
  queue worker — success sets `sent` + `twilio_sid`; failure sets
  `failed` and asserts, via `Log::spy()`, that the error was logged
  rather than thrown).
- **Not committed, must be set manually:** `TWILIO_ACCOUNT_SID`,
  `TWILIO_AUTH_TOKEN`, and `TWILIO_PHONE_NUMBER` are in `.env.example`
  as obviously-fake placeholders only. Outbound sending won't actually
  work in any environment until real Twilio credentials are added to
  that environment's own `.env` by hand.
- **UNVERIFIED against a real Twilio send — blocked on a trial-account
  restriction, not a code issue.** Everything above is built and
  code-reviewed (tests pass, and prove the plumbing — job, status
  transitions, tenant isolation — is wired correctly against a fake
  sender), but a real send through `TwilioSmsSender` has not actually
  succeeded end-to-end. A real attempt failed with: *"Invalid template
  name. Trial accounts can only use predefined SMS templates."* —
  Twilio trial accounts can't send arbitrary/custom message bodies
  (like the appointment-confirmation text this feature sends), only
  pre-approved template messages, until the account is upgraded to
  paid. Revisit and actually verify a real send once the Twilio
  account in use is upgraded — don't assume this phase is
  production-ready against live Twilio before that happens.

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
