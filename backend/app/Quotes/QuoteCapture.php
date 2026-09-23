<?php

namespace App\Quotes;

use App\Models\BusinessSettings;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Quote;
use App\Models\QuoteEvent;
use App\Models\StoredFile;
use App\Models\User;
use App\Quotes\Parsing\ParsedItem;
use App\Support\Tokens;
use Illuminate\Support\Facades\DB;

/**
 * Starts a draft from what was said at the door: the transcript is read into lines, the
 * recording is kept with the quote, and the customer is matched by phone or added.
 */
class QuoteCapture
{
    public function __construct(
        private readonly QuoteExtractor $extractor,
        private readonly CustomerResolver $customers,
        private readonly QuoteNumbers $numbers,
        private readonly QuoteEditor $editor,
    ) {}

    public function draft(
        Organization $organization,
        ?User $by,
        ?string $transcript,
        ?StoredFile $voice = null,
        string $source = Quote::SOURCE_TYPED,
        ?Customer $customer = null,
        ?string $localeHint = null,
    ): Quote {
        $transcript = trim((string) $transcript) ?: null;
        $parsed = $transcript ? $this->extractor->extract($transcript, $localeHint ?? $organization->locale) : null;
        $settings = BusinessSettings::for($organization);

        return DB::transaction(function () use ($organization, $by, $transcript, $voice, $source, $customer, $parsed, $settings) {
            $customer ??= $parsed ? $this->customers->resolve([
                'name' => $parsed->customerName,
                'phone' => $parsed->customerPhone,
                'address' => $parsed->customerAddress,
                'locale' => $parsed->locale,
            ], $organization->locale) : null;

            $quote = new Quote([
                'customer_id' => $customer?->id,
                'created_by' => $by?->id,
                'number' => $this->numbers->next($organization->id, now()),
                'title' => $parsed?->title,
                'status' => QuoteStatus::Draft,
                'source' => $source,
                'proposed_for' => $parsed?->proposedFor,
                'validity_days' => $settings->default_validity_days,
                'notes' => $parsed?->notes,
                'terms' => $settings->terms,
                'voice_file_id' => $voice?->id,
                'transcript' => $transcript,
                'public_token' => Tokens::urlToken(),
            ]);
            $quote->organization_id = $organization->id;
            $quote->save();
            if ($parsed) {
                $this->editor->replaceItems($quote, array_map(fn (ParsedItem $i) => $i->toApi(), $parsed->items));
                $quote->save();
            }

            $quote->record(EventType::Created, $by ? QuoteEvent::USER : QuoteEvent::SYSTEM, $by, $source === Quote::SOURCE_WHATSAPP ? QuoteEvent::VIA_WHATSAPP : QuoteEvent::VIA_APP, meta: array_filter([
                'source' => $source,
                'engine' => $parsed?->engine,
                // Said in lekë while the business quotes in euro: the editor asks to check the prices.
                'currencySaid' => $parsed?->currency && $parsed->currency !== $organization->currency ? $parsed->currency : null,
            ]));

            return $quote;
        });
    }

    /** A new draft with the same customer and lines, for a revised or repeat job. */
    public function duplicate(Quote $original, User $by): Quote
    {
        return DB::transaction(function () use ($original, $by) {
            $copy = $original->replicate([
                'number', 'status', 'outcome', 'public_token', 'sent_at', 'viewed_at', 'responded_at', 'response_note',
                'nudge_count', 'last_nudged_at', 'lost_reason', 'outcome_at', 'valid_until', 'voice_file_id', 'transcript',
            ]);
            $copy->fill([
                'number' => $this->numbers->next($original->organization_id, now()),
                'status' => QuoteStatus::Draft,
                'source' => Quote::SOURCE_DUPLICATE,
                'created_by' => $by->id,
                'public_token' => Tokens::urlToken(),
            ]);
            $copy->save();
            $this->editor->replaceItems($copy, $original->items->map(fn ($i) => [
                'description' => $i->description, 'qty' => $i->qty, 'unit' => $i->unit,
                'unitPriceCents' => $i->unit_price_cents, 'kind' => $i->kind->value,
            ])->all());
            $copy->save();
            $copy->record(EventType::Created, QuoteEvent::USER, $by, QuoteEvent::VIA_APP, meta: ['source' => Quote::SOURCE_DUPLICATE, 'from' => $original->number]);

            return $copy;
        });
    }
}
