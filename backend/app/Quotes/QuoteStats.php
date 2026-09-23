<?php

namespace App\Quotes;

use App\Models\Quote;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The numbers a tradesperson has never seen: of the quotes sent each week, how many were
 * accepted, declined or ignored, what was quoted against what was won, and how long
 * customers take to answer. Weeks run Monday to Sunday in the business's time zone and
 * each quote counts in the week it was sent, so a week's numbers keep filling in as
 * answers arrive.
 */
class QuoteStats
{
    /**
     * @return array{weeks: list<array<string, mixed>>, total: array<string, mixed>, thisWeek: array<string, mixed>}
     */
    public function weekly(string $timezone, CarbonInterface $now, int $weeks = 12): array
    {
        $thisWeek = CarbonImmutable::instance($now)->tz($timezone)->startOfWeek(CarbonInterface::MONDAY);
        $from = $thisWeek->subWeeks($weeks - 1);

        $buckets = [];
        for ($i = 0; $i < $weeks; $i++) {
            $buckets[$from->addWeeks($i)->toDateString()] = [];
        }
        $quotes = Quote::query()
            ->where('sent_at', '>=', $from->utc())
            ->get(['id', 'status', 'outcome', 'total_cents', 'sent_at', 'responded_at']);
        foreach ($quotes as $quote) {
            $week = $quote->sent_at->copy()->tz($timezone)->startOfWeek(CarbonInterface::MONDAY)->toDateString();
            if (isset($buckets[$week])) {
                $buckets[$week][] = $quote;
            }
        }

        $rows = [];
        foreach ($buckets as $weekStart => $inWeek) {
            $rows[] = ['weekStart' => $weekStart, ...$this->summarise($inWeek)];
        }

        return [
            'weeks' => $rows,
            'total' => $this->summarise(array_merge(...array_values($buckets))),
            'thisWeek' => $rows[count($rows) - 1],
        ];
    }

    /**
     * @param  list<Quote>  $quotes
     * @return array<string, int|float|null>
     */
    public function summarise(array $quotes): array
    {
        $with = fn (QuoteStatus ...$statuses) => count(array_filter($quotes, fn (Quote $q) => in_array($q->status, $statuses, true)));
        $won = array_filter($quotes, fn (Quote $q) => $q->isWon());
        $answerHours = [];
        foreach ($quotes as $q) {
            if ($q->responded_at && in_array($q->status, [QuoteStatus::Accepted, QuoteStatus::Declined], true)) {
                $answerHours[] = $q->sent_at->diffInMinutes($q->responded_at) / 60;
            }
        }
        $sent = count($quotes);

        return [
            'sent' => $sent,
            'accepted' => $with(QuoteStatus::Accepted),
            'declined' => $with(QuoteStatus::Declined),
            'silent' => $with(QuoteStatus::Silent, QuoteStatus::Expired),
            'open' => $with(QuoteStatus::Sent, QuoteStatus::Viewed),
            'won' => count($won),
            'quotedCents' => array_sum(array_map(fn (Quote $q) => $q->total_cents, $quotes)),
            'wonCents' => array_sum(array_map(fn (Quote $q) => $q->total_cents, $won)),
            'conversion' => $sent > 0 ? round(count($won) / $sent, 4) : null,
            'medianAnswerHours' => self::median($answerHours),
        ];
    }

    /** @param list<float|int> $values */
    public static function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }
        sort($values);
        $middle = intdiv(count($values), 2);
        $median = count($values) % 2 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;

        return round((float) $median, 1);
    }
}
