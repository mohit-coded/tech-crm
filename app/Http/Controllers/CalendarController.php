<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    /**
     * Calendar is BelongsToLocation-scoped, so every query below (including
     * route-model-bound $calendar params) is already filtered to the acting
     * user's current_location_id — no manual location_id filtering needed.
     */
    public function index(): View
    {
        $calendars = Calendar::withCount('availabilityRules')->orderBy('name')->paginate(15);

        return view('calendars.index', ['calendars' => $calendars]);
    }

    public function create(): View
    {
        return view('calendars.create');
    }

    public function store(Request $request): RedirectResponse
    {
        // location_id is deliberately omitted here: BelongsToLocation's
        // creating() hook auto-fills it from Auth::user()->current_location_id,
        // same as ContactController@store / FunnelController@store.
        Calendar::create($this->validatedCalendar($request));

        return redirect()->route('calendars.index')->with('status', __('Calendar created.'));
    }

    public function edit(Calendar $calendar): View
    {
        $calendar->load('availabilityRules');

        return view('calendars.edit', ['calendar' => $calendar]);
    }

    public function update(Request $request, Calendar $calendar): RedirectResponse
    {
        $calendar->update($this->validatedCalendar($request));

        $rules = $request->validate([
            'rules' => ['array'],
            'rules.*.day_of_week' => ['nullable', 'integer', 'between:0,6'],
            'rules.*.start_time' => ['nullable', 'date_format:H:i'],
            'rules.*.end_time' => ['nullable', 'date_format:H:i'],
            'rules.*.remove' => ['nullable', 'boolean'],

            'new_rules' => ['array'],
            'new_rules.*.day_of_week' => ['nullable', 'integer', 'between:0,6'],
            'new_rules.*.start_time' => ['nullable', 'date_format:H:i'],
            'new_rules.*.end_time' => ['nullable', 'date_format:H:i'],
        ]);

        $this->syncAvailabilityRules($calendar, $rules['rules'] ?? [], $rules['new_rules'] ?? []);

        return redirect()->route('calendars.edit', $calendar)->with('status', __('Calendar updated.'));
    }

    public function destroy(Calendar $calendar): RedirectResponse
    {
        $calendar->delete();

        return redirect()->route('calendars.index')->with('status', __('Calendar deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedCalendar(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'timezone' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    /**
     * Applies inline add/update/remove edits to a calendar's availability
     * rules, submitted together with the calendar update from a single
     * form on the edit page (see calendars/edit.blade.php).
     *
     * Existing rows come in keyed by rule id (`rules[{id}][...]`) and are
     * resolved via $calendar->availabilityRules()->find($ruleId) rather
     * than AvailabilityRule::find() — AvailabilityRule has no location_id
     * of its own (see the tenant-check note on the model), so scoping the
     * lookup through the already tenant-verified $calendar's own relation
     * is what keeps a foreign/stale rule id from being touched; it simply
     * won't be found and is silently skipped.
     *
     * New rows come in as a flat, unkeyed list (`new_rules[]`) rendered as
     * a handful of blank rows on the edit page; any row left fully blank
     * (no day/start/end filled in) is ignored rather than saved.
     */
    private function syncAvailabilityRules(Calendar $calendar, array $existingRules, array $newRules): void
    {
        foreach ($existingRules as $ruleId => $data) {
            $rule = $calendar->availabilityRules()->find($ruleId);

            if (! $rule) {
                continue;
            }

            if (! empty($data['remove'])) {
                $rule->delete();

                continue;
            }

            if (blank($data['day_of_week'] ?? null) || blank($data['start_time'] ?? null) || blank($data['end_time'] ?? null)) {
                continue;
            }

            $rule->update([
                'day_of_week' => $data['day_of_week'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
            ]);
        }

        foreach ($newRules as $data) {
            if (blank($data['day_of_week'] ?? null) || blank($data['start_time'] ?? null) || blank($data['end_time'] ?? null)) {
                continue;
            }

            $calendar->availabilityRules()->create([
                'day_of_week' => $data['day_of_week'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
            ]);
        }
    }
}
