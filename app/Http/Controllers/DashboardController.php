<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private const VALID_RANGES = ['7d', '30d', '90d', 'all'];

    public function index(Request $request): View
    {
        $range = $request->query('range');
        if (! in_array($range, self::VALID_RANGES, true)) {
            $range = '30d';
        }

        $rangeStart = match ($range) {
            '7d' => now()->subDays(7),
            '90d' => now()->subDays(90),
            'all' => null,
            default => now()->subDays(30),
        };

        // Opportunity is BelongsToLocation-scoped, so these queries are
        // already filtered to the acting user's current_location_id
        // without any manual location_id filtering here.

        // Open-opportunity count and pipeline value deliberately stay as
        // "current state" snapshots regardless of the selected range,
        // rather than being scoped to opportunities.created_at like the
        // rest of this page. They answer "what's in my pipeline right
        // now" — an open opportunity created outside the selected range
        // is still real, unclosed work sitting in the pipeline today, and
        // hiding it just because it's older than the range would make
        // these two cards misleading rather than more precise. Everything
        // else below this (conversion rate, stage breakdown, recent
        // opportunities) is inherently about activity *within* a window,
        // so those are range-scoped.
        $openOpportunities = Opportunity::where('status', 'open');
        $openCount = (clone $openOpportunities)->count();
        $pipelineValue = (clone $openOpportunities)->sum('monetary_value');

        $rangedOpportunities = Opportunity::when(
            $rangeStart !== null,
            fn ($query) => $query->where('opportunities.created_at', '>=', $rangeStart)
        );

        $totalInRange = (clone $rangedOpportunities)->count();
        $wonInRange = (clone $rangedOpportunities)->where('status', 'won')->count();
        $conversionRate = $totalInRange > 0
            ? round(($wonInRange / $totalInRange) * 100, 1)
            : 0;

        $stageBreakdown = (clone $rangedOpportunities)
            ->where('status', 'open')
            ->join('pipeline_stages', 'pipeline_stages.id', '=', 'opportunities.pipeline_stage_id')
            ->selectRaw('pipeline_stages.id as stage_id, pipeline_stages.name as stage_name, count(*) as opportunities_count')
            ->groupBy('pipeline_stages.id', 'pipeline_stages.name')
            ->orderByDesc('opportunities_count')
            ->get();

        $recentOpportunities = (clone $rangedOpportunities)
            ->with(['contact', 'stage'])
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard', [
            'range' => $range,
            'openCount' => $openCount,
            'pipelineValue' => $pipelineValue,
            'totalInRange' => $totalInRange,
            'wonInRange' => $wonInRange,
            'conversionRate' => $conversionRate,
            'stageBreakdown' => $stageBreakdown,
            'recentOpportunities' => $recentOpportunities,
        ]);
    }
}
