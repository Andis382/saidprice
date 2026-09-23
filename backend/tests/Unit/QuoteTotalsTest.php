<?php

namespace Tests\Unit;

use App\Quotes\ItemKind;
use App\Quotes\QuoteTotals;
use PHPUnit\Framework\TestCase;

class QuoteTotalsTest extends TestCase
{
    public function test_line_totals_round_once_to_whole_cents(): void
    {
        $this->assertSame(8500, QuoteTotals::line(1, 8500));
        $this->assertSame(1600, QuoteTotals::line(2, 800));
        $this->assertSame(3750, QuoteTotals::line(1.5, 2500));
        // 0.15 is not exact in binary; counted in hundredths, 15 x 30 / 100 = 4.5 rounds up.
        $this->assertSame(5, QuoteTotals::line(0.15, 30));
        // Quantities are kept to two decimals.
        $this->assertSame(331, QuoteTotals::line(0.333, 1003));
        $this->assertSame(0, QuoteTotals::line(0, 999));
    }

    public function test_the_total_is_the_sum_of_the_rounded_lines_split_by_kind(): void
    {
        $totals = QuoteTotals::of([
            ['qty' => 1, 'unitPriceCents' => 6500, 'kind' => ItemKind::Parts],
            ['qty' => 2, 'unitPriceCents' => 750, 'kind' => 'PARTS'],
            ['qty' => 1.5, 'unitPriceCents' => 2333, 'kind' => ItemKind::Labour],
            ['qty' => 1, 'unitPriceCents' => 1000, 'kind' => ItemKind::Other],
        ]);

        // 1.5 x 23.33 = 34.995 -> 35.00 on its own line
        $this->assertSame(['total' => 6500 + 1500 + 3500 + 1000, 'parts' => 8000, 'labour' => 3500, 'other' => 1000], $totals);
    }

    public function test_no_items_is_zero(): void
    {
        $this->assertSame(['total' => 0, 'parts' => 0, 'labour' => 0, 'other' => 0], QuoteTotals::of([]));
    }
}
