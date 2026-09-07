<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        // Opportunity is BelongsToLocation-scoped, so these queries are
        // already filtered to the acting user's current_location_id
        // without any manual location_id filtering here.
        $openOpportunities = Opportunity::where('status', 'open');

        $openCount = (clone $openOpportunities)->count();
        $pipelineValue = (clone $openOpportunities)->sum('monetary_value');

        $stageBreakdown = (clone $openOpportunities)
            ->join('pipeline_stages', 'pipeline_stages.id', '=', 'opportunities.pipeline_stage_id')
            ->selectRaw('pipeline_stages.id as stage_id, pipeline_stages.name as stage_name, count(*) as opportunities_count')
            ->groupBy('pipeline_stages.id', 'pipeline_stages.name')
            ->orderByDesc('opportunities_count')
            ->get();

        $recentOpportunities = Opportunity::with(['contact', 'stage'])
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard', [
            'openCount' => $openCount,
            'pipelineValue' => $pipelineValue,
            'stageBreakdown' => $stageBreakdown,
            'recentOpportunities' => $recentOpportunities,
        ]);
    }
}
