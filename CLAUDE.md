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
- **Template for cancelling a running sequence of queued jobs (established with `SendCampaignStep`, Phase 6 Stage 2):** don't try to un-queue or delete a pending delayed job — there's no reliable handle for that once it's been dispatched. Instead, give the record the job acts on a live status flag (e.g. `CampaignEnrollment.status`), and have every job in the sequence re-fetch that record fresh from the DB as the first thing `handle()` does, then no-op immediately if the status is no longer what the job expects. Never trust `$this->someModel` as passed into the constructor for this check — it's a possibly-stale snapshot from whenever the job was dispatched (serialized, or just an in-memory copy if `handle()` is called directly in a test), not the live value. "Cancelling" the sequence then just means flipping that one flag; every already-queued future job harmlessly no-ops on its own when it eventually runs. Apply this same shape to any future multi-step delayed/queued sequence (e.g. a nurture drip, a multi-touch reminder chain) rather than inventing a way to reach into the queue and remove a specific pending job.
- **Stale-relation gotcha (hit building `EnrollContactsOnStageEntry`, Phase 6 Stage 3):** after calling a method that updates a model's own attribute in place — `moveToStage()` updating `pipeline_stage_id` via `$this->update()` is the concrete case — don't trust an already-accessed relation on that *same in-memory instance* anywhere later in the same request/listener/job chain, even indirectly (e.g. the same object reused across two dispatches of the same event). Eloquent caches a `BelongsTo`/etc. relation the first time it's accessed and `update()` doesn't invalidate that cache, so `$model->someRelation` can keep returning the pre-update related row instead of the one the updated foreign key now points to. This bit the listener directly: it read `$opportunity->stage?->name` to get the newly-entered stage's name, but on a `moveToStage()` call that stage relation had already been cached (from `stage_id` before the move) by an earlier access — e.g. the same listener already having run once for that opportunity's *creation* — so it silently read the stage being moved *out of* instead of the one moved *into*. The fix, and the reusable lesson: look the related row up fresh by the id you actually have in hand (here, `PipelineStage::find($event->newStageId)`) rather than walking a relation off a model instance whose attributes changed underneath it — `$model->fresh()->someRelation` also works, but a direct fresh `Model::find($id)` on the id you already have is simpler when you don't need the rest of the model reloaded too. Caught by `CampaignTriggerTest`'s `moveToStage()` case failing against a listener that looked correct in isolation — worth remembering any time an event fired *after* an in-place `update()` needs to read the post-update related state through a relation.

## Build order (Phase 1 complete: 1a multi-tenancy foundation + 1b auth wiring/multi-location membership. Phase 2 complete: Opportunities/Pipeline, including the Kanban stage-move API. Phase 3 complete: Funnels/landing pages + lead capture, admin CRUD + public routes. Phase 4 complete: Calendars + Availability Rules (admin) plus the public booking flow (Phase 4b) — see below. Phase 5 complete: Conversations — data model, outbound SMS sending, inbound webhook, and inbox UI (5a/5b/5c) — see below; the one open item is real Twilio verification of outbound sending, still pending a trial-account upgrade. Phase 6 complete: Campaigns end to end — data model + admin CRUD (Stage 1), the execution engine that walks a `CampaignEnrollment` through its steps (Stage 2), and the trigger engine that auto-enrolls a contact when their `Opportunity` enters a matching pipeline stage (Stage 3) — see below. Phase 7 complete: appointment completion and its reputation-adjacent auto-enrollment trigger (Stage 1), plus the per-location Google review link setting and `{{placeholder}}` resolution that let a campaign's own message bodies carry real review-link/contact-name values (Stage 2) — see below. As scoped, this phase builds the machinery a review-request campaign runs on, not a specific seeded "leave us a review" campaign/template itself — that's ordinary campaign content an admin creates through the existing Phase 6 UI using this phase's `appointment_completed` trigger and `{{review_link}}`/`{{contact.first_name}}` placeholders, not further app code. Phase 8 basic Dashboard built with real data — see below; broader reporting still open.)
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
- **Performance trade-off from the original foundational pass — now
  closed (booking-engine hardening pass):** `bookedIntervals()` used to
  fetch *all* of a calendar's non-cancelled appointments rather than
  filtering by a date range, prioritizing correctness over query scope
  at the time — date-range filtering across a stored-UTC column
  against a local calendar day has timezone-boundary edge cases that
  are easy to get subtly wrong. It's now bounded: the query filters to
  `[localDate - 24h, localDate + 48h]` (i.e. a full day of padding on
  each side of the requested local day), converted to UTC before use
  in the query, same `->setTimezone('UTC')` discipline as everywhere
  else a local Carbon instance needs to hit a UTC-stored column. 24
  hours per side is deliberately generous rather than an exact
  boundary — the widest real-world UTC offset is +14:00 (Kiribati) to
  -12:00 (Baker Island), so a full day's padding on each side covers
  every timezone (and any DST shift within it) with room to spare,
  without computing or special-casing an exact offset per timezone.
  Over-padding costs a handful of extra rows fetched; under-padding
  risks silently excluding a genuinely overlapping appointment, which
  is the one outcome this can't allow — the trade deliberately favors
  width. `bookedIntervals()` now takes the already-computed
  `$localDate` as a third parameter to build this window. Proven by a
  new case in `AvailabilitySlotCalculatorTest`
  (`...excluded_by_a_bounded_query`) that does two things, not just
  one: confirms results are still correct with appointments weeks
  away, *and* inspects the actual logged SQL (`DB::enableQueryLog()`)
  to confirm the appointments query now filters by `starts_at`/
  `ends_at` at all — proving the query is actually bounded, not just
  that results happen to still come out right.
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
- **Concurrency limitation from the original pass — now closed
  (booking-engine hardening pass), via two layers, not one:** the
  re-validation above only ever closed the "page was loaded a while
  ago, slot got taken since" case — a plain read, so two requests
  could still both pass it at the exact same instant and both proceed
  to create an `Appointment` for it. Both pieces of the "future
  hardening" this note used to flag are now actually in place:
  1. **A pessimistic lock**, inside `confirmBooking()`'s
     `DB::transaction()`, right before `Appointment::create()`: locks
     every non-cancelled `Appointment` on this calendar overlapping
     the exact slot being booked (`->lockForUpdate()`), then does the
     real overlap check against *that* locked result — not the
     calculator's earlier, pre-lock one. A concurrent request racing
     this one either blocks until this transaction commits (then
     correctly sees the row just inserted and bails out), or —
     **critically** — finds nothing to lock yet and proceeds anyway,
     if this is a genuinely first booking of that slot with no prior
     row for either transaction to lock. That gap is what layer 2
     closes.
  2. **A DB-level unique index**, added via migration, on
     `(calendar_id, starts_at)` scoped to non-cancelled appointments —
     the backstop for exactly that "nothing exists yet to lock" case.
     Neither database this app runs on (MySQL in production, SQLite
     for local/tests — see `config/database.php`) supports a true
     partial/filtered unique index (Postgres/SQL Server's
     `UNIQUE (...) WHERE status <> 'cancelled'` has no MySQL/SQLite
     equivalent), so it's emulated the portable way both actually
     support: a generated column, `active_slot_marker`, that evaluates
     to `1` for a non-cancelled appointment and `NULL` for a cancelled
     one, included in a composite unique index alongside
     `(calendar_id, starts_at)`. NULL is never considered equal to
     another NULL for unique-index purposes on either database, so any
     number of cancelled appointments can freely share a slot while
     two non-cancelled ones for the same slot collide and the second
     `INSERT` is rejected. **Explicitly not** a plain unique index on
     `(calendar_id, starts_at, status)` — that's not equivalent and
     would be a bug: `status` being part of the key there means only
     two appointments with the *exact same* status value would
     collide, so a `'requested'` and a `'confirmed'` row for the same
     slot could still coexist. The second `Appointment::create()`
     losing this race surfaces as Laravel's own
     `Illuminate\Database\UniqueConstraintViolationException`
     (driver-portable — both `MySqlConnection` and `SQLiteConnection`
     detect their own driver's unique-violation error and Laravel
     throws this specific subclass of `QueryException` for it), caught
     in `confirmBooking()` alongside a new
     `App\Exceptions\AppointmentSlotUnavailableException` (thrown by
     the lockForUpdate() check above) and converted to the same
     user-facing "that time was just booked" redirect either way.
  Covered by `tests/Unit/AppointmentActiveSlotUniqueIndexTest.php`
  (bypasses the booking flow entirely — `Appointment::create()` called
  directly, twice — to prove the constraint itself works,
  independent of the application-level lock: two non-cancelled
  appointments for the same calendar/start time collide; a
  `'requested'` and a `'confirmed'` one for the same slot *also*
  collide, proving the `(calendar_id, starts_at, status)` shortcut
  really would have been wrong; a cancelled appointment never blocks
  reusing its slot; a different calendar or a different start time
  never collides) and a new case in `FunnelBookingTest`
  (`...runs_a_locked_overlap_check_before_creating_the_appointment`)
  that confirms a normal booking still succeeds with the lock in place
  and that the locked query actually runs — and, since SQLite compiles
  `lockForUpdate()` to a silent no-op (it has no row-level locking
  syntax; see `Illuminate\Database\Query\Grammars\SQLiteGrammar::
  compileLock()`), separately compiles the identical query shape
  against a real `MySqlGrammar` to confirm `FOR UPDATE` is actually
  emitted for the production driver, since that can't be observed
  against the local/test one. The already-existing race test (slot
  taken between page load and submission) still passes unmodified,
  now backed by an actual database-enforced guarantee rather than just
  the softer re-validation read.
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

### Conversations (Phase 5b — inbound SMS webhook, complete)
- `POST /webhooks/twilio/sms` (`TwilioWebhookController@sms`) is
  public — not in the `auth` group — but protected by the new
  `twilio.signature` middleware (`App\Http\Middleware\
  VerifyTwilioSignature`, aliased in `bootstrap/app.php`) instead,
  which runs before any other logic and aborts 403 on a missing or
  invalid `X-Twilio-Signature`. That middleware validates via
  `Twilio\Security\RequestValidator` against `config('services.
  twilio.token')`, `request()->fullUrl()`, and `request()->post()`.
  The route is also explicitly excluded from CSRF validation in
  `bootstrap/app.php` (`$middleware->validateCsrfTokens(except:
  [...])`) — Twilio's POST carries no Laravel CSRF token, and
  signature verification is what authenticates this route instead.
- **Caveat: `request()->fullUrl()` may not match the public URL
  Twilio actually POSTed to, once this is behind a reverse proxy or
  tunnel.** Signature validation needs the *exact* public URL Twilio
  used — fine for direct requests, but behind ngrok, a load balancer,
  or real hosting, the scheme/host Laravel sees can differ from
  what's publicly reachable, which would make a genuinely valid
  signature fail this check. If that happens, look at Laravel's
  trusted-proxy config (the `TrustProxies` middleware / `X-Forwarded-
  *` headers) before assuming the signature itself is wrong — this is
  flagged in a comment directly on `VerifyTwilioSignature` too.
  Revisit once this actually goes live behind ngrok or real hosting;
  assuming direct requests for now.
- **Resolving a `Location` by the `To` number is deliberately
  cross-tenant** — there's no `location_id` to scope this lookup by
  yet, it's what we're discovering. This is safe specifically because
  `VerifyTwilioSignature` has already run and proven the request came
  from *our* Twilio account about a message sent to *one of our own*
  verified numbers — it's trusting Twilio's signed assertion of which
  of our numbers received it, not an arbitrary claim from an
  unauthenticated client the way an unscoped lookup on a public form
  would be. No match on `twilio_phone_number` → empty 200 TwiML, no
  error (an unrecognized number isn't a transient failure worth
  Twilio retrying). Once a `Location` is found, the `Contact`
  lookup-or-create by the `From` number *is* scoped explicitly by
  `location_id` — same discipline as the Funnels public routes,
  since `BelongsToLocation`'s scope is inert with no authenticated
  user.
- Every inbound message creates a `Message` with `direction:
  'inbound'`, `status: 'received'`, `body` from `Body`, `twilio_sid`
  from `MessageSid`, and the controller always responds with the
  minimal `<?xml version="1.0" encoding="UTF-8"?><Response></
  Response>` TwiML so Twilio doesn't treat it as a failure.
- Covered by `tests/Feature/TwilioWebhookTest.php`, with every test
  signature computed via Twilio's own `RequestValidator` against a
  fake token bound in `config()` — never hardcoded. Cases: a valid
  signature with a matching `To` creates exactly one `Message` and
  `Contact`, scoped correctly; a second inbound message from the same
  number reuses that `Contact` rather than duplicating it; **an
  invalid or missing signature is rejected with 403 and creates
  nothing** (the important one); a valid signature with an
  unrecognized `To` returns 200 and creates nothing, not an error;
  and two locations with different numbers — an inbound message to
  location A's number never creates data under location B, even when
  the `From` number coincidentally matches an existing contact there.

### Conversations (Phase 5c — inbox UI, complete; closes Phase 5)
- `ConversationController@index` (`GET /conversations`) lists
  `Contact`s that have at least one `Message`
  (`Contact::whereHas('messages')`), ordered by their most recent
  message's `created_at` descending via `withMax('messages',
  'created_at')` + `orderByDesc('messages_max_created_at')`, with a
  truncated preview of the last message body. That preview comes from
  the new `Contact::latestMessage(): HasOne` relation (a
  `latestOfMany()` `Message`), eager-loaded alongside — avoids pulling
  every message for a contact just to read the last one. No manual
  `location_id` filtering, same as everywhere else.
- `ConversationController@show(Contact $contact)`
  (`GET /conversations/{contact}`) is route-model-bound and
  tenant-safe via the same live `BelongsToLocation` scope as every
  other admin controller — no extra check needed. Loads all of that
  contact's messages in chronological order (inbound and outbound
  interleaved) for the thread view.
- Thread view (`conversations/show.blade.php`) reuses existing
  Tailwind patterns rather than inventing new styling: the same
  card container as other admin pages, plain flex (`justify-start`/
  `justify-end`) with the app's existing color tokens (indigo-600 for
  outbound, gray-100/700 for inbound) for the bubbles, and the
  existing `x-text-input`/`x-primary-button`/`x-input-error`
  components for the send form at the bottom.
- **The send form posts to the existing `messages.store` route
  (`MessageController@store`) with `contact_id` as a hidden field —
  no new send endpoint was needed.** `MessageController@store`
  already redirected via `redirect()->back()`; this was verified,
  not assumed, to correctly return to the conversation thread page,
  since the form's `action` posts from that exact page and the
  browser's `Referer` header reflects it. The test drives this
  explicitly with `$this->from($threadUrl)->post(...)` and asserts
  the redirect target, rather than trusting `back()` blindly.
- Covered by `tests/Feature/ConversationControllerTest.php`: index
  tenant isolation (also proving a contact with zero messages is
  excluded from the list); index ordering by most-recent-message
  descending; `show()` 404s for a cross-tenant contact; `show()`
  renders inbound/outbound messages in correct chronological order;
  and sending from the thread (`Queue::fake()`, same pattern as
  `MessageControllerTest`) creates the message, redirects back to
  that same thread, and the thread then displays it.

### Campaigns (Phase 6, Stage 1 — data model + admin CRUD only)
- **No execution engine as of this stage** — that's Stage 2, below,
  which is now complete. `campaigns`/`campaign_steps`/
  `campaign_enrollments` exist and can be managed through the admin
  UI, but nothing built in *this* stage enrolls a `Contact`, advances
  an enrollment through its steps, or sends anything automatically —
  `trigger_event` on a `Campaign` is still just a string field, not
  wired to any actual event listener (that's Stage 3, still open).
  Same incremental approach as `AvailabilitySlotCalculator` (Phase 4):
  build and test the data layer in isolation first, wire up execution
  later.
- `campaigns` (`location_id` `BelongsToLocation`, `name`,
  `trigger_event`, `is_active` default true) is a normal tenant
  table. `campaign_steps` (`campaign_id` FK cascadeOnDelete,
  `position`, `channel` enum `sms`/`email`, `body`, `delay_minutes`
  default 0) has no `location_id` of its own — same situation as
  `AvailabilityRule`/`PipelineStage`, scoped only indirectly via
  `campaign_id` → `Campaign` → `location_id`. `campaign_enrollments`
  (`location_id` `BelongsToLocation`, `campaign_id`, `contact_id`,
  nullable `current_step_id` → `campaign_steps` with `nullOnDelete` —
  an enrollment shouldn't vanish just because the step it's on gets
  deleted later, `status` enum `active`/`completed`/`cancelled`
  default `active`) exists as a table + model
  (`CampaignEnrollment belongsTo Campaign/Contact/currentStep`) but
  nothing creates rows in it yet. `Campaign hasMany CampaignStep`
  (`steps()`, ordered by `position`) and `hasMany CampaignEnrollment`
  (`enrollments()`); `CampaignStep belongsTo Campaign`.
- `CampaignController` (`Route::resource('campaigns',
  CampaignController::class)->except('show')`, inside the `auth`
  middleware group) is the admin CRUD: index, create, store, edit,
  update, destroy — same shape and tenant-isolation reasoning as
  Calendars/Contacts/Funnels CRUD.
- **Steps are managed inline on the campaign edit page, reusing
  Calendars' inline-availability-rules pattern exactly** — proven
  there already, so no new pattern was invented: one `<form>` submits
  the campaign fields together with its steps in a single `PUT
  /campaigns/{campaign}` request. Existing rows come in keyed by step
  id (`steps[{id}][channel|body|delay_minutes|remove]`) with a
  "Remove" checkbox; a fixed 3 blank `new_steps[i][...]` rows below
  them are silently skipped if left without a body.
  `CampaignController::syncSteps()` is a near-literal mirror of
  `CalendarController::syncAvailabilityRules()`.
- **Same tenant-check discipline as `AvailabilityRule`:** existing
  step rows are resolved via `$campaign->steps()->find($stepId)`,
  never a bare `CampaignStep::find($id)` — since `$campaign` is
  already route-model-bound and tenant-verified, a step id belonging
  to another campaign (same tenant or a different one) simply isn't
  found through that relation and is silently skipped, never updated
  or deleted.
- **Blade gotcha caught before it shipped:** an early draft of
  `campaigns/edit.blade.php` wrote
  `:value="old(\"steps.{$step->id}.body\", ...)"` — nesting a
  double-quoted, interpolated PHP string literal inside a Blade
  *component*'s `:value="..."` binding (itself double-quoted), which
  breaks Blade's attribute-quote parsing. This is different from
  `calendars/edit.blade.php`'s plain `<input value="{{ old(\"...\")
  }}">`, where `{{ }}` is just an echo with no such constraint —
  components and plain echoed attributes don't have the same quoting
  rules. Fixed by using single-quoted string concatenation instead:
  `old('steps.'.$step->id.'.body', ...)`. Worth remembering before
  reusing this exact inline-rows pattern again with `<x-text-input>`
  instead of a plain `<input>`.
- Covered by `tests/Feature/CampaignControllerTest.php`: index/store
  isolation and edit/update/destroy 404s, same shape as
  `CalendarControllerTest`, plus step-specific cases — adding,
  editing, and removing a step, and (the important ones, mirroring
  `CalendarControllerTest`'s availability-rule test exactly) two
  cases proving a smuggled foreign-location step id through your own
  campaign's update is silently ignored — whether the attempted
  operation was an edit or a removal — leaving the real owner's step
  completely untouched.

### Campaigns (Phase 6, Stage 2 — execution engine, complete)
- **No trigger/auto-enrollment as of this stage** — that's Stage 3,
  below, which is now also complete. This stage only builds the
  machinery to walk an *already-created* `CampaignEnrollment` through
  its campaign's steps — nothing built in *this* stage calls
  `enroll()` on its own. `trigger_event` was still an inert string
  field until Stage 3 wired it to a real event listener.
- `App\Services\EnrollsContacts::enroll(Contact $contact, Campaign
  $campaign): CampaignEnrollment` creates the enrollment (`status:
  'active'`, `location_id` set explicitly from `$campaign->location_id`
  — never via `BelongsToLocation` auto-fill, since this may end up
  called from a queued job or event listener later with no
  authenticated user) and dispatches `SendCampaignStep` for the
  campaign's *first* step, delayed by that first step's own
  `delay_minutes` — a sequence can have an initial delay before its
  very first message, not just between later messages.
  `EnrollsContacts::cancel(CampaignEnrollment $enrollment): void`
  just sets `status: 'cancelled'`; nothing else is needed, given how
  `SendCampaignStep` is written (see the next bullet, and the new
  cancellation-pattern convention above).
- `App\Jobs\SendCampaignStep implements ShouldQueue` (constructor:
  `CampaignEnrollment $enrollment`, `CampaignStep $step`) is the
  reusable cancellation-pattern in practice (see Conventions above):
  `handle()` re-fetches the enrollment fresh via
  `CampaignEnrollment::find($this->enrollment->id)` first thing and
  no-ops immediately if it's not found or `status !== 'active'` —
  this, not un-queuing a pending delayed job, is the entire
  cancellation mechanism. If still active: for `channel: 'sms'`, it
  creates a `Message` (`direction: 'outbound'`, explicit `location_id`
  from the enrollment, `status: 'queued'`) and dispatches the existing
  `SendSmsMessage` job — reusing Phase 5's sending infrastructure
  rather than reimplementing it. **For `channel: 'email'`, it only
  logs (`Log::info`) that email sending isn't implemented yet and
  moves on — a known, deliberate gap (no email infra exists at all
  yet), not a bug, and not a blocker for finishing the SMS path.**
  Either way, it then updates `enrollment.current_step_id` to this
  step, looks up the campaign's next step by `position`, and either
  dispatches a new `SendCampaignStep` for it (delayed by that *next*
  step's own `delay_minutes`) or, if there is no next step, marks the
  enrollment `completed`.
- Covered by `tests/Feature/CampaignExecutionTest.php`, unit-level
  throughout — no real queue worker, `Queue::fake()` +
  `assertPushed`/`assertNotPushed` to check what gets dispatched (and
  its delay), `->handle()` called directly to observe side effects,
  same style as `SendSmsMessageTest`. Cases: enrolling dispatches the
  first step's job with that step's own delay; running a step's job
  creates a `Message` and dispatches `SendSmsMessage` (no real Twilio
  call); running a step's job dispatches the next step's job with the
  *next* step's own `delay_minutes`; running the last step's job marks
  the enrollment `completed` and dispatches nothing further;
  **running a step's job against an enrollment that was cancelled
  since the job was constructed sends nothing and dispatches nothing
  further** — the critical proof that the cancellation mechanism
  actually works, by constructing the job with a pre-cancellation
  enrollment instance and only cancelling afterward, before calling
  `handle()`; `cancel()` sets `status: 'cancelled'`; and `enroll()`
  sets `location_id` explicitly when called with no authenticated
  user at all (proving it doesn't blow up or silently omit it).

### Campaigns (Phase 6, Stage 3 — trigger engine, complete; closes Phase 6)
- Auto-enrollment: a `Contact` is enrolled into a matching `Campaign`
  automatically when their `Opportunity` enters a pipeline stage whose
  name matches that campaign's `trigger_event`. Nothing manual is
  needed at either the creation or move-to-stage call site — see the
  next two bullets for how both paths funnel into the same listener.
- `Opportunity::booted()` (new) dispatches `OpportunityStageChanged($opportunity,
  null, $opportunity->pipeline_stage_id)` from a `created()` hook
  whenever a freshly-created `Opportunity` already has a
  `pipeline_stage_id` — creating an opportunity with an initial stage
  now counts as "entering" that stage, same as a later `moveToStage()`
  call, so any code that creates an `Opportunity` with a stage (now or
  in the future) fires the trigger-relevant event without that call
  site needing to know about triggers at all. `OpportunityStageChanged.
  oldStageId` is now `?int` (was `int`) to carry this `null` "no prior
  stage" case; `moveToStage()`'s own dispatch is unchanged.
- `App\Listeners\EnrollContactsOnStageEntry` (registered via
  `Event::listen(OpportunityStageChanged::class, ...)` in
  `AppServiceProvider::boot()` — **explicit registration, not
  auto-discovery:** this app's `bootstrap/app.php` never calls
  `->withEvents()`, so Laravel's `app/Listeners` auto-discovery isn't
  active here, unlike a stock Laravel 11+ skeleton) handles both
  dispatch paths with one code path: resolves the entered stage's name
  (see the stale-relation gotcha in Conventions above for why this is
  `PipelineStage::find($event->newStageId)->name`, not
  `$opportunity->stage?->name`), builds
  `"opportunity_stage:{stage name}"`, and looks up active `Campaign`s
  in the opportunity's own `location_id` with exactly that
  `trigger_event` — **name-based matching, not stage-id-based,**
  consistent with the existing "Booking Requested"/"Booking Confirmed"
  stage-lookup-by-name pattern elsewhere in the app (see
  `FunnelPublicController`), not a new convention. Location filtering
  is explicit via `Campaign::withoutGlobalScopes()->where('location_id',
  $opportunity->location_id)`, not a reliance on `BelongsToLocation`'s
  scope, since this listener may run with no authenticated user (a
  queued job, a future non-HTTP trigger source) — same reasoning as
  every other "queued job"/"public route" explicit-filter case in this
  app. For each matching campaign, **the idempotency guard**: skips
  (does not enroll again) if the opportunity's contact already has an
  `active` `CampaignEnrollment` for that specific campaign — without
  this, the same lead could be double-enrolled if this event ever
  fires twice for the same transition. Otherwise calls
  `EnrollsContacts::enroll($contact, $campaign)` (Stage 2's service,
  unchanged).
- **Admin form:** `trigger_event` on the campaign create/edit forms
  (`resources/views/campaigns/_form.blade.php`) is now a `<select>` of
  `"opportunity_stage:{name}"` options, not a free-text field — built
  from `CampaignController::stageNames()`, the distinct pipeline stage
  names across the current location's pipelines. That query
  (`PipelineStage::whereHas('pipeline')->distinct()->orderBy('name')->pluck('name')`)
  needs no manual `location_id` filtering: `whereHas('pipeline')`
  applies `Pipeline`'s own `BelongsToLocation` global scope to the
  subquery automatically, the same way a global scope applies anywhere
  else — `PipelineStage` itself still has none of its own (unchanged
  from the Phase 2 note above). If the location has no pipeline
  stages at all yet, the dropdown is replaced with a plain explanatory
  message rather than rendering empty/broken. The campaign's *current*
  `trigger_event` value is always kept selectable even if it no longer
  matches any known stage name (a renamed/deleted stage, or Stage 1
  data predating this dropdown) — added specifically so opening and
  re-saving an existing campaign unchanged can't silently rewrite its
  trigger to a different value just because the dropdown didn't
  recognize it.
- Covered by `tests/Feature/CampaignTriggerTest.php`. The event-firing
  case: creating an `Opportunity` with an initial stage dispatches
  `OpportunityStageChanged` with `oldStageId === null` (via
  `Event::fake([OpportunityStageChanged::class])`, same
  only-fake-this-event pattern as `OpportunityTest`, to keep Eloquent's
  own creating/saving events — and `BelongsToLocation`'s auto-fill —
  working). The integration cases (no event faking, so the real
  listener runs): a matching active campaign auto-enrolls the
  contact on initial creation; **the same scenario but the contact
  already has an active enrollment for that campaign creates no
  second one** (the idempotency guard, the critical test here); moving
  an opportunity via `moveToStage()` (not just fresh creation) also
  correctly triggers a matching campaign — this is the test that
  caught the stale-relation bug above; a campaign in a different
  location with an exactly-matching `trigger_event` string is never
  triggered by an opportunity in another location; and an inactive
  campaign with a matching `trigger_event` enrolls no one. Plus two
  view-smoke tests for the new dropdown: it renders
  `opportunity_stage:{name}` options built from real pipeline stage
  names on both the create and edit pages, and the create page shows
  the empty-state message when the location has no pipeline stages
  yet.

### Appointment completion + reputation trigger (Phase 7, Stage 1)
- Adds a `completed_at` (nullable timestamp) column to `appointments`,
  set by a new `AppointmentController@complete(Appointment $appointment)`
  — same route-model-bound, tenant-safe shape as `confirm()`/`cancel()`
  (a cross-tenant appointment id simply 404s before the method body
  runs, no manual `location_id` check needed). Registered as
  `POST /appointments/{appointment}/complete` in `routes/web.php`,
  alongside `confirm`/`cancel`.
- **Only a `confirmed`, not-yet-completed appointment can be
  completed** — completing one that was never confirmed, was
  cancelled, or is already completed is rejected (a flashed
  `session('error')`, nothing changed) rather than silently allowed or
  silently ignored; the appointments index now renders that flash
  alongside the existing `session('status')` one. `status` itself is
  *not* changed to some new `'completed'` value — it stays
  `'confirmed'`, and `completed_at` being non-null is what represents
  completion. The index's "Mark Completed" button is shown only when
  `status === 'confirmed' && ! completed_at`; once completed, a small
  "Completed" badge renders next to the status badge instead.
- `App\Events\AppointmentCompleted` (plain event, same shape as
  `OpportunityStageChanged`/exactly the established pattern) is
  dispatched from `complete()` on success.
  `App\Listeners\EnrollContactsOnAppointmentCompletion` (registered
  via `Event::listen()` in `AppServiceProvider::boot()`, same
  explicit-registration reasoning as `EnrollContactsOnStageEntry` —
  see that note above) is a near-literal copy of
  `EnrollContactsOnStageEntry`'s shape, simplified: no stage name to
  resolve, just a single fixed `trigger_event` string,
  `'appointment_completed'`. Finds active `Campaign`s in the
  appointment's own `location_id` with exactly that `trigger_event`
  (same explicit `withoutGlobalScopes()->where('location_id', ...)`
  filtering, same reasoning — this listener may run with no
  authenticated user) and enrolls the appointment's contact into each
  via `EnrollsContacts`, skipping any campaign the contact already has
  an `active` enrollment for — the same idempotency guard as the
  stage-entry listener, for the same reason (this event firing twice
  for the same appointment must not double-enroll).
- **Admin form:** the `trigger_event` dropdown
  (`campaigns/_form.blade.php`) now always offers a fixed
  `"appointment_completed"` option — labeled "When an appointment is
  marked completed" — merged in ahead of the per-stage
  `"opportunity_stage:{name}"` options built from the location's
  pipelines. Unlike the per-stage options, this one doesn't depend on
  any pipeline existing, so the old "no pipeline stages yet" empty
  state (Phase 6 Stage 3) no longer means an empty/broken dropdown —
  the dropdown always has at least this one option now. That old
  message is kept, but only as a secondary hint shown below the
  (now never-empty) dropdown when there are no stage-based options,
  rather than replacing the dropdown entirely.
- Covered by two test files. `AppointmentControllerTest` (extended):
  cannot complete another location's appointment (same 404 pattern as
  confirm/cancel); completing a confirmed appointment sets
  `completed_at` and dispatches `AppointmentCompleted`
  (`Event::fake()` + `assertDispatched`); completing a non-confirmed
  appointment is rejected — `completed_at` stays null, status
  unchanged, `session('error')` present, event not dispatched; and
  (an extra case beyond what was strictly asked, cheap to add given
  the guard already existed) completing an already-completed
  appointment is also rejected rather than silently resetting
  `completed_at` to a new timestamp. `AppointmentCompletionTriggerTest`
  (new, mirrors `CampaignTriggerTest`'s shape exactly): a matching
  active campaign auto-enrolls the appointment's contact; the
  idempotency case — an already-actively-enrolled contact is not
  double-enrolled; a campaign in a different location with an
  exactly-matching `trigger_event` is never triggered; and an inactive
  campaign with a matching `trigger_event` enrolls no one.

### Dynamic review link + message placeholders (Phase 7, Stage 2; closes Phase 7)
- Adds a nullable `google_review_url` (string) column to `locations`,
  now in `Location::$fillable`. No admin UI of any kind existed for
  editing `Location`-level settings before this — locations were only
  ever created via registration or tinker — so this is also the first
  such page.
- **`LocationSettingsController`** (`edit`/`update` only, `auth`
  middleware, `GET`/`PUT /settings`) is deliberately *not* a
  route-model-bound resource controller — there's one settings page
  per location, not a list, so both methods act directly on
  `Auth::user()->currentLocation` with no `{location}` route
  parameter at all. **This makes it tenant-safe by construction, not
  by a check:** unlike every other controller in this app (which
  needs an explicit 404-on-cross-tenant-id test because a malicious id
  could be substituted into the URL), there is structurally no id in
  this route to substitute — `update()` can only ever act on whichever
  location `current_location_id` already points to. Proven, not just
  asserted, by `LocationSettingsControllerTest`'s "only ever affects
  the authenticated user's own current location" test: acting as user
  A and submitting the update changes only location A, leaving a
  freshly-created location B (which the test never even routes
  through) completely untouched. Kept deliberately minimal to just the
  `google_review_url` field for this phase, even though `Location` has
  several other fillable fields (`phone`, `timezone`, etc.) with no
  admin UI of their own either — expanding this page to cover those is
  explicitly out of scope here, same incremental-build reasoning as
  `AvailabilitySlotCalculator`/Campaigns Stage 1: build what the
  current phase actually needs, not a speculative full settings page.
  A "Settings" link was added to both the desktop and mobile nav,
  alongside the existing top-level links.
- **`App\Services\ResolvesMessagePlaceholders::resolve(string $body,
  Contact $contact, Location $location): string`** is the
  placeholder-substitution engine campaign message bodies run through.
  Supports exactly two placeholders — `{{review_link}}` (→
  `$location->google_review_url`, or `''` if not set — never leaves
  the literal placeholder behind) and `{{contact.first_name}}` (→
  `$contact->first_name`) — via a plain `strtr()` call with a fixed
  replacement map. **An unrecognized placeholder (e.g. a typo like
  `{{contct.first_name}}`) is deliberately left exactly as written,
  not stripped** — `strtr()` only touches the exact keys given it, so
  anything else in the body passes through untouched. This is a
  conscious choice, not an oversight: silently eating unknown
  `{{...}}` syntax would make a typo invisible in the admin UI and
  only show up (if at all) as a confusingly blank spot in a sent
  message; leaving it as literal text makes the typo obvious and
  debuggable in the message itself.
- **Wired into `SendCampaignStep`:** for an `sms` step, the body
  passed to `Message::create()` is now
  `ResolvesMessagePlaceholders::resolve($this->step->body,
  $enrollment->contact, $enrollment->location)` instead of the raw
  `$this->step->body` — resolved against the *freshly re-fetched*
  `$enrollment`'s own `contact()`/`location()` relations (`location()`
  comes from `BelongsToLocation` itself), not the job's original
  constructor arguments, so this is naturally safe from the
  stale-relation gotcha documented above (the enrollment fetched via
  `CampaignEnrollment::find()` at the top of `handle()` is a brand new
  object with nothing cached on it yet). The `email` branch is
  unaffected — it's still just a logged no-op, see Phase 6 Stage 2.
- **Admin form:** the `trigger_event` `<select>` (Phase 6 Stage 3 /
  Phase 7 Stage 1) doesn't need any changes for placeholders — they're
  just plain text an admin types into a step's `body` field on the
  existing campaign edit page, not a separate UI concept.
- Covered by three things. `Tests\Unit\ResolvesMessagePlaceholdersTest`:
  both placeholders resolve correctly together; an unset
  `google_review_url` resolves to an empty string rather than leaving
  `{{review_link}}` in the output; an unrecognized placeholder is left
  untouched verbatim. A new case in `CampaignExecutionTest`
  (`...resolves_message_placeholders_with_real_contact_and_location_values`):
  a real `EnrollsContacts::enroll()` call followed by a real
  `SendCampaignStep::handle()` call (same "call the job's `handle()`
  directly" pattern every other job-side-effect test in that file
  already uses — `Queue::fake()` is only there to stop the
  `SendSmsMessage` this step dispatches from actually running, since
  that would otherwise attempt a real Twilio call under this app's
  `QUEUE_CONNECTION=sync` test setting) produces a `Message` whose
  body has the real contact first name and review URL substituted in,
  not the raw `{{...}}` template text. `LocationSettingsControllerTest`:
  the edit page renders the current location's saved
  `google_review_url`; a `PUT` updates it on the authenticated user's
  current location; and the tenant-isolation-by-construction test
  described above.

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
