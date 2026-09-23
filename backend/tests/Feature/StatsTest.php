<?php

namespace Tests\Feature;

use App\Quotes\QuoteOutcome;
use App\Quotes\QuoteStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\MakesQuotes;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use MakesQuotes, RefreshDatabase;

    public function test_weekly_numbers_count_each_quote_in_the_week_it_was_sent(): void
    {
        Carbon::setTestNow('2026-09-23 12:00:00'); // Wednesday
        $owner = $this->owner();
        $make = fn (string $sentAt, QuoteStatus $status, int $cents, ?string $respondedAt = null, ?QuoteOutcome $outcome = null) => $this->quoteFor($owner)
            ->sent(Carbon::parse($sentAt), $status)
            ->create(['total_cents' => $cents, 'responded_at' => $respondedAt ? Carbon::parse($respondedAt) : null, 'outcome' => $outcome]);

        // This week (from Monday 21 September, Tirana time)
        $make('2026-09-21 08:00', QuoteStatus::Accepted, 14500, '2026-09-21 12:00');
        $make('2026-09-22 09:00', QuoteStatus::Declined, 30000, '2026-09-23 09:00');
        $make('2026-09-22 10:00', QuoteStatus::Viewed, 8000);
        $make('2026-09-23 07:00', QuoteStatus::Sent, 5000);
        // Last week
        $make('2026-09-15 10:00', QuoteStatus::Accepted, 20000, '2026-09-15 16:00', QuoteOutcome::Won);
        $make('2026-09-16 10:00', QuoteStatus::Accepted, 10000, '2026-09-17 10:00', QuoteOutcome::Lost);
        $make('2026-09-17 10:00', QuoteStatus::Silent, 12000);
        $make('2026-09-18 10:00', QuoteStatus::Expired, 9000);
        // Sunday 20 September 23:30 in Tirana is still last week, though it is Monday nowhere yet in UTC terms
        $make('2026-09-20 21:30', QuoteStatus::Declined, 7000, '2026-09-21 09:30');
        // A draft never counts
        $this->quoteFor($owner)->create();

        $stats = $this->actingAs($owner)->getJson('/api/stats')->assertOk()->json();

        $this->assertCount(12, $stats['weeks']);
        $this->assertSame('2026-09-21', $stats['thisWeek']['weekStart']);
        $this->assertSame(4, $stats['thisWeek']['sent']);
        $this->assertSame(1, $stats['thisWeek']['accepted']);
        $this->assertSame(1, $stats['thisWeek']['declined']);
        $this->assertSame(2, $stats['thisWeek']['open']);
        $this->assertSame(57500, $stats['thisWeek']['quotedCents']);
        $this->assertSame(14500, $stats['thisWeek']['wonCents']);
        $this->assertEquals(0.25, $stats['thisWeek']['conversion']);
        $this->assertEquals(14.0, $stats['thisWeek']['medianAnswerHours']); // 4 h and 24 h

        $last = $stats['weeks'][10];
        $this->assertSame('2026-09-14', $last['weekStart']);
        $this->assertSame(5, $last['sent']);
        $this->assertSame(2, $last['accepted']);
        $this->assertSame(1, $last['won'], 'accepted but lost later is not won');
        $this->assertSame(2, $last['silent'], 'silent and expired together');
        $this->assertSame(20000, $last['wonCents']);
        $this->assertEquals(0.2, $last['conversion']);
        $this->assertEquals(12.0, $last['medianAnswerHours']); // 6 h, 12 h, 24 h

        $this->assertSame(9, $stats['total']['sent']);
        $this->assertSame(2, $stats['total']['won']);
    }

    public function test_empty_weeks_have_no_conversion_rather_than_zero(): void
    {
        $owner = $this->owner();

        $stats = $this->actingAs($owner)->getJson('/api/stats?weeks=4')->assertOk()->json();

        $this->assertCount(4, $stats['weeks']);
        $this->assertSame(0, $stats['thisWeek']['sent']);
        $this->assertNull($stats['thisWeek']['conversion']);
        $this->assertNull($stats['thisWeek']['medianAnswerHours']);
    }
}
