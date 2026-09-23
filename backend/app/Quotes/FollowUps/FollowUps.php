<?php

namespace App\Quotes\FollowUps;

use App\Models\BusinessSettings;
use App\Models\Nudge;
use App\Models\Organization;
use App\Models\Quote;
use App\Models\QuoteEvent;
use App\Quotes\EventType;
use App\Quotes\NudgeStatus;
use App\Quotes\QuoteStatus;
use App\Quotes\QuoteWorkflow;
use App\Support\Tenant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * The hourly pass over quotes waiting for an answer: drafts follow-ups, marks quotes silent
 * or expired. It never sends anything; drafts wait in the Follow-ups inbox for a tap.
 */
class FollowUps
{
    public function __construct(
        private readonly QuoteWorkflow $workflow,
        private readonly NudgeDrafter $drafter,
    ) {}

    /** @return array{drafted: int, silent: int, expired: int} */
    public function run(Organization $organization, ?CarbonInterface $now = null): array
    {
        $now ??= now();

        return Tenant::run($organization->id, function () use ($organization, $now) {
            $counts = ['drafted' => 0, 'silent' => 0, 'expired' => 0];
            foreach ($this->waiting() as $quote) {
                switch ($this->plan($quote, $organization, $now)['action']) {
                    case FollowUpSchedule::FIRST_NUDGE:
                        $this->draftNudge($quote, 1);
                        $counts['drafted']++;
                        break;
                    case FollowUpSchedule::SECOND_NUDGE:
                        $this->draftNudge($quote, 2);
                        $counts['drafted']++;
                        break;
                    case FollowUpSchedule::SILENT:
                        $this->workflow->silent($quote);
                        $counts['silent']++;
                        break;
                    case FollowUpSchedule::EXPIRE:
                        $this->workflow->expire($quote);
                        $counts['expired']++;
                        break;
                }
            }

            return $counts;
        });
    }

    /**
     * What happens next for one sent quote; the detail screen shows it as "next follow-up".
     * Expects nudges loaded and last_question_at selected (see waiting()).
     *
     * @return array{action: string, at: ?CarbonImmutable}
     */
    public function plan(Quote $quote, Organization $organization, CarbonInterface $now): array
    {
        $settings = BusinessSettings::for($organization);
        $schedule = new FollowUpSchedule($settings->first_nudge_hours, $settings->second_nudge_days);
        $nudges = $quote->nudges
            ->filter(fn (Nudge $n) => $n->created_at->greaterThanOrEqualTo($quote->sent_at))
            ->map(fn (Nudge $n) => ['status' => $n->status, 'actedAt' => $n->actedAt()])
            ->values()
            ->all();
        // Valid until the end of that day where the business is.
        $expiresAt = $quote->valid_until->copy()->shiftTimezone($organization->timezone)->endOfDay();
        $lastQuestion = $quote->last_question_at ? Carbon::parse($quote->last_question_at) : null;

        return $schedule->plan($quote->status, $quote->sent_at, $expiresAt, $nudges, $lastQuestion, $now);
    }

    /** @return Collection<int, Quote> */
    public function waiting(): Collection
    {
        return Quote::with(['customer', 'nudges', 'organization'])
            ->withMax(['events as last_question_at' => fn ($q) => $q->where('type', EventType::Question)], 'created_at')
            ->whereIn('status', [QuoteStatus::Sent, QuoteStatus::Viewed, QuoteStatus::Silent])
            ->whereNotNull('sent_at')
            ->whereNotNull('valid_until')
            ->get();
    }

    private function draftNudge(Quote $quote, int $sequence): void
    {
        $quote->nudges()->create([
            'organization_id' => $quote->organization_id,
            'sequence' => $sequence,
            'body' => $this->drafter->draft($quote, $sequence),
            'status' => NudgeStatus::Drafted,
        ]);
        $quote->record(EventType::NudgeDrafted, QuoteEvent::SYSTEM, meta: ['sequence' => $sequence]);
    }
}
