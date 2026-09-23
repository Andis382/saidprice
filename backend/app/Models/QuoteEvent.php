<?php

namespace App\Models;

use App\Quotes\EventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line on a quote's timeline. Written once, never edited. */
class QuoteEvent extends Model
{
    public const UPDATED_AT = null;

    public const USER = 'USER';

    public const CUSTOMER = 'CUSTOMER';

    public const SYSTEM = 'SYSTEM';

    public const VIA_APP = 'APP';

    public const VIA_LINK = 'LINK';

    public const VIA_WHATSAPP = 'WHATSAPP';

    protected $fillable = ['quote_id', 'type', 'actor', 'user_id', 'channel', 'body', 'meta', 'created_at'];

    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'actor' => $this->actor,
            'userName' => $this->user?->name,
            'channel' => $this->channel,
            'body' => $this->body,
            'meta' => $this->meta ?? (object) [],
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
