<?php

namespace App\Quotes\Parsing;

use App\Quotes\ItemKind;

final class ParsedItem
{
    public function __construct(
        public string $description,
        public float $qty,
        public ?string $unit,
        public int $unitPriceCents,
        public ItemKind $kind,
    ) {}

    public function toApi(): array
    {
        return [
            'description' => $this->description,
            'qty' => $this->qty,
            'unit' => $this->unit,
            'unitPriceCents' => $this->unitPriceCents,
            'kind' => $this->kind->value,
        ];
    }
}
