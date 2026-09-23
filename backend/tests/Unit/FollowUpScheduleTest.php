<?php

namespace Tests\Unit;

use App\Quotes\FollowUps\FollowUpSchedule;
use App\Quotes\NudgeStatus;
use App\Quotes\QuoteStatus;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class FollowUpScheduleTest extends TestCase
{
    private FollowUpSchedule $schedule;

    private CarbonImmutable $sent;

    private CarbonImmutable $expires;

    protected function setUp(): void
    {
        $this->schedule = new FollowUpSchedule(48, 5);
        $this->sent = CarbonImmutable::parse('2026-09-01 10:00');
        $this->expires = CarbonImmutable::parse('2026-09-15 21:59:59');
    }

    public function test_the_first_nudge_falls_due_after_the_quiet_period(): void
    {
        $this->assertSame(
            ['action' => FollowUpSchedule::WAIT, 'at' => '2026-09-03 10:00'],
            $this->plan(QuoteStatus::Sent, [], null, $this->sent->addHours(10)),
        );
        $this->assertSame(FollowUpSchedule::FIRST_NUDGE, $this->plan(QuoteStatus::Viewed, [], null, $this->sent->addHours(48))['action']);
    }

    public function test_a_waiting_draft_holds_everything(): void
    {
        $nudges = [['status' => NudgeStatus::Drafted, 'actedAt' => null]];

        $this->assertSame(['action' => FollowUpSchedule::WAIT, 'at' => null], $this->plan(QuoteStatus::Sent, $nudges, null, $this->sent->addDays(9)));
    }

    public function test_the_second_waits_for_both_the_day_count_and_a_gap_after_the_first(): void
    {
        $early = [['status' => NudgeStatus::Sent, 'actedAt' => $this->sent->addHours(50)]];
        $late = [['status' => NudgeStatus::Dismissed, 'actedAt' => $this->sent->addHours(110)]];

        $this->assertSame('2026-09-06 10:00', $this->plan(QuoteStatus::Sent, $early, null, $this->sent->addHours(60))['at']);
        $this->assertSame('2026-09-08 00:00', $this->plan(QuoteStatus::Sent, $late, null, $this->sent->addHours(115))['at']);
        $this->assertSame(FollowUpSchedule::SECOND_NUDGE, $this->plan(QuoteStatus::Sent, $early, null, $this->sent->addDays(5))['action']);
    }

    public function test_silence_after_both_and_a_question_restarts_the_clock(): void
    {
        $both = [
            ['status' => NudgeStatus::Sent, 'actedAt' => $this->sent->addHours(50)],
            ['status' => NudgeStatus::Sent, 'actedAt' => $this->sent->addDays(5)],
        ];

        $this->assertSame(FollowUpSchedule::SILENT, $this->plan(QuoteStatus::Sent, $both, null, $this->sent->addDays(7))['action']);
        $this->assertSame(
            ['action' => FollowUpSchedule::WAIT, 'at' => '2026-09-09 12:00'],
            $this->plan(QuoteStatus::Sent, $both, $this->sent->addDays(6)->addHours(2), $this->sent->addDays(7)),
        );
    }

    public function test_expiry_wins_and_closed_quotes_wait(): void
    {
        $this->assertSame(FollowUpSchedule::EXPIRE, $this->plan(QuoteStatus::Silent, [], null, $this->expires->addSecond())['action']);
        $this->assertSame(FollowUpSchedule::WAIT, $this->plan(QuoteStatus::Silent, [], null, $this->expires->subDay())['action']);
        $this->assertSame(FollowUpSchedule::WAIT, $this->plan(QuoteStatus::Accepted, [], null, $this->expires->addDay())['action']);
    }

    /** @return array{action: string, at: ?string} */
    private function plan(QuoteStatus $status, array $nudges, ?CarbonImmutable $question, CarbonImmutable $now): array
    {
        $plan = $this->schedule->plan($status, $this->sent, $this->expires, $nudges, $question, $now);

        return ['action' => $plan['action'], 'at' => $plan['at']?->format('Y-m-d H:i')];
    }
}
