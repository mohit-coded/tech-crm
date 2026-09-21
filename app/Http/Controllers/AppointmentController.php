<?php

namespace App\Http\Controllers;

use App\Events\AppointmentCompleted;
use App\Models\Appointment;
use App\Models\Opportunity;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    /**
     * Appointment is BelongsToLocation-scoped, so every query below
     * (including the route-model-bound $appointment params on confirm()/
     * cancel()) is already filtered to the acting user's
     * current_location_id — no manual location_id filtering needed.
     */
    public function index(): View
    {
        $appointments = Appointment::with(['contact', 'calendar.location'])
            ->orderBy('starts_at')
            ->paginate(15);

        return view('appointments.index', ['appointments' => $appointments]);
    }

    public function confirm(Appointment $appointment): RedirectResponse
    {
        $appointment->update(['status' => 'confirmed']);

        $this->moveOpportunityToStage($appointment, 'Booking Confirmed');

        return redirect()->route('appointments.index')->with('status', __('Appointment confirmed.'));
    }

    public function cancel(Appointment $appointment): RedirectResponse
    {
        $appointment->update(['status' => 'cancelled']);

        return redirect()->route('appointments.index')->with('status', __('Appointment cancelled.'));
    }

    /**
     * Only a 'confirmed' appointment that isn't already completed can be
     * marked completed — completing one that was never confirmed, was
     * cancelled, or is already completed is a nonsensical transition, so
     * it's rejected (a flashed error, nothing changed) rather than
     * silently allowed or silently ignored.
     */
    public function complete(Appointment $appointment): RedirectResponse
    {
        if ($appointment->status !== 'confirmed' || $appointment->completed_at !== null) {
            return redirect()->route('appointments.index')
                ->with('error', __('Only a confirmed, not-yet-completed appointment can be marked completed.'));
        }

        $appointment->update(['completed_at' => now()]);

        event(new AppointmentCompleted($appointment));

        return redirect()->route('appointments.index')->with('status', __('Appointment marked completed.'));
    }

    /**
     * Mirrors FunnelPublicController@confirmBooking's stage-move logic
     * exactly: find the lead's most recent Opportunity for this
     * appointment's location/contact, find a stage in its pipeline named
     * $stageName, and move it there if one exists — a no-op (not an
     * error) if there's no Opportunity, or its pipeline has no stage
     * with that name.
     */
    private function moveOpportunityToStage(Appointment $appointment, string $stageName): void
    {
        $opportunity = Opportunity::where('location_id', $appointment->location_id)
            ->where('contact_id', $appointment->contact_id)
            ->latest()
            ->first();

        if (! $opportunity) {
            return;
        }

        $stage = $opportunity->pipeline
            ->stages()
            ->where('name', $stageName)
            ->first();

        if ($stage) {
            $opportunity->moveToStage($stage);
        }
    }
}
