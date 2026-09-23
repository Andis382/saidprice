<?php

namespace App\Http\Controllers;

use App\Models\Nudge;
use App\Models\Quote;
use App\Quotes\NudgeStatus;
use App\Quotes\QuoteStats;
use App\Quotes\QuoteStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function show(Request $request, QuoteStats $stats): JsonResponse
    {
        $weeks = max(4, min(26, (int) $request->query('weeks', 12)));

        return response()->json($stats->weekly($request->user()->organization->timezone, now(), $weeks));
    }

    /** Small numbers for the menu badges and the pipeline header. */
    public function overview(Request $request, QuoteStats $stats): JsonResponse
    {
        return response()->json([
            'followUps' => Nudge::where('status', NudgeStatus::Drafted)->count(),
            'drafts' => Quote::where('status', QuoteStatus::Draft)->count(),
            'thisWeek' => $stats->weekly($request->user()->organization->timezone, now(), 1)['thisWeek'],
        ]);
    }
}
