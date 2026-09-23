<?php

namespace App\Quotes;

/**
 * Where a quote stands with the customer. The transition table is the single source of
 * truth for what may happen next; QuoteWorkflow refuses anything it does not list.
 */
enum QuoteStatus: string
{
    case Draft = 'DRAFT';
    case Sent = 'SENT';
    case Viewed = 'VIEWED';
    case Accepted = 'ACCEPTED';
    case Declined = 'DECLINED';
    case Silent = 'SILENT';
    case Expired = 'EXPIRED';

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Draft => [self::Sent],
            self::Sent => [self::Sent, self::Viewed, self::Accepted, self::Declined, self::Silent, self::Expired],
            self::Viewed => [self::Sent, self::Accepted, self::Declined, self::Silent, self::Expired],
            // A silent customer can still answer while the quote is valid.
            self::Silent => [self::Sent, self::Accepted, self::Declined, self::Expired],
            // Resending renews an expired quote; "won" by phone after it lapsed is still a win.
            self::Expired => [self::Sent, self::Accepted],
            // The customer changed their mind and said yes after all.
            self::Declined => [self::Accepted],
            // What was agreed stays on record.
            self::Accepted => [],
        };
    }

    public function canBecome(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    /** Sent and waiting for an answer: the follow-up clock runs. */
    public function isAwaitingAnswer(): bool
    {
        return $this === self::Sent || $this === self::Viewed;
    }

    /** The customer can still accept or decline from the link or WhatsApp. */
    public function isAnswerable(): bool
    {
        return in_array($this, [self::Sent, self::Viewed, self::Silent], true);
    }

    /** Scope and price may still change; once answered they are the record. */
    public function isEditable(): bool
    {
        return ! in_array($this, [self::Accepted, self::Declined], true);
    }

    public function isSent(): bool
    {
        return $this !== self::Draft;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
