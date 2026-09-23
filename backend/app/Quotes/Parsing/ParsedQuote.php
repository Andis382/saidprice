<?php

namespace App\Quotes\Parsing;

use App\Quotes\QuoteTotals;

/** What was understood from a spoken or typed quote. Every field is a suggestion. */
final class ParsedQuote
{
    public const ENGINE_PARSER = 'parser';

    public const ENGINE_AI = 'ai';

    /** @param list<ParsedItem> $items */
    public function __construct(
        public string $locale = 'sq',
        public ?string $customerName = null,
        public ?string $customerPhone = null,
        public ?string $customerAddress = null,
        public ?string $title = null,
        public array $items = [],
        public ?string $proposedFor = null,
        public ?string $notes = null,
        public ?string $currency = null,
        public string $engine = self::ENGINE_PARSER,
    ) {}

    public function totalCents(): int
    {
        return QuoteTotals::of(array_map(fn (ParsedItem $i) => ['qty' => $i->qty, 'unitPriceCents' => $i->unitPriceCents, 'kind' => $i->kind], $this->items))['total'];
    }

    public function toApi(): array
    {
        return [
            'locale' => $this->locale,
            'customerName' => $this->customerName,
            'customerPhone' => $this->customerPhone,
            'customerAddress' => $this->customerAddress,
            'title' => $this->title,
            'items' => array_map(fn (ParsedItem $i) => $i->toApi(), $this->items),
            'proposedFor' => $this->proposedFor,
            'notes' => $this->notes,
            'currency' => $this->currency,
            'engine' => $this->engine,
            'totalCents' => $this->totalCents(),
        ];
    }
}
