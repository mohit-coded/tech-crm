<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Closes a deal (won/lost). Deliberately separate from
 * OpportunityStageController: stage and status are independent, so this
 * never touches pipeline_stage_id, and it needs none of the PipelineStage
 * cross-tenant check that controller exists for — there's no foreign key
 * to another tenant-scoped table here, and $opportunity is already
 * tenant-verified by route-model binding through BelongsToLocation.
 */
class OpportunityStatusController extends Controller
{
    public function update(Request $request, Opportunity $opportunity): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['won', 'lost'])],
        ]);

        $opportunity->update(['status' => $validated['status']]);

        return response()->json($opportunity->fresh());
    }
}
