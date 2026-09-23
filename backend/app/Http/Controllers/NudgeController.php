<?php

namespace App\Http\Controllers;

use App\Models\Nudge;
use App\Quotes\FollowUps\Nudges;
use App\Quotes\NudgeStatus;
use App\Quotes\QuotePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The Follow-ups inbox: drafts waiting for a tap, and what was done lately. */
class NudgeController extends Controller
{
    public function __construct(
        private readonly Nudges $nudges,
        private readonly QuotePresenter $presenter,
    ) {}

    public function index(): JsonResponse
    {
        $pending = Nudge::with('quote.customer')->where('status', NudgeStatus::Drafted)->oldest()->get();
        $recent = Nudge::with('quote.customer')
            ->where('status', '!=', NudgeStatus::Drafted)
            ->where('updated_at', '>=', now()->subDays(14))
            ->latest('updated_at')
            ->limit(20)
            ->get();

        return response()->json([
            'pending' => $pending->map(fn (Nudge $n) => $this->row($n))->all(),
            'recent' => $recent->map(fn (Nudge $n) => $this->row($n))->all(),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);

        return response()->json($this->row($this->nudges->edit(Nudge::findOrFail($id), $data['body'])));
    }

    public function send(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['body' => ['nullable', 'string', 'max:1000']]);
        $nudge = Nudge::findOrFail($id);
        $message = $this->nudges->send($nudge, $request->user(), $data['body'] ?? null);

        return response()->json(['nudge' => $this->row($nudge->fresh('quote.customer')), 'message' => $message->toApi()]);
    }

    public function dismiss(Request $request, int $id): JsonResponse
    {
        $nudge = Nudge::findOrFail($id);
        $this->nudges->dismiss($nudge, $request->user());

        return response()->json($this->row($nudge->fresh('quote.customer')));
    }

    private function row(Nudge $nudge): array
    {
        return [
            ...$nudge->toApi(),
            'quote' => $this->presenter->summary($nudge->quote),
            'customerLocale' => $nudge->quote->customerLocale(),
        ];
    }
}
