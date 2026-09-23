<?php

namespace App\Quotes;

/**
 * Line and quote totals. Quantities may be fractional (1.5 h, 2.5 m); the line total is
 * rounded to whole cents once, and the quote total is the sum of the rounded lines, so
 * what the customer adds up on paper matches the total printed under it.
 */
class QuoteTotals
{
    public static function line(float $qty, int $unitPriceCents): int
    {
        // Quantities have two decimals: in whole hundredths the product is exact integer maths.
        $hundredths = (int) round($qty * 100);

        return (int) round($hundredths * $unitPriceCents / 100, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * @param  iterable<array{qty: float|int, unitPriceCents: int, kind?: ItemKind|string}>  $items
     * @return array{total: int, parts: int, labour: int, other: int}
     */
    public static function of(iterable $items): array
    {
        $sums = ['total' => 0, 'parts' => 0, 'labour' => 0, 'other' => 0];
        foreach ($items as $item) {
            $line = self::line((float) $item['qty'], (int) $item['unitPriceCents']);
            $kind = $item['kind'] ?? ItemKind::Other;
            $kind = $kind instanceof ItemKind ? $kind : ItemKind::from($kind);
            $sums['total'] += $line;
            $sums[strtolower($kind->name)] += $line;
        }

        return $sums;
    }
}
