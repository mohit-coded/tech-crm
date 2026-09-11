<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Funnel;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Services\AvailabilitySlotCalculator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FunnelPublicController extends Controller
{
    public function show(string $slug): View
    {
        $funnel = $this->publishedFunnel($slug);

        return view('funnels.public', ['funnel' => $funnel]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $funnel = $this->publishedFunnel($slug);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated, $funnel) {
            // No authenticated user on a public route, so BelongsToLocation's
            // creating() auto-fill has nothing to key off — location_id must
            // be set explicitly here, same reasoning as registration's
            // default Pipeline (see RegisteredUserController@store).
            $contact = Contact::create([
                'location_id' => $funnel->location_id,
                'first_name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
            ]);

            // Pipeline is BelongsToLocation-scoped, but that scope only
            // activates for an authenticated user — on this public route
            // Auth::user() is null, so it's inert. The location_id filter
            // here is what actually keeps this tenant-safe.
            $pipeline = Pipeline::where('location_id', $funnel->location_id)
                ->where('is_default', true)
                ->first()
                ?? Pipeline::where('location_id', $funnel->location_id)->first();

            $stage = $pipeline?->stages()->first();

            abort_if(! $pipeline || ! $stage, 500, 'Funnel location has no pipeline to receive leads.');

            Opportunity::create([
                'location_id' => $funnel->location_id,
                'contact_id' => $contact->id,
                'pipeline_id' => $pipeline->id,
                'pipeline_stage_id' => $stage->id,
                'name' => $validated['name'],
                'status' => 'open',
                'source' => $funnel->name,
            ]);
        });

        return redirect()->route('funnels.public.show', $funnel->slug)->with('submitted', true);
    }

    /**
     * Date-picker + slot-list step. Selecting a date is a plain GET with
     * ?date=..., re-rendering this same page with the slot list — chosen
     * over a JS/fetch() JSON endpoint (the Kanban-board pattern) because
     * it needs no new endpoint, no Alpine/fetch glue, and is trivially
     * testable with a plain HTTP GET; a full page round-trip per date
     * pick is an acceptable v1 trade-off for a foundational booking flow.
     */
    public function book(Request $request, string $slug): View
    {
        $funnel = $this->publishedFunnel($slug);

        if (! $funnel->calendar_id || ! $funnel->calendar) {
            return view('funnels.book-unavailable', ['funnel' => $funnel]);
        }

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $date = isset($validated['date']) ? Carbon::parse($validated['date']) : null;

        $slots = $date
            ? (new AvailabilitySlotCalculator())->getAvailableSlots($funnel->calendar, $date)
            : [];

        return view('funnels.book', [
            'funnel' => $funnel,
            'date' => $date,
            'slots' => $slots,
        ]);
    }

    public function confirmBooking(Request $request, string $slug): RedirectResponse
    {
        $funnel = $this->publishedFunnel($slug);

        abort_if(! $funnel->calendar_id || ! $funnel->calendar, 404);

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
        ]);

        $calendar = $funnel->calendar;
        $date = Carbon::parse($validated['date']);

        // CRITICAL: re-run the calculator right before booking, not just
        // when the page was first rendered — someone else may have taken
        // this slot in the meantime. If the submitted time isn't in the
        // freshly computed list any more, reject rather than double-book.
        $availableSlots = (new AvailabilitySlotCalculator())->getAvailableSlots($calendar, $date);

        $matchingSlot = collect($availableSlots)->first(
            fn (array $slot) => $slot['start']->format('H:i') === $validated['start_time']
        );

        if (! $matchingSlot) {
            return redirect()
                ->route('funnels.public.book', ['slug' => $funnel->slug, 'date' => $validated['date']])
                ->withErrors(['start_time' => __('Sorry, that time was just booked. Please choose another.')])
                ->withInput();
        }

        // The calculator returns slots in the calendar's resolved timezone
        // (calendar -> location -> UTC) — convert to UTC explicitly before
        // storing, since appointments.starts_at/ends_at are always UTC
        // (see the Appointment model) and Eloquent's datetime cast stores
        // whatever timezone the Carbon instance currently holds, it does
        // not convert for you.
        $startsAt = $matchingSlot['start']->clone()->setTimezone('UTC');
        $endsAt = $matchingSlot['end']->clone()->setTimezone('UTC');

        DB::transaction(function () use ($validated, $funnel, $startsAt, $endsAt) {
            // Fresh lookup by email (scoped to the funnel's own location)
            // rather than trusting a client-supplied contact id (which
            // would need the same re-verification effort anyway) or
            // session state (which wouldn't survive the visitor leaving
            // and coming back to book later) — this is simplest-correct
            // and naturally tenant-safe, since we supply location_id
            // ourselves rather than accepting it from the request.
            $contact = Contact::where('location_id', $funnel->location_id)
                ->where('email', $validated['email'])
                ->first();

            if ($contact) {
                $contact->update([
                    'first_name' => $validated['name'],
                    'phone' => $validated['phone'],
                ]);
            } else {
                $contact = Contact::create([
                    'location_id' => $funnel->location_id,
                    'first_name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'],
                ]);
            }

            Appointment::create([
                'location_id' => $funnel->location_id,
                'calendar_id' => $funnel->calendar_id,
                'contact_id' => $contact->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => 'requested',
            ]);

            // If the lead already has an Opportunity (created in store()
            // when they first submitted the funnel) and its pipeline has
            // a "Booking Requested" stage (the exact name DatabaseSeeder
            // and RegisteredUserController both seed), move it there via
            // moveToStage() — the only sanctioned way to change stage, so
            // OpportunityStageChanged still fires.
            $opportunity = Opportunity::where('location_id', $funnel->location_id)
                ->where('contact_id', $contact->id)
                ->latest()
                ->first();

            if ($opportunity) {
                $bookingRequestedStage = $opportunity->pipeline
                    ->stages()
                    ->where('name', 'Booking Requested')
                    ->first();

                if ($bookingRequestedStage) {
                    $opportunity->moveToStage($bookingRequestedStage);
                }
            }
        });

        return redirect()->route('funnels.public.book.confirmed', $funnel->slug);
    }

    public function bookingConfirmed(string $slug): View
    {
        $funnel = $this->publishedFunnel($slug);

        return view('funnels.book-confirmed', ['funnel' => $funnel]);
    }

    /**
     * Resolves a funnel by slug for anonymous visitors — bypasses the
     * BelongsToLocation scope explicitly (there's no authenticated user
     * for it to key off anyway) and 404s on unpublished/nonexistent slugs
     * alike, so an unpublished funnel's existence is never leaked.
     */
    private function publishedFunnel(string $slug): Funnel
    {
        return Funnel::withoutGlobalScopes()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();
    }
}
