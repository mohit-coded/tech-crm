<?php

namespace App\Http\Controllers;

use App\Models\Pipeline;
use Illuminate\Contracts\View\View;

class OpportunityBoardController extends Controller
{
    public function index(): View
    {
        // Pipeline is BelongsToLocation-scoped, so this is already
        // filtered to the acting user's current_location_id.
        $pipeline = Pipeline::where('is_default', true)->first()
            ?? Pipeline::first();

        $stages = $pipeline
            ? $pipeline->stages()
                ->with(['opportunities' => fn ($query) => $query->where('status', 'open')->with('contact')])
                ->get()
            : collect();

        return view('opportunities.board', [
            'pipeline' => $pipeline,
            'stages' => $stages,
        ]);
    }
}
