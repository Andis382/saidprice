<?php

namespace App\Models;

use App\Quotes\ItemKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of a quote. Reached through its (tenant-scoped) quote, never on its own. */
class QuoteItem extends Model
{
    protected $fillable = ['quote_id', 'position', 'description', 'qty', 'unit', 'unit_price_cents', 'kind', 'total_cents'];

    protected function casts(): array
    {
        return [
            'kind' => ItemKind::class,
            'qty' => 'float',
            'unit_price_cents' => 'integer',
            'total_cents' => 'integer',
            'position' => 'integer',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'qty' => $this->qty,
            'unit' => $this->unit,
            'unitPriceCents' => $this->unit_price_cents,
            'kind' => $this->kind->value,
            'totalCents' => $this->total_cents,
        ];
    }
}
