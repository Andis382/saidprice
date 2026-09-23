<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Models\QuoteEvent;
use App\Quotes\QuotePdf;
use App\Quotes\QuotePresenter;
use App\Quotes\QuoteStatus;
use App\Quotes\QuoteWorkflow;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** The customer's side of the WhatsApp link: read, accept, decline, ask. No login. */
class PublicQuoteController extends Controller
{
    public function __construct(
        private readonly QuoteWorkflow $workflow,
        private readonly QuotePresenter $presenter,
    ) {}

    public function show(Request $request, string $token): JsonResponse
    {
        $quote = $this->find($token);
        // The business checking its own link does not count as the customer opening it.
        if ($request->user()?->organization_id !== $quote->organization_id) {
            Tenant::run($quote->organization_id, fn () => $this->workflow->viewed($quote));
        }

        return response()->json($this->presenter->forCustomer($quote));
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $quote = $this->find($token);
        Tenant::run($quote->organization_id, fn () => $this->workflow->accept($quote, QuoteEvent::VIA_LINK, $data['note'] ?? null));

        return response()->json($this->presenter->forCustomer($quote->fresh()));
    }

    public function decline(Request $request, string $token): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $quote = $this->find($token);
        Tenant::run($quote->organization_id, fn () => $this->workflow->decline($quote, QuoteEvent::VIA_LINK, $data['reason'] ?? null));

        return response()->json($this->presenter->forCustomer($quote->fresh()));
    }

    public function question(Request $request, string $token): Response
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:1000']]);
        $quote = $this->find($token);
        Tenant::run($quote->organization_id, fn () => $this->workflow->question($quote, QuoteEvent::VIA_LINK, trim($data['text'])));

        return response()->noContent();
    }

    public function pdf(string $token, QuotePdf $pdf): Response
    {
        return $pdf->download($this->find($token));
    }

    /** Drafts have a token from the start but are not shown until sent. */
    private function find(string $token): Quote
    {
        $quote = Quote::withoutGlobalScope('organization')
            ->with(['items', 'customer' => fn ($q) => $q->withoutGlobalScope('organization'), 'organization'])
            ->where('public_token', $token)
            ->first();
        abort_if(! $quote || $quote->status === QuoteStatus::Draft, 404, __('errors.not_found'));

        return $quote;
    }
}
