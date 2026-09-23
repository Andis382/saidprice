<?php

namespace App\Quotes;

use App\Messaging\Messenger;
use App\Models\OutboundMessage;
use App\Models\Quote;

/**
 * Every WhatsApp message about a quote: to the customer in their language, and notices to
 * the business phone in the business's language. Notices are skipped when no business
 * phone is set; the timeline in the app still shows what happened.
 */
class QuoteMessages
{
    public function __construct(private readonly Messenger $messenger) {}

    public function quote(Quote $quote): OutboundMessage
    {
        $locale = $quote->customerLocale();

        return $this->toCustomer($quote, 'quote_sent', [
            'business' => $quote->organization->name,
            'number' => $quote->number,
            'title' => $quote->displayTitle(),
            'total' => Phrases::total($quote, $locale),
            'when' => Phrases::when($quote->proposed_for, $locale),
            'valid_until' => Phrases::date($quote->valid_until, $locale),
        ]);
    }

    public function nudge(Quote $quote, string $text): OutboundMessage
    {
        return $this->toCustomer($quote, 'quote_nudge', ['text' => $text]);
    }

    public function thanks(Quote $quote): OutboundMessage
    {
        $locale = $quote->customerLocale();

        return $this->toCustomer($quote, 'quote_thanks', [
            'business' => $quote->organization->name,
            'number' => $quote->number,
            'total' => Phrases::total($quote, $locale),
            'when' => Phrases::when($quote->proposed_for, $locale, 'quotes.when_the_date'),
        ], withLink: false);
    }

    public function accepted(Quote $quote): ?OutboundMessage
    {
        $locale = $quote->organization->locale;

        return $this->toBusiness($quote, 'quote_accepted_notice', [
            'when' => Phrases::when($quote->proposed_for, $locale),
        ]);
    }

    public function declined(Quote $quote, ?string $reason): ?OutboundMessage
    {
        return $this->toBusiness($quote, 'quote_declined_notice', [
            'reason' => $reason ?: trans('quotes.reason_none', [], $quote->organization->locale),
        ]);
    }

    public function question(Quote $quote, string $question): ?OutboundMessage
    {
        return $this->toBusiness($quote, 'quote_question_notice', [
            'question' => $question,
            'reply' => 'https://wa.me/'.$quote->customer?->phone,
        ]);
    }

    public function draftReady(Quote $quote, string $sourceKey): ?OutboundMessage
    {
        $org = $quote->organization;
        if (! $org->phone) {
            return null;
        }

        return $this->messenger->send($org->id, $org->phone, $org->name, 'quote_draft_created', $org->locale, [
            'number' => $quote->number,
            'source' => trans('quotes.source_'.$sourceKey, [], $org->locale),
        ], $quote->appUrl(), 'quote', $quote->id);
    }

    /** @param array<string, string|int|null> $params */
    private function toCustomer(Quote $quote, string $template, array $params, bool $withLink = true): OutboundMessage
    {
        $customer = $quote->customer;

        return $this->messenger->send(
            $quote->organization_id,
            $customer->phone,
            $customer->name,
            $template,
            $quote->customerLocale(),
            ['name' => $customer->greetingName(), ...$params],
            $withLink ? $quote->publicUrl() : null,
            'quote',
            $quote->id,
        );
    }

    /** @param array<string, string|int|null> $params */
    private function toBusiness(Quote $quote, string $template, array $params): ?OutboundMessage
    {
        $org = $quote->organization;
        if (! $org->phone) {
            return null;
        }
        $locale = $org->locale;

        return $this->messenger->send($org->id, $org->phone, $org->name, $template, $locale, [
            'customer' => $quote->customer?->name,
            'number' => $quote->number,
            'title' => $quote->displayTitle(),
            'total' => Phrases::total($quote, $locale),
            ...$params,
        ], $quote->appUrl(), 'quote', $quote->id);
    }
}
