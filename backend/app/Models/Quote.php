<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Quotes\EventType;
use App\Quotes\QuoteOutcome;
use App\Quotes\QuoteStatus;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A written quote: scope, price, proposed date and validity, plus everything that happened
 * to it since. Money is integer cents in the organisation's currency.
 */
class Quote extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    public const SOURCE_VOICE = 'VOICE';

    public const SOURCE_TYPED = 'TYPED';

    public const SOURCE_WHATSAPP = 'WHATSAPP';

    public const SOURCE_DUPLICATE = 'DUPLICATE';

    protected $fillable = [
        'organization_id', 'customer_id', 'created_by', 'number', 'title', 'status', 'outcome', 'source',
        'total_cents', 'proposed_for', 'validity_days', 'valid_until', 'notes', 'terms', 'voice_file_id',
        'transcript', 'public_token', 'sent_at', 'viewed_at', 'responded_at', 'response_note',
        'nudge_count', 'last_nudged_at', 'lost_reason', 'outcome_at',
    ];

    protected $attributes = [
        'status' => 'DRAFT',
        'total_cents' => 0,
        'nudge_count' => 0,
        'validity_days' => 14,
        'source' => self::SOURCE_TYPED,
    ];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'outcome' => QuoteOutcome::class,
            'total_cents' => 'integer',
            'validity_days' => 'integer',
            'nudge_count' => 'integer',
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'responded_at' => 'datetime',
            'last_nudged_at' => 'datetime',
            'outcome_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('position')->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(QuoteEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function nudges(): HasMany
    {
        return $this->hasMany(Nudge::class)->orderBy('created_at')->orderBy('id');
    }

    public function voiceFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'voice_file_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Adds a line to the timeline. */
    public function record(
        EventType $type,
        string $actor,
        ?User $user = null,
        ?string $channel = null,
        ?string $body = null,
        array $meta = [],
    ): QuoteEvent {
        return $this->events()->create([
            'type' => $type,
            'actor' => $actor,
            'user_id' => $user?->id,
            'channel' => $channel,
            'body' => $body,
            'meta' => $meta ?: null,
            'created_at' => now(),
        ]);
    }

    /** What the quote is for, even when nobody typed a title. */
    public function displayTitle(): string
    {
        if ($this->title) {
            return $this->title;
        }
        $first = $this->relationLoaded('items') ? $this->items->first() : $this->items()->first();

        return $first?->description ?? trans('quotes.untitled', ['number' => $this->number], $this->customerLocale());
    }

    /** Accepted counts as won unless the tradesperson says the job fell through. */
    public function isWon(): bool
    {
        return $this->outcome === QuoteOutcome::Won
            || ($this->status === QuoteStatus::Accepted && $this->outcome !== QuoteOutcome::Lost);
    }

    /** isWon() in SQL, for counts and sums. */
    public function scopeWon(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->where('outcome', QuoteOutcome::Won)
            ->orWhere(fn (Builder $q) => $q->where('status', QuoteStatus::Accepted)->where(fn (Builder $q) => $q->whereNull('outcome')->orWhere('outcome', '!=', QuoteOutcome::Lost))));
    }

    /** The language the customer reads: theirs, else the business's. */
    public function customerLocale(): string
    {
        return $this->customer?->locale ?? $this->organization?->locale ?? config('product.default_locale');
    }

    public function publicUrl(): string
    {
        return config('product.public_url').'/q/'.$this->public_token;
    }

    public function appUrl(): string
    {
        return config('product.public_url').'/quotes/'.$this->id;
    }
}
