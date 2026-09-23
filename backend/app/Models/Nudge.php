<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Quotes\NudgeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** A drafted follow-up for a quote nobody answered. It waits for a person to tap Send. */
class Nudge extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'quote_id', 'sequence', 'body', 'status', 'acted_by', 'outbound_message_id', 'sent_at', 'dismissed_at', 'created_at'];

    protected $attributes = ['status' => 'DRAFTED'];

    protected function casts(): array
    {
        return [
            'status' => NudgeStatus::class,
            'sequence' => 'integer',
            'sent_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function outboundMessage(): BelongsTo
    {
        return $this->belongsTo(OutboundMessage::class);
    }

    /** When the person acted on it, whichever way. */
    public function actedAt(): ?Carbon
    {
        return $this->sent_at ?? $this->dismissed_at;
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'quoteId' => $this->quote_id,
            'sequence' => $this->sequence,
            'body' => $this->body,
            'status' => $this->status->value,
            'createdAt' => $this->created_at?->toIso8601String(),
            'sentAt' => $this->sent_at?->toIso8601String(),
            'dismissedAt' => $this->dismissed_at?->toIso8601String(),
        ];
    }
}
