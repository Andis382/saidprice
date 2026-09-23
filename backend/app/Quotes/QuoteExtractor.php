<?php

namespace App\Quotes;

use App\Ai\AiExtractor;
use App\Quotes\Parsing\ParsedItem;
use App\Quotes\Parsing\ParsedQuote;
use App\Quotes\Parsing\QuoteParser;
use App\Quotes\Parsing\Vocabulary;
use App\Support\Phones;

/**
 * Transcript to structured quote. Claude reads it when ANTHROPIC_API_KEY is set; otherwise,
 * or when that fails, the rule-based QuoteParser does. Either way the editor comes next.
 */
class QuoteExtractor
{
    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'customerName' => ['type' => 'string', 'description' => 'As said, with a title if one was used ("Znj. Hoxha", "Mrs Hoxha"). Empty if not said.'],
            'customerPhone' => ['type' => 'string', 'description' => 'Digits as said. Empty if not said.'],
            'customerAddress' => ['type' => 'string', 'description' => 'Street and number as said. Empty if not said.'],
            'title' => ['type' => 'string', 'description' => 'Short name of the job in the language spoken, e.g. "Ndërrim rubineti kuzhine".'],
            'items' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'description' => ['type' => 'string'],
                        'qty' => ['type' => 'number'],
                        'unit' => ['type' => 'string', 'description' => 'h, m, pcs (orë, m, copë in Albanian) or empty'],
                        'unitPrice' => ['type' => 'number', 'description' => 'Price of one unit in the currency said'],
                        'kind' => ['type' => 'string', 'enum' => ['PARTS', 'LABOUR', 'OTHER']],
                    ],
                    'required' => ['description', 'qty', 'unit', 'unitPrice', 'kind'],
                    'additionalProperties' => false,
                ],
            ],
            'proposedFor' => ['type' => 'string', 'description' => 'When the work would be done, as said ("Thursday morning", "të enjten në mëngjes"). Empty if not said.'],
            'notes' => ['type' => 'string', 'description' => 'Anything else worth writing on the quote. Empty if nothing.'],
            'currency' => ['type' => 'string', 'description' => 'EUR, ALL, GBP or USD if a currency was said, else empty'],
        ],
        'required' => ['customerName', 'customerPhone', 'customerAddress', 'title', 'items', 'proposedFor', 'notes', 'currency'],
        'additionalProperties' => false,
    ];

    public function __construct(
        private readonly AiExtractor $ai,
        private readonly QuoteParser $parser,
    ) {}

    public function extract(string $transcript, string $localeHint): ParsedQuote
    {
        if ($this->ai->enabled()) {
            $data = $this->ai->fromText($transcript, $this->instruction(), self::SCHEMA);
            if (is_array($data) && isset($data['items'])) {
                return $this->fromAi($data, Vocabulary::detectLocale($transcript, $localeHint));
            }
        }

        return $this->parser->parse($transcript, $localeHint);
    }

    private function instruction(): string
    {
        return 'A plumber or electrician in Albania said a quote out loud to a customer, in Albanian or English. '
            .'Turn the transcript into a written quote. Keep every description in the language spoken. '
            .'Split the price the way it was said: a parts amount and a labour amount are two lines; '
            .'"two hoses at 8" is qty 2 at 8. Never invent prices, names or dates that were not said.';
    }

    /** @param array<string, mixed> $data */
    private function fromAi(array $data, string $locale): ParsedQuote
    {
        $text = fn (string $key) => is_string($data[$key] ?? null) && trim($data[$key]) !== '' ? trim($data[$key]) : null;
        $items = [];
        foreach ($data['items'] as $item) {
            if (! is_array($item) || trim((string) ($item['description'] ?? '')) === '') {
                continue;
            }
            $items[] = new ParsedItem(
                description: trim((string) $item['description']),
                qty: max(0.01, round((float) ($item['qty'] ?? 1), 2)),
                unit: trim((string) ($item['unit'] ?? '')) ?: null,
                unitPriceCents: (int) round(((float) ($item['unitPrice'] ?? 0)) * 100),
                kind: ItemKind::tryFrom((string) ($item['kind'] ?? '')) ?? ItemKind::Parts,
            );
        }

        return new ParsedQuote(
            locale: $locale,
            customerName: $text('customerName'),
            customerPhone: Phones::normalize($text('customerPhone')),
            customerAddress: $text('customerAddress'),
            title: $text('title'),
            items: $items,
            proposedFor: $text('proposedFor'),
            notes: $text('notes'),
            currency: $text('currency'),
            engine: ParsedQuote::ENGINE_AI,
        );
    }
}
