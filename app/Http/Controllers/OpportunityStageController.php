<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OpportunityStageController extends Controller
{
    public function update(Request $request, Opportunity $opportunity): JsonResponse
    {
        $validated = $request->validate([
            'pipeline_stage_id' => ['required', 'integer', 'exists:pipeline_stages,id'],
        ]);

        $stage = PipelineStage::findOrFail($validated['pipeline_stage_id']);

        // Pipeline is BelongsToLocation, so a plain $stage->pipeline lookup
        // would be silently filtered to null for a cross-tenant stage
        // (via the global scope) instead of letting us 403 on it — bypass
        // the scope here so the tenant check itself is trustworthy.
        $stagePipelineLocationId = Pipeline::withoutGlobalScopes()
            ->whereKey($stage->pipeline_id)
            ->value('location_id');

        abort_unless(
            $stagePipelineLocationId === Auth::user()->current_location_id,
            403
        );

        $opportunity->moveToStage($stage);

        return response()->json($opportunity->fresh(['stage']));
    }
}
