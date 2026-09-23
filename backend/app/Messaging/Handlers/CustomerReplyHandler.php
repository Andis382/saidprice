<?php

namespace App\Messaging\Handlers;

use App\Messaging\InboundHandler;
use App\Models\InboundMessage;
use App\Models\Quote;
use App\Models\QuoteEvent;
use App\Quotes\QuoteStatus;
use App\Quotes\QuoteWorkflow;
use App\Support\Tenant;

/**
 * A customer answers the quote message on WhatsApp: "po", "yes" or "1" accepts their latest
 * open quote, "jo", "no" or "2" declines it, anything else is a question on their latest
 * quote and goes straight to the business phone.
 */
class CustomerReplyHandler implements InboundHandler
{
    private const YES = ['po', 'yes', '1'];

    private const NO = ['jo', 'no', '2'];

    public function __construct(private readonly QuoteWorkflow $workflow) {}

    public function handle(InboundMessage $message): bool
    {
        $text = trim((string) $message->body);
        if ($message->kind !== 'text' || $text === '') {
            return false;
        }
        $quotes = Quote::withoutGlobalScope('organization')
            ->with(['customer' => fn ($q) => $q->withoutGlobalScope('organization'), 'organization'])
            ->whereHas('customer', fn ($q) => $q->withoutGlobalScope('organization')->where('phone', $message->from_phone))
            ->where('status', '!=', QuoteStatus::Draft)
            ->orderByDesc('sent_at')
            ->limit(20)
            ->get();
        if ($quotes->isEmpty()) {
            return false;
        }
        $open = $quotes->first(fn (Quote $q) => $q->status->isAnswerable());
        $target = $open ?? $quotes->first();
        $word = preg_replace('/[^\p{L}\p{N}]/u', '', mb_strtolower($text));

        return Tenant::run($target->organization_id, function () use ($message, $open, $target, $word, $text) {
            if ($open && in_array($word, self::YES, true)) {
                $this->workflow->accept($open, QuoteEvent::VIA_WHATSAPP);
                $message->markHandled('quote_accept', $open->organization_id, 'quote', $open->id);
            } elseif ($open && in_array($word, self::NO, true)) {
                $this->workflow->decline($open, QuoteEvent::VIA_WHATSAPP);
                $message->markHandled('quote_decline', $open->organization_id, 'quote', $open->id);
            } else {
                $this->workflow->question($target, QuoteEvent::VIA_WHATSAPP, $text);
                $message->markHandled('quote_question', $target->organization_id, 'quote', $target->id);
            }

            return true;
        });
    }
}
