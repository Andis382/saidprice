<?php

namespace App\Quotes;

use RuntimeException;

/** Something was asked of a quote that its status no longer allows. Rendered as 409. */
class QuoteStateException extends RuntimeException
{
    public static function cannotBecome(QuoteStatus $from, QuoteStatus $to): self
    {
        return new self(__('quotes.invalid_transition', ['status' => __('quotes.status.'.$from->value)]));
    }
}
