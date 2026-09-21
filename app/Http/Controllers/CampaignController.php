<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\PipelineStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CampaignController extends Controller
{
    /**
     * Campaign is BelongsToLocation-scoped, so every query below (including
     * route-model-bound $campaign params) is already filtered to the acting
     * user's current_location_id — no manual location_id filtering needed.
     */
    public function index(): View
    {
        $campaigns = Campaign::withCount('steps')->orderBy('name')->paginate(15);

        return view('campaigns.index', ['campaigns' => $campaigns]);
    }

    public function create(): View
    {
        return view('campaigns.create', ['stageNames' => $this->stageNames()]);
    }

    public function store(Request $request): RedirectResponse
    {
        // location_id is deliberately omitted here: BelongsToLocation's
        // creating() hook auto-fills it from Auth::user()->current_location_id,
        // same as CalendarController@store.
        Campaign::create($this->validatedCampaign($request));

        return redirect()->route('campaigns.index')->with('status', __('Campaign created.'));
    }

    public function edit(Campaign $campaign): View
    {
        $campaign->load('steps');

        return view('campaigns.edit', [
            'campaign' => $campaign,
            'stageNames' => $this->stageNames(),
        ]);
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        $campaign->update($this->validatedCampaign($request));

        $steps = $request->validate([
            'steps' => ['array'],
            'steps.*.channel' => ['nullable', 'in:sms,email'],
            'steps.*.body' => ['nullable', 'string', 'max:1600'],
            'steps.*.delay_minutes' => ['nullable', 'integer', 'min:0'],
            'steps.*.remove' => ['nullable', 'boolean'],

            'new_steps' => ['array'],
            'new_steps.*.channel' => ['nullable', 'in:sms,email'],
            'new_steps.*.body' => ['nullable', 'string', 'max:1600'],
            'new_steps.*.delay_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->syncSteps($campaign, $steps['steps'] ?? [], $steps['new_steps'] ?? []);

        return redirect()->route('campaigns.edit', $campaign)->with('status', __('Campaign updated.'));
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $campaign->delete();

        return redirect()->route('campaigns.index')->with('status', __('Campaign deleted.'));
    }

    /**
     * Distinct pipeline stage names across the current location's
     * pipelines, for the trigger_event dropdown. Relies on PipelineStage's
     * whereHas('pipeline') to inherit Pipeline's own BelongsToLocation
     * scope (a global scope applies to the related model's query inside
     * whereHas() the same as anywhere else) — no manual location_id
     * filtering needed here, same as the rest of this controller.
     *
     * @return Collection<int, string>
     */
    private function stageNames(): Collection
    {
        return PipelineStage::whereHas('pipeline')->distinct()->orderBy('name')->pluck('name');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedCampaign(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger_event' => ['required', 'string', 'max:255'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    /**
     * Applies inline add/update/remove edits to a campaign's steps,
     * submitted together with the campaign update from a single form on
     * the edit page (see campaigns/edit.blade.php) — same pattern as
     * CalendarController::syncAvailabilityRules(), proven there already.
     *
     * Existing rows come in keyed by step id (`steps[{id}][...]`) and are
     * resolved via $campaign->steps()->find($stepId) rather than a bare
     * CampaignStep::find() — CampaignStep has no location_id of its own
     * (see the tenant-check note on the model), so scoping the lookup
     * through the already tenant-verified $campaign's own relation is
     * what keeps a foreign/stale step id from being touched; it simply
     * won't be found and is silently skipped.
     *
     * New rows come in as a flat, unkeyed list (`new_steps[]`) rendered as
     * a handful of blank rows on the edit page; any row left without a
     * body is ignored rather than saved.
     */
    private function syncSteps(Campaign $campaign, array $existingSteps, array $newSteps): void
    {
        foreach ($existingSteps as $stepId => $data) {
            $step = $campaign->steps()->find($stepId);

            if (! $step) {
                continue;
            }

            if (! empty($data['remove'])) {
                $step->delete();

                continue;
            }

            if (blank($data['body'] ?? null)) {
                continue;
            }

            $step->update([
                'channel' => $data['channel'] ?? 'sms',
                'body' => $data['body'],
                'delay_minutes' => $data['delay_minutes'] ?? 0,
            ]);
        }

        $nextPosition = ($campaign->steps()->max('position') ?? -1) + 1;

        foreach ($newSteps as $data) {
            if (blank($data['body'] ?? null)) {
                continue;
            }

            $campaign->steps()->create([
                'position' => $nextPosition++,
                'channel' => $data['channel'] ?? 'sms',
                'body' => $data['body'],
                'delay_minutes' => $data['delay_minutes'] ?? 0,
            ]);
        }
    }
}
