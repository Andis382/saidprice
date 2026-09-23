<?php

namespace App\Quotes;

use App\Models\BusinessSettings;
use App\Models\OutboundMessage;
use App\Models\Quote;
use App\Models\QuoteItem;
use Carbon\CarbonImmutable;

/** Quotes as JSON for the app (board card, full detail) and for the customer's page. */
class QuotePresenter
{
    /** Board cards and lists. Uses pending_nudge / last_question_at when the query selected them. */
    public function summary(Quote $quote): array
    {
        return [
            'id' => $quote->id,
            'number' => $quote->number,
            'title' => $quote->displayTitle(),
            'status' => $quote->status->value,
            'outcome' => $quote->outcome?->value,
            'source' => $quote->source,
            'totalCents' => $quote->total_cents,
            'customer' => $quote->customer ? [
                'id' => $quote->customer->id,
                'name' => $quote->customer->name,
                'phone' => $quote->customer->phone,
            ] : null,
            'proposedFor' => $quote->proposed_for,
            'validUntil' => $quote->valid_until?->toDateString(),
            'sentAt' => $quote->sent_at?->toIso8601String(),
            'viewedAt' => $quote->viewed_at?->toIso8601String(),
            'respondedAt' => $quote->responded_at?->toIso8601String(),
            'createdAt' => $quote->created_at?->toIso8601String(),
            'updatedAt' => $quote->updated_at?->toIso8601String(),
            'nudgeCount' => $quote->nudge_count,
            'hasVoice' => $quote->voice_file_id !== null || $quote->transcript !== null,
            'pendingNudge' => (bool) ($quote->pending_nudge ?? false),
            'hasQuestion' => $quote->last_question_at !== null && $quote->status->isSent()
                && ($quote->responded_at === null || $quote->responded_at->lessThan($quote->last_question_at)),
        ];
    }

    /** @param array{action: string, at: ?CarbonImmutable}|null $followUp */
    public function detail(Quote $quote, ?array $followUp = null): array
    {
        $quote->loadMissing(['items', 'customer', 'events.user', 'nudges', 'voiceFile', 'organization']);
        $created = $quote->events->first(fn ($e) => $e->type === EventType::Created);
        $lastMessage = OutboundMessage::where('related_type', 'quote')->where('related_id', $quote->id)->latest('id')->first();
        $status = $quote->status;

        return [
            ...$this->summary($quote),
            'customer' => $quote->customer?->toApi(),
            'items' => $quote->items->map(fn (QuoteItem $i) => $i->toApi())->all(),
            'totals' => $this->totals($quote),
            'validityDays' => $quote->validity_days,
            'notes' => $quote->notes,
            'terms' => $quote->terms,
            'transcript' => $quote->transcript,
            'voice' => $quote->voiceFile ? [
                'url' => $quote->voiceFile->url(),
                'contentType' => $quote->voiceFile->content_type,
            ] : null,
            'responseNote' => $quote->response_note,
            'lostReason' => $quote->lost_reason,
            'outcomeAt' => $quote->outcome_at?->toIso8601String(),
            'lastNudgedAt' => $quote->last_nudged_at?->toIso8601String(),
            'publicUrl' => $quote->publicUrl(),
            'events' => $quote->events->map->toApi()->all(),
            'nudges' => $quote->nudges->map->toApi()->all(),
            'capture' => [
                'engine' => $created?->meta['engine'] ?? null,
                'currencySaid' => $created?->meta['currencySaid'] ?? null,
                'duplicatedFrom' => $created?->meta['from'] ?? null,
            ],
            'nextFollowUpAt' => isset($followUp['at']) ? $followUp['at']->toIso8601String() : null,
            'lastMessage' => $lastMessage ? [
                'id' => $lastMessage->id,
                'status' => $lastMessage->status,
                'templateKey' => $lastMessage->template_key,
                'waMeUrl' => $lastMessage->waMeUrl(),
                'createdAt' => $lastMessage->created_at?->toIso8601String(),
            ] : null,
            'can' => [
                'edit' => $status->isEditable(),
                'send' => $status === QuoteStatus::Draft,
                'resend' => in_array($status, [QuoteStatus::Sent, QuoteStatus::Viewed, QuoteStatus::Silent, QuoteStatus::Expired], true),
                'outcome' => $status->isSent(),
                'delete' => $status === QuoteStatus::Draft,
            ],
        ];
    }

    /** The customer's view: no internal notes on the timeline, no other customers' data. */
    public function forCustomer(Quote $quote): array
    {
        $quote->loadMissing(['items', 'customer', 'organization']);
        $org = $quote->organization;

        return [
            'number' => $quote->number,
            'title' => $quote->displayTitle(),
            'status' => $quote->status->value,
            'locale' => $quote->customerLocale(),
            'currency' => $org->currency,
            'business' => [
                'name' => $org->name,
                'phone' => $org->phone,
                'trade' => BusinessSettings::for($org)->trade,
            ],
            'customerName' => $quote->customer?->name,
            'items' => $quote->items->map(fn (QuoteItem $i) => $i->toApi())->all(),
            'totals' => $this->totals($quote),
            'totalCents' => $quote->total_cents,
            'proposedFor' => $quote->proposed_for,
            'validUntil' => $quote->valid_until?->toDateString(),
            'notes' => $quote->notes,
            'terms' => $quote->terms,
            'sentAt' => $quote->sent_at?->toIso8601String(),
            'respondedAt' => $quote->responded_at?->toIso8601String(),
            'responseNote' => $quote->response_note,
            'canAnswer' => $quote->status->isAnswerable(),
        ];
    }

    /** @return array{total: int, parts: int, labour: int, other: int} */
    private function totals(Quote $quote): array
    {
        return QuoteTotals::of($quote->items->map(fn (QuoteItem $i) => ['qty' => $i->qty, 'unitPriceCents' => $i->unit_price_cents, 'kind' => $i->kind]));
    }
}
