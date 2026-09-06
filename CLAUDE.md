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

## Build order (Phase 1 complete: 1a multi-tenancy foundation + 1b auth wiring/multi-location membership. Phase 2 complete: Opportunities/Pipeline.)
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
- `users.current_location_id` still represents the user's single
  *currently active* location — that's unchanged and is still what
  `BelongsToLocation`'s global scope keys off of.
- Access control over *which* locations a user is allowed to switch
  into is enforced separately, via the `location_user` pivot
  (`User::locations()` / `Location::users()`), not via
  `current_location_id`. `LocationSwitchController@switch` checks
  pivot membership (403 if none) before updating
  `current_location_id`.

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
  so this must be enforced at the controller layer once one exists.
