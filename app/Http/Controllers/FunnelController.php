<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\Funnel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FunnelController extends Controller
{
    /**
     * Funnel is BelongsToLocation-scoped, so every query below (including
     * route-model-bound $funnel params) is already filtered to the acting
     * user's current_location_id — no manual location_id filtering needed.
     */
    public function index(): View
    {
        $funnels = Funnel::orderBy('name')->paginate(15);

        return view('funnels.index', ['funnels' => $funnels]);
    }

    public function create(): View
    {
        return view('funnels.create', ['calendars' => Calendar::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        // location_id is deliberately omitted here: BelongsToLocation's
        // creating() hook auto-fills it from Auth::user()->current_location_id,
        // same as ContactController@store.
        Funnel::create($validated);

        return redirect()->route('funnels.index')->with('status', __('Funnel created.'));
    }

    public function edit(Funnel $funnel): View
    {
        return view('funnels.edit', [
            'funnel' => $funnel,
            'calendars' => Calendar::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Funnel $funnel): RedirectResponse
    {
        $funnel->update($this->validated($request, $funnel));

        return redirect()->route('funnels.index')->with('status', __('Funnel updated.'));
    }

    public function destroy(Funnel $funnel): RedirectResponse
    {
        $funnel->delete();

        return redirect()->route('funnels.index')->with('status', __('Funnel deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Funnel $funnel = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'alpha_dash',
                'unique:funnels,slug'.($funnel ? ','.$funnel->id : ''),
            ],
            'headline' => ['required', 'string', 'max:255'],
            'subheadline' => ['nullable', 'string', 'max:255'],
            'button_text' => ['required', 'string', 'max:255'],
            // Plain 'exists:calendars,id' would query the calendars table
            // directly, bypassing BelongsToLocation's global scope entirely
            // (the exists rule isn't Eloquent-aware) — that would let a
            // cross-tenant calendar_id validate as "existing". Scoping the
            // exists check to the acting user's own location_id here is
            // what actually keeps this tenant-safe.
            'calendar_id' => [
                'nullable',
                Rule::exists('calendars', 'id')->where(
                    fn ($query) => $query->where('location_id', Auth::user()->current_location_id)
                ),
            ],
        ]);

        $validated['is_published'] = $request->boolean('is_published');

        return $validated;
    }
}
