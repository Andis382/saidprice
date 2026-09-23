<?php

namespace Tests\Feature;

use App\Models\Nudge;
use App\Models\OutboundMessage;
use App\Models\QuoteEvent;
use App\Quotes\EventType;
use App\Quotes\NudgeStatus;
use App\Quotes\QuoteStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\MakesQuotes;
use Tests\TestCase;

class FollowUpJobTest extends TestCase
{
    use MakesQuotes, RefreshDatabase;

    private Carbon $sentAt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sentAt = Carbon::parse('2026-09-01 10:00:00');
    }

    public function test_the_full_follow_up_cycle_drafts_but_never_sends(): void
    {
        $owner = $this->owner();
        $quote = $this->quoteFor($owner, $this->customerOf($owner, ['name' => 'Drita Hoxha']))->sent($this->sentAt)->create();

        $this->runAt(47);
        $this->assertSame(0, Nudge::count());

        $this->runAt(48);
        $first = Nudge::sole();
        $this->assertSame(NudgeStatus::Drafted, $first->status);
        $this->assertSame(1, $first->sequence);
        $this->assertSame('Përshëndetje Drita, po ju shkruaj për ofertën "Ndërrim rubineti kuzhine" (145 €, të enjten në mëngjes). Ta rezervoj? Mund të përgjigjeni këtu ose të hapni lidhjen.', $first->body);
        $this->assertSame(0, OutboundMessage::withoutGlobalScope('organization')->count(), 'drafting must not send');

        $this->runAt(72);
        $this->assertSame(1, Nudge::count(), 'no second draft while the first waits');

        Carbon::setTestNow($this->sentAt->copy()->addHours(80));
        $this->actingAs($owner)->postJson("/api/nudges/{$first->id}/send")->assertOk()
            ->assertJsonPath('message.templateKey', 'quote_nudge');
        $this->assertSame(1, $quote->fresh()->nudge_count);
        $this->assertSame(1, OutboundMessage::withoutGlobalScope('organization')->count());

        // Second one: five days after sending, and not sooner than 48 h after the first went out.
        $this->runAt(120);
        $this->assertSame(1, Nudge::count());
        $this->runAt(128);
        $second = Nudge::where('sequence', 2)->sole();
        $this->assertStringContainsString('një pyetje e fundit', $second->body);

        Carbon::setTestNow($this->sentAt->copy()->addHours(130));
        $this->actingAs($owner)->postJson("/api/nudges/{$second->id}/dismiss")->assertOk()->assertJsonPath('status', 'DISMISSED');

        $this->runAt(177);
        $this->assertSame(QuoteStatus::Sent, $quote->fresh()->status);
        $this->runAt(178);
        $this->assertSame(QuoteStatus::Silent, $quote->fresh()->status);
        $this->assertSame(2, Nudge::count());
        $this->assertSame(1, OutboundMessage::withoutGlobalScope('organization')->count(), 'only the tapped nudge was sent');

        $types = $quote->fresh()->events->pluck('type')->map->value->all();
        $this->assertSame(['NUDGE_DRAFTED', 'NUDGE_SENT', 'NUDGE_DRAFTED', 'NUDGE_DISMISSED', 'SILENT'], $types);
    }

    public function test_quotes_expire_at_the_end_of_their_last_valid_day(): void
    {
        $owner = $this->owner();
        $quote = $this->quoteFor($owner)->sent($this->sentAt, QuoteStatus::Silent)->create();
        $this->assertSame('2026-09-15', $quote->valid_until->toDateString());

        Carbon::setTestNow('2026-09-15 21:59:00');
        $this->artisan('quotes:follow-up')->assertSuccessful();
        $this->assertSame(QuoteStatus::Silent, $quote->fresh()->status);

        Carbon::setTestNow('2026-09-15 22:00:30');
        $this->artisan('quotes:follow-up')->expectsOutputToContain('1 expired')->assertSuccessful();
        $this->assertSame(QuoteStatus::Expired, $quote->fresh()->status);
    }

    public function test_a_customer_question_pauses_the_follow_ups(): void
    {
        $owner = $this->owner();
        $quote = $this->quoteFor($owner)->sent($this->sentAt)->create();
        Carbon::setTestNow($this->sentAt->copy()->addHours(40));
        $quote->record(EventType::Question, QuoteEvent::CUSTOMER, channel: QuoteEvent::VIA_WHATSAPP, body: 'A vini më herët?');

        $this->runAt(48);
        $this->assertSame(0, Nudge::count());
        $this->runAt(88);
        $this->assertSame(1, Nudge::count());
    }

    public function test_answers_and_resends_clear_waiting_drafts(): void
    {
        $owner = $this->owner();
        $quote = $this->quoteFor($owner)->sent($this->sentAt)->create();
        $this->runAt(49);
        $this->assertSame(1, Nudge::where('status', NudgeStatus::Drafted)->count());

        $this->postJson("/api/public/quotes/{$quote->public_token}/accept")->assertOk();

        $this->assertSame(0, Nudge::where('status', NudgeStatus::Drafted)->count());
        $this->assertSame(NudgeStatus::Dismissed, Nudge::sole()->status);
    }

    public function test_the_timing_follows_the_business_settings(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->putJson('/api/settings/quoting', [
            'trade' => 'Hidraulik',
            'defaultValidityDays' => 10,
            'firstNudgeHours' => 24,
            'secondNudgeDays' => 3,
            'terms' => 'Pagesa në dorë.',
        ])->assertOk();
        $this->quoteFor($owner)->sent($this->sentAt)->create();

        $this->runAt(24);
        $this->assertSame(1, Nudge::count());
    }

    private function runAt(int $hoursAfterSending): void
    {
        Carbon::setTestNow($this->sentAt->copy()->addHours($hoursAfterSending));
        $this->artisan('quotes:follow-up')->assertSuccessful();
    }
}
