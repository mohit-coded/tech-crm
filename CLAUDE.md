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

## Build order (Phase 1 complete: 1a multi-tenancy foundation + 1b auth wiring/multi-location membership.)
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
  `current_location_id`.Fun, fun, No, no, no, no, oh, watch it, watch it, Oh my God, Arjun, you know what you did? It's all for You Please I'm very serious. Please marry me, baby. We are the first to get I think around now, now, so a lot of people are thinking This I think Yeah, Hey, hey, hey, Shiva.
Oh.
Hey, I'm about to do phone call.
Hey, I'm gonna do phone call.
Hey, I'm gonna do phone call.
Hey, I'm gonna do phone call.
Hey, I'm gonna do phone call.
You Phone, if it I I Bye, Hey, hey, hey, hey, hey, hey, hey, hey. Bye, bye, see you, bye, bye, bye, bye, Hey, Next call, call down. It is number They, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, Oh, go, go, go, go, go, go, Oh, and the wallet, it Oh, and the wallet, they have to do it like this, right I am a I I am a man Yeah, in the... in the... In the air, but I The Kalapuzhya thinnest, which you think I like.
You are thinnest.
I am not that.Fun, fun, No, no, no, no, oh, watch it, watch it, Oh my God, Arjun, you know what you did? It's all for You Please I'm very serious. Please marry me, baby. We are the first to get I think around now, now, so a lot of people are thinking This I think Yeah, Hey, hey, hey, Shiva.
Oh.
Hey, I'm about to do phone call.
Hey, I'm gonna do phone call.
Hey, I'm gonna do phone call.
Hey, I'm gonna do phone call.
Hey, I'm gonna do phone call.
You Phone, if it I I Bye, Hey, hey, hey, hey, hey, hey, hey, hey. Bye, bye, see you, bye, bye, bye, bye, Hey, Next call, call down. It is number They, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, Oh, go, go, go, go, go, go, Oh, and the wallet, it Oh, and the wallet, they have to do it like this, right I am a I I am a man Yeah, in the... in the... In the air, but I Yaar thinna? Ahmaaa, idan japdiyil.Fun, fun, No, no, no, no, oh, watch it, watch it, Oh my God, Arjun, you know what you did? It's all for You Please I'm very serious. Please marry me, baby. We are the first to get I think around now, now, so a lot of people are thinking This I think Yeah, Hey, hey, hey, Shiva.
Oh.
Hey, I'm about to do phone call.
Hey, I'm gonna do phone call.
Hey, I'm gonna do phone call.
Hey, I'm gonna do phone call.
Hey, I'm gonna do phone call.
You Phone, if it I I Bye, Hey, hey, hey, hey, hey, hey, hey, hey. Bye, bye, see you, bye, bye, bye, bye, Hey, Next call, call down. It is number They, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, Oh, go, go, go, go, go, go, Oh, and the wallet, it Oh, and the wallet, they have to do it like this, right I am a I I am a man Yeah, in the... in the... In the air, but I Ah... Ah, ma... Ah, gan japtigul.Fun, fun, No, no, no, no, oh, watch it, watch it, Oh my God, Arjun, you know what you did? It's all for You Please I'm very serious. Please marry me, baby. We are the first to get I think around now, now, so a lot of people are thinking This I think Yeah, Hey, hey, hey, Shiva.
Oh.
Hey, I'm about to do phone call.
Hey, I'm gonna do phone call.
Fun, fun, No, no, no, no, oh, watch it, watch it, Oh my God, Arjun, you know what you did? It's all for You Please I'm very serious. Please marry me, baby. We are the first to get I think around now, now, so a lot of people are thinking This I think Yeah, Hey, hey, hey, Shiva.
Oh.
Hey, I'm about to do phone call.
Hey, I'm gonna do phone call.
Hey, I'm gonna do phone call.
Hey, I'm gonna do phone call.
Hey, I'm gonna do phone call.
You Phone, if it I I Bye, Hey, hey, hey, hey, hey, hey, hey, hey. Bye, bye, see you, bye, bye, bye, bye, Hey, Next call, call down. It is number They, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, the, Oh, go, go, go, go, go, go, Oh, and the wallet, it Oh, and the wallet, they have to do it like this, right I am a I I am a man Yeah, in the... in the... In the air, but I Wallet. It. Oh, and wallet. They have to do it like this, right? I'm, I'm a Bam boom, I don't I Just answer After pasting Mavi, Mavi mavii, La la la la la la la, I I am a I I see that my leg, I need to know my leg, but I am a little bit of a numb leg, but I am a little bit of a numb leg, God.