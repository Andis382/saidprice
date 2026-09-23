<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Quotes\FollowUps\FollowUps;
use App\Quotes\QuoteCapture;
use App\Quotes\QuoteOutcome;
use App\Quotes\QuotePresenter;
use App\Quotes\QuoteWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Send, send again, duplicate, and say how it ended. */
class QuoteActionController extends Controller
{
    public function __construct(
        private readonly QuoteWorkflow $workflow,
        private readonly QuotePresenter $presenter,
        private readonly FollowUps $followUps,
    ) {}

    public function send(Request $request, int $id): JsonResponse
    {
        $quote = Quote::findOrFail($id);
        $message = $this->workflow->send($quote, $request->user());

        return response()->json(['quote' => $this->detail($quote), 'message' => $message->toApi()]);
    }

    public function resend(Request $request, int $id): JsonResponse
    {
        $quote = Quote::findOrFail($id);
        $message = $this->workflow->resend($quote, $request->user());

        return response()->json(['quote' => $this->detail($quote), 'message' => $message->toApi()]);
    }

    public function duplicate(Request $request, int $id, QuoteCapture $capture): JsonResponse
    {
        $original = Quote::with('items')->findOrFail($id);

        return response()->json($this->presenter->detail($capture->duplicate($original, $request->user())->fresh()), 201);
    }

    public function outcome(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'outcome' => ['required', Rule::enum(QuoteOutcome::class)],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);
        $quote = Quote::findOrFail($id);
        $this->workflow->outcome($quote, QuoteOutcome::from($data['outcome']), $request->user(), $data['reason'] ?? null);

        return response()->json($this->detail($quote));
    }

    private function detail(Quote $quote): array
    {
        $quote = $quote->fresh(['items', 'customer', 'events.user', 'nudges', 'voiceFile', 'organization']);
        $plan = $quote->status->isAwaitingAnswer() ? $this->followUps->plan($quote, $quote->organization, now()) : null;

        return $this->presenter->detail($quote, $plan);
    }
}
