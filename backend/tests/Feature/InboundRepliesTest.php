<?php

namespace Tests\Feature;

use App\Models\InboundMessage;
use App\Models\OutboundMessage;
use App\Models\Quote;
use App\Quotes\QuoteStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MakesQuotes;
use Tests\TestCase;

class InboundRepliesTest extends TestCase
{
    use MakesQuotes, RefreshDatabase;

    public function test_po_accepts_the_latest_open_quote(): void
    {
        $owner = $this->owner();
        $customer = $this->customerOf($owner, ['phone' => '355681234567']);
        $older = $this->quoteFor($owner, $customer)->sent(now()->subDays(4))->create();
        $latest = $this->quoteFor($owner, $customer)->sent(now()->subDay(), QuoteStatus::Viewed)->create();

        $this->webhook('355681234567', 'Po!');

        $this->assertSame(QuoteStatus::Accepted, $latest->fresh()->status);
        $this->assertSame(QuoteStatus::Sent, $older->fresh()->status);
        $this->assertSame('WHATSAPP', $latest->fresh()->events->last()->channel);
        $this->assertSame('quote_accept', InboundMessage::sole()->handled_by);
        $this->assertEqualsCanonicalizing(
            ['quote_accepted_notice', 'quote_thanks'],
            OutboundMessage::withoutGlobalScope('organization')->pluck('template_key')->all(),
        );
    }

    public function test_jo_and_2_decline(): void
    {
        $owner = $this->owner();
        $customer = $this->customerOf($owner, ['phone' => '355681234567']);
        $quote = $this->quoteFor($owner, $customer)->sent()->create();

        $this->webhook('355681234567', '2');

        $this->assertSame(QuoteStatus::Declined, $quote->fresh()->status);
    }

    public function test_anything_else_is_a_question_on_the_latest_quote(): void
    {
        $owner = $this->owner();
        $customer = $this->customerOf($owner, ['phone' => '355681234567']);
        $quote = $this->quoteFor($owner, $customer)->sent(now()->subDays(2), QuoteStatus::Accepted)->create();

        $this->webhook('355681234567', 'Në çfarë ore vini të enjten?');

        $quote->refresh();
        $this->assertSame(QuoteStatus::Accepted, $quote->status);
        $this->assertSame('QUESTION', $quote->events->last()->type->value);
        $this->assertSame('quote_question_notice', OutboundMessage::withoutGlobalScope('organization')->sole()->template_key);
    }

    public function test_a_voice_note_from_the_business_phone_becomes_a_draft(): void
    {
        $owner = $this->owner();

        $this->webhook('355692124480', null, 'audio');

        $quote = Quote::withoutGlobalScope('organization')->sole();
        $this->assertSame(QuoteStatus::Draft, $quote->status);
        $this->assertSame(Quote::SOURCE_WHATSAPP, $quote->source);
        $this->assertSame($owner->organization_id, $quote->organization_id);
        $ready = OutboundMessage::withoutGlobalScope('organization')->sole();
        $this->assertSame('quote_draft_created', $ready->template_key);
        $this->assertStringContainsString('/quotes/'.$quote->id, $ready->body);
    }

    public function test_a_typed_quote_from_the_business_phone_is_read_into_lines(): void
    {
        $this->owner();

        $this->webhook('355692124480', 'Arben Kola, 069 234 5678, montim bojleri 80 litra 180 euro, punëdore 70');

        $quote = Quote::withoutGlobalScope('organization')->with('items', 'customer')->sole();
        $this->assertSame(25000, $quote->total_cents);
        $this->assertSame('Arben Kola', $quote->customer->name);
        $this->assertSame('business_draft', InboundMessage::sole()->handled_by);
    }

    public function test_strangers_and_small_talk_are_left_unhandled(): void
    {
        $this->owner();

        $this->webhook('355670000000', 'po');
        $this->webhook('355692124480', 'ok faleminderit');

        $this->assertSame(0, InboundMessage::where('handled', true)->count());
        $this->assertSame(0, Quote::withoutGlobalScope('organization')->count());
    }

    public function test_the_demo_simulator_goes_through_the_same_handlers(): void
    {
        $owner = $this->owner();
        $customer = $this->customerOf($owner, ['phone' => '355681234567']);
        $quote = $this->quoteFor($owner, $customer)->sent()->create();

        $this->actingAs($owner)->postJson('/api/dev/inbound', ['from' => '068 123 4567', 'body' => 'yes'])
            ->assertOk()
            ->assertJsonPath('inbound.handledBy', 'quote_accept')
            ->assertJsonPath('replies.0.templateKey', 'quote_thanks');
        $this->assertSame(QuoteStatus::Accepted, $quote->fresh()->status);
    }

    /** What Meta posts to the webhook for one message. */
    private function webhook(string $from, ?string $body, string $type = 'text'): void
    {
        $message = ['from' => $from, 'id' => 'wamid.'.uniqid(), 'timestamp' => (string) now()->timestamp, 'type' => $type];
        $message[$type] = $type === 'text' ? ['body' => $body] : ['id' => 'media-1', 'mime_type' => 'audio/ogg; codecs=opus'];

        $this->postJson('/api/webhooks/whatsapp', ['entry' => [['changes' => [['value' => ['messages' => [$message]]]]]]])->assertOk();
    }
}
