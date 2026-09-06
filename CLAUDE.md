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

## Build order (Phase 1a complete: multi-tenancy foundation — locations, contacts, BelongsToLocation scope. Phase 1b (auth wiring) next.)
1. Auth + multi-tenant locations + Contacts/CRM base
2. Opportunities/Pipeline (Kanban)
3. Funnels/landing pages + lead capture
4. Scheduling/Calendar
5. Conversations (Twilio SMS)
6. Campaigns + Trigger engine
7. Reputation/review requests
8. Dashboard/reporting
9. FB Lead Ads + Google Business integrations