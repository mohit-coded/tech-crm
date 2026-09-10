<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Funnel;
use App\Models\Opportunity;
use App\Models\Pipeline;
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
