<?php

namespace App\Quotes\FollowUps;

use App\Models\Quote;
use App\Quotes\Phrases;

/**
 * One polite, specific line in the customer's language: which job, what price, which day.
 * Asking is easier when the words are already written.
 */
class NudgeDrafter
{
    public function draft(Quote $quote, int $sequence): string
    {
        $locale = $quote->customerLocale();
        $when = $quote->proposed_for ? trans('nudge.when', ['when' => Phrases::when($quote->proposed_for, $locale)], $locale) : '';

        return trans($sequence === 1 ? 'nudge.first' : 'nudge.second', [
            'name' => $quote->customer?->greetingName() ?? '',
            'title' => $quote->displayTitle(),
            'total' => Phrases::total($quote, $locale),
            'when' => $when,
        ], $locale);
    }
}
