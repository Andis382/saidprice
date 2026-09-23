<?php

namespace App\Support;

use NumberFormatter;

/** Integer cents to "€145" / "145 €", the same way the web app shows it. */
class Money
{
    public static function format(int $cents, string $currency, string $locale): string
    {
        $formatter = new NumberFormatter($locale === 'sq' ? 'sq_AL' : 'en_GB', NumberFormatter::CURRENCY);
        $decimals = $cents % 100 === 0 ? 0 : 2;
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

        // Plain spaces read the same in WhatsApp and survive every PDF font.
        return str_replace(["\u{00A0}", "\u{202F}"], ' ', (string) $formatter->formatCurrency($cents / 100, $currency));
    }
}
