<?php

namespace App\Quotes\FollowUps;

use App\Quotes\NudgeStatus;
use App\Quotes\QuoteStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * When a quote nobody answered needs the next follow-up. Pure: it only reads times.
 *
 *   sent ──48 h──▶ first nudge drafted ──(5 days after sending)──▶ second nudge drafted
 *        ──48 h after the second was sent or dismissed──▶ SILENT
 *
 * Nothing is drafted while a draft waits for the tradesperson, and a customer question
 * restarts the quiet period: the ball is in the tradesperson's court. Past its validity
 * the quote expires instead.
 */
final class FollowUpSchedule
{
    public const WAIT = 'wait';

    public const FIRST_NUDGE = 'first_nudge';

    public const SECOND_NUDGE = 'second_nudge';

    public const SILENT = 'silent';

    public const EXPIRE = 'expire';

    public function __construct(
        private readonly int $firstNudgeHours,
        private readonly int $secondNudgeDays,
    ) {}

    /**
     * @param  list<array{status: NudgeStatus, actedAt: ?CarbonInterface}>  $nudges  drafted since the quote was last sent, oldest first
     * @return array{action: string, at: ?CarbonImmutable} what to do now, or when the next step falls due
     */
    public function plan(
        QuoteStatus $status,
        CarbonInterface $sentAt,
        CarbonInterface $expiresAt,
        array $nudges,
        ?CarbonInterface $lastQuestionAt,
        CarbonInterface $now,
    ): array {
        if ($status->isAnswerable() && $now->greaterThanOrEqualTo($expiresAt)) {
            return ['action' => self::EXPIRE, 'at' => null];
        }
        if (! $status->isAwaitingAnswer()) {
            return ['action' => self::WAIT, 'at' => null];
        }
        foreach ($nudges as $nudge) {
            if ($nudge['status'] === NudgeStatus::Drafted) {
                return ['action' => self::WAIT, 'at' => null];
            }
        }

        $gap = fn (?CarbonInterface $from) => $from ? CarbonImmutable::instance($from)->addHours($this->firstNudgeHours) : null;
        $sent = CarbonImmutable::instance($sentAt);
        $lastActed = $nudges ? $nudges[count($nudges) - 1]['actedAt'] : null;
        [$action, $candidates] = match (count($nudges)) {
            0 => [self::FIRST_NUDGE, [$gap($sent)]],
            1 => [self::SECOND_NUDGE, [$sent->addDays($this->secondNudgeDays), $gap($lastActed)]],
            default => [self::SILENT, [$gap($lastActed ?? $sent)]],
        };
        $candidates[] = $gap($lastQuestionAt);
        $due = max(array_filter($candidates));

        return $now->greaterThanOrEqualTo($due) ? ['action' => $action, 'at' => null] : ['action' => self::WAIT, 'at' => $due];
    }
}
