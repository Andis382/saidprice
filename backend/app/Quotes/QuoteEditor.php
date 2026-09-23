<?php

namespace App\Quotes;

use App\Models\Quote;
use App\Models\QuoteEvent;
use App\Models\QuoteItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Applies what the editor saved: customer, lines, date, validity, notes and terms. */
class QuoteEditor
{
    public function __construct(private readonly CustomerResolver $customers) {}

    /** @param array<string, mixed> $data validated editor payload (camelCase) */
    public function update(Quote $quote, array $data, User $by): Quote
    {
        if (! $quote->status->isEditable()) {
            throw new QuoteStateException(__('quotes.not_editable'));
        }

        return DB::transaction(function () use ($quote, $data, $by) {
            $totalBefore = $quote->total_cents;
            if (array_key_exists('customer', $data)) {
                $customer = $data['customer'] ? $this->customers->resolve($data['customer'], $quote->organization->locale) : null;
                $quote->customer()->associate($customer);
            }
            $fields = [
                'title' => 'title', 'proposedFor' => 'proposed_for', 'validityDays' => 'validity_days',
                'notes' => 'notes', 'terms' => 'terms', 'transcript' => 'transcript',
            ];
            foreach ($fields as $key => $column) {
                if (array_key_exists($key, $data)) {
                    $quote->{$column} = is_string($data[$key]) ? (trim($data[$key]) ?: null) : $data[$key];
                }
            }
            if (array_key_exists('items', $data)) {
                $this->replaceItems($quote, $data['items']);
            }
            if ($quote->status->isSent() && $quote->sent_at) {
                $quote->valid_until = $quote->sent_at->copy()->tz($quote->organization->timezone)->addDays($quote->validity_days)->toDateString();
                $quote->record(EventType::Edited, QuoteEvent::USER, $by, QuoteEvent::VIA_APP, meta: ['fromCents' => $totalBefore, 'toCents' => $quote->total_cents]);
            }
            $quote->save();

            return $quote;
        });
    }

    /** @param list<array{description: string, qty: float|int|string, unit?: ?string, unitPriceCents: int|string, kind: string}> $items */
    public function replaceItems(Quote $quote, array $items): void
    {
        $quote->items()->delete();
        $rows = [];
        foreach (array_values($items) as $position => $item) {
            $qty = round((float) $item['qty'], 2);
            $unitPrice = (int) $item['unitPriceCents'];
            $rows[] = new QuoteItem([
                'position' => $position,
                'description' => trim((string) $item['description']),
                'qty' => $qty,
                'unit' => isset($item['unit']) && trim((string) $item['unit']) !== '' ? trim((string) $item['unit']) : null,
                'unit_price_cents' => $unitPrice,
                'kind' => ItemKind::from($item['kind']),
                'total_cents' => QuoteTotals::line($qty, $unitPrice),
            ]);
        }
        $quote->items()->saveMany($rows);
        $quote->total_cents = array_sum(array_map(fn (QuoteItem $i) => $i->total_cents, $rows));
        $quote->setRelation('items', collect($rows));
    }
}
