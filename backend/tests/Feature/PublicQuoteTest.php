<?php

namespace Tests\Feature;

use App\Models\OutboundMessage;
use App\Quotes\QuoteStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MakesQuotes;
use Tests\TestCase;

class PublicQuoteTest extends TestCase
{
    use MakesQuotes, RefreshDatabase;

    public function test_first_open_by_the_customer_marks_it_viewed(): void
    {
        $owner = $this->owner();
        $quote = $this->quoteFor($owner)->sent(now()->subHour())->create();

        $this->getJson("/api/public/quotes/{$quote->public_token}")->assertOk()
            ->assertJsonPath('number', $quote->number)
            ->assertJsonPath('business.name', 'Elektro-Hidro Beqiri')
            ->assertJsonPath('totalCents', 14500)
            ->assertJsonPath('canAnswer', true)
            ->assertJsonMissingPath('events');

        $quote->refresh();
        $this->assertSame(QuoteStatus::Viewed, $quote->status);
        $this->assertNotNull($quote->viewed_at);
        $this->assertSame(['VIEWED'], $quote->events->pluck('type.value')->all());

        $this->getJson("/api/public/quotes/{$quote->public_token}")->assertOk();
        $this->assertCount(1, $quote->fresh()->events);
    }

    public function test_the_business_opening_its_own_link_does_not_count(): void
    {
        $owner = $this->owner();
        $quote = $this->quoteFor($owner)->sent()->create();

        $this->actingAs($owner)->getJson("/api/public/quotes/{$quote->public_token}")->assertOk();

        $this->assertSame(QuoteStatus::Sent, $quote->fresh()->status);
    }

    public function test_drafts_and_unknown_tokens_are_not_found(): void
    {
        $owner = $this->owner();
        $draft = $this->quoteFor($owner)->create();

        $this->getJson("/api/public/quotes/{$draft->public_token}")->assertNotFound();
        $this->getJson('/api/public/quotes/not-a-token')->assertNotFound();
    }

    public function test_accepting_tells_the_business_and_thanks_the_customer(): void
    {
        $owner = $this->owner();
        $quote = $this->quoteFor($owner)->sent(now()->subDay(), QuoteStatus::Viewed)->create();

        $this->postJson("/api/public/quotes/{$quote->public_token}/accept", ['note' => 'Po, të enjten jam në shtëpi'])->assertOk()
            ->assertJsonPath('status', 'ACCEPTED')
            ->assertJsonPath('canAnswer', false);

        $quote->refresh();
        $this->assertNotNull($quote->responded_at);
        $this->assertSame('Po, të enjten jam në shtëpi', $quote->response_note);
        $messages = OutboundMessage::withoutGlobalScope('organization')->orderBy('id')->get();
        $this->assertSame(['quote_accepted_notice', 'quote_thanks'], $messages->pluck('template_key')->all());
        $this->assertSame('355692124480', $messages[0]->recipient);
        $this->assertStringContainsString('Pranuar:', $messages[0]->body);
        $this->assertSame($quote->customer->phone, $messages[1]->recipient);
        $this->assertStringContainsString('të enjten në mëngjes', $messages[1]->body);
    }

    public function test_declining_with_a_reason(): void
    {
        $owner = $this->owner();
        $quote = $this->quoteFor($owner)->sent()->create();

        $this->postJson("/api/public/quotes/{$quote->public_token}/decline", ['reason' => 'Shumë shtrenjtë'])->assertOk()
            ->assertJsonPath('status', 'DECLINED')
            ->assertJsonPath('responseNote', 'Shumë shtrenjtë');

        $notice = OutboundMessage::withoutGlobalScope('organization')->sole();
        $this->assertSame('quote_declined_notice', $notice->template_key);
        $this->assertStringContainsString('Arsyeja: Shumë shtrenjtë', $notice->body);
    }

    public function test_a_question_keeps_the_status_and_reaches_the_business(): void
    {
        $owner = $this->owner();
        $quote = $this->quoteFor($owner)->sent()->create();

        $this->postJson("/api/public/quotes/{$quote->public_token}/question", ['text' => 'A mund të vini të premten?'])->assertNoContent();
        $this->postJson("/api/public/quotes/{$quote->public_token}/question", ['text' => ''])->assertUnprocessable();

        $quote->refresh();
        $this->assertSame(QuoteStatus::Sent, $quote->status);
        $this->assertSame('A mund të vini të premten?', $quote->events->last()->body);
        $notice = OutboundMessage::withoutGlobalScope('organization')->sole();
        $this->assertSame('quote_question_notice', $notice->template_key);
        $this->assertStringContainsString('wa.me/'.$quote->customer->phone, $notice->body);
    }

    public function test_answered_or_expired_quotes_cannot_be_answered_again(): void
    {
        $owner = $this->owner();
        $expired = $this->quoteFor($owner)->sent(now()->subDays(30), QuoteStatus::Expired)->create();
        $accepted = $this->quoteFor($owner)->sent(now()->subDays(2), QuoteStatus::Accepted)->create();

        $this->postJson("/api/public/quotes/{$expired->public_token}/accept")->assertStatus(409);
        $this->postJson("/api/public/quotes/{$accepted->public_token}/decline")->assertStatus(409);
        $this->getJson("/api/public/quotes/{$expired->public_token}")->assertOk()->assertJsonPath('canAnswer', false);
    }

    public function test_the_customer_can_download_the_pdf(): void
    {
        $owner = $this->owner();
        $quote = $this->quoteFor($owner)->sent()->create();

        $response = $this->get("/api/public/quotes/{$quote->public_token}/pdf")->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
