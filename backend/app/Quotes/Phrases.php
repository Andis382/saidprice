<?php

namespace App\Quotes;

use App\Models\Quote;
use App\Support\Money;
use Carbon\CarbonInterface;

/** Small pieces of customer-facing text, in the reader's language. */
class Phrases
{
    private const EN_DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /** "Të enjten në mëngjes" inside a sentence is "të enjten në mëngjes"; "Thursday" stays "Thursday". */
    public static function when(?string $proposedFor, string $locale, string $fallbackKey = 'quotes.when_tba'): string
    {
        $when = trim((string) $proposedFor);
        if ($when === '') {
            return trans($fallbackKey, [], $locale);
        }
        $first = strtok($when, ' ');
        if (in_array(rtrim((string) $first, ','), self::EN_DAYS, true)) {
            return $when;
        }

        return mb_strtolower(mb_substr($when, 0, 1)).mb_substr($when, 1);
    }

    public static function total(Quote $quote, string $locale): string
    {
        return Money::format($quote->total_cents, $quote->organization->currency, $locale);
    }

    public static function date(?CarbonInterface $date, string $locale): string
    {
        return $date ? $date->copy()->locale($locale)->isoFormat('D MMM YYYY') : '';
    }
}
