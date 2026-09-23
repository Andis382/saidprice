<?php

namespace Tests\Feature;

use App\Models\OutboundMessage;
use App\Models\Quote;
use App\Quotes\QuoteStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\MakesQuotes;
use Tests\TestCase;

class QuoteLifecycleTest extends TestCase
{
    use MakesQuotes, RefreshDatabase;

    public function test_the_editor_replaces_lines_and_recomputes_the_total(): void
    {
        $owner = $this->owner();
        $quote = $this->quoteFor($owner)->create();

        $this->actingAs($owner)->putJson("/api/quotes/{$quote->id}", [
            'title' => 'Montim bojleri 80 L',
            'customer' => ['id' => $quote->customer_id, 'name' => 'Arta Kola', 'phone' => '068 123 4567', 'address' => 'Rruga Qemal Stafa 9', 'locale' => 'en'],
            'items' => [
                ['description' => 'Bojler 80 L', 'qty' => 1, 'unit' => null, 'unitPriceCents' => 18900, 'kind' => 'PARTS'],
                ['description' => 'Punëdore', 'qty' => 1.5, 'unit' => 'orë', 'unitPriceCents' => 2333, 'kind' => 'LABOUR'],
            ],
            'proposedFor' => 'E shtunë paradite',
            'validityDays' => 7,
            'notes' => null,
        ])->assertOk()
            ->assertJsonPath('totalCents', 18900 + 3500)
            ->assertJsonPath('totals.labour', 3500)
            ->assertJsonPath('items.1.totalCents', 3500)
            ->assertJsonPath('customer.name', 'Arta Kola')
            ->assertJsonPath('customer.phone', '355681234567')
            ->assertJsonPath('customer.locale', 'en')
            ->assertJsonPath('validityDays', 7);
    }

    public function test_editor_errors_are_per_field(): void
    {
        $owner = $this->owner();
        $quote = $this->quoteFor($owner)->create();

        $this->actingAs($owner)->putJson("/api/quotes/{$quote->id}", [
            'customer' => ['name' => 'Arta', 'phone' => '12'],
            'items' => [['description' => '', 'qty' => 0, 'unitPriceCents' => -5, 'kind' => 'NAILS']],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.description', 'items.0.qty', 'items.0.unitPriceCents', 'items.0.kind']);

        $this->actingAs($owner)->putJson("/api/quotes/{$quote->id}", ['customer' => ['name' => 'Arta', 'phone' => '12']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer.phone']);
    }

    public function test_sending_writes_the_quote_to_the_customer_in_their_language(): void
    {
        $owner = $this->owner();
        $customer = $this->customerOf($owner, ['name' => 'Mark Evans', 'locale' => 'en']);
        $quote = $this->quoteFor($owner, $customer)->create(['title' => 'Replace kitchen tap', 'proposed_for' => 'Thursday morning']);
        Carbon::setTestNow('2026-09-21 09:00:00');

        $response = $this->actingAs($owner)->postJson("/api/quotes/{$quote->id}/send")->assertOk()
            ->assertJsonPath('quote.status', 'SENT')
            ->assertJsonPath('quote.validUntil', '2026-10-05')
            ->assertJsonPath('message.templateKey', 'quote_sent')
            ->assertJsonPath('message.status', 'SIMULATED');

        $body = $response->json('message.body');
        $this->assertStringContainsString('Hello Mark, here is your written quote '.$quote->number, $body);
        $this->assertStringContainsString('€145', $body);
        $this->assertStringContainsString('Proposed: Thursday morning', $body);
        $this->assertStringContainsString('/q/'.$quote->public_token, $body);
        $this->assertStringContainsString('wa.me/'.$customer->phone, $response->json('message.waMeUrl'));
    }

    public function test_a_quote_without_a_phone_or_lines_is_not_sent(): void
    {
        $owner = $this->owner();
        $quote = $this->quoteFor($owner, $this->customerOf($owner, ['phone' => null]))->create();
        $quote->items()->delete();
        $quote->update(['total_cents' => 0]);

        $this->actingAs($owner)->postJson("/api/quotes/{$quote->id}/send")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer.phone', 'items']);
        $this->assertSame(QuoteStatus::Draft, $quote->fresh()->status);
        $this->assertSame(0, OutboundMessage::count());
    }

    public function test_a_customer_known_only_by_number_needs_a_name_before_sending(): void
    {
        $owner = $this->owner();
        $quote = $this->quoteFor($owner, $this->customerOf($owner, ['name' => '+355685551234', 'phone' => '355685551234']))->create();

        $errors = $this->actingAs($owner)->postJson("/api/quotes/{$quote->id}/send")
            ->assertUnprocessable()
            ->json('errors');

        $this->assertSame(['Add the customer’s name so the message can greet them.'], $errors['customer.name']);
    }

    public function test_resending_an_expired_quote_renews_it(): void
    {
        $owner = $this->owner();
        $quote = $this->quoteFor($owner)->sent(now()->subDays(20), QuoteStatus::Expired)->create();

        $this->actingAs($owner)->postJson("/api/quotes/{$quote->id}/resend")->assertOk()
            ->assertJsonPath('quote.status', 'SENT')
            ->assertJsonPath('quote.validUntil', now()->tz('Europe/Tirane')->addDays(14)->toDateString())
            ->assertJsonPath('quote.events.0.type', 'RESENT');
    }

    public function test_won_by_phone_accepts_an_open_quote_and_lost_closes_it(): void
    {
        $owner = $this->owner();
        $open = $this->quoteFor($owner)->sent(now()->subDay())->create();
        $viewed = $this->quoteFor($owner)->sent(now()->subDay(), QuoteStatus::Viewed)->create();

        $this->actingAs($owner)->postJson("/api/quotes/{$open->id}/outcome", ['outcome' => 'WON'])->assertOk()
            ->assertJsonPath('status', 'ACCEPTED')
            ->assertJsonPath('outcome', 'WON');

        $this->actingAs($owner)->postJson("/api/quotes/{$viewed->id}/outcome", ['outcome' => 'LOST', 'reason' => 'Went with someone cheaper'])->assertOk()
            ->assertJsonPath('status', 'DECLINED')
            ->assertJsonPath('outcome', 'LOST')
            ->assertJsonPath('lostReason', 'Went with someone cheaper');
    }

    public function test_answered_quotes_are_locked_and_only_drafts_can_be_deleted(): void
    {
        $owner = $this->owner();
        $accepted = $this->quoteFor($owner)->sent(now()->subDay(), QuoteStatus::Accepted)->create();
        $draft = $this->quoteFor($owner)->create();

        $this->actingAs($owner)->putJson("/api/quotes/{$accepted->id}", ['title' => 'Cheaper now'])->assertStatus(409);
        $this->actingAs($owner)->deleteJson("/api/quotes/{$accepted->id}")->assertStatus(409);
        $this->actingAs($owner)->deleteJson("/api/quotes/{$draft->id}")->assertNoContent();
        $this->assertNull(Quote::find($draft->id));
    }

    public function test_duplicate_makes_a_new_draft_with_the_same_lines(): void
    {
        $owner = $this->owner();
        $quote = $this->quoteFor($owner)->sent(now()->subDays(3), QuoteStatus::Declined)->create();

        $this->actingAs($owner)->postJson("/api/quotes/{$quote->id}/duplicate")->assertCreated()
            ->assertJsonPath('status', 'DRAFT')
            ->assertJsonPath('source', 'DUPLICATE')
            ->assertJsonPath('customer.id', $quote->customer_id)
            ->assertJsonPath('totalCents', 14500)
            ->assertJsonPath('capture.duplicatedFrom', $quote->number)
            ->assertJsonCount(2, 'items');
    }

    public function test_the_board_groups_by_status_with_counts_and_value(): void
    {
        $owner = $this->owner();
        $this->quoteFor($owner)->create();
        $this->quoteFor($owner)->sent()->create();
        $this->quoteFor($owner)->sent(now()->subDays(20), QuoteStatus::Silent)->create();
        $this->quoteFor($owner)->sent(now()->subDays(30), QuoteStatus::Expired)->create(['total_cents' => 10000]);

        $columns = collect($this->actingAs($owner)->getJson('/api/quotes')->assertOk()->json('columns'))->keyBy('key');

        $this->assertSame(['DRAFT', 'SENT', 'VIEWED', 'ACCEPTED', 'DECLINED', 'SILENT'], $columns->keys()->all());
        $this->assertSame(1, $columns['DRAFT']['count']);
        $this->assertSame(2, $columns['SILENT']['count']);
        $this->assertSame(24500, $columns['SILENT']['totalCents']);
    }

    public function test_search_finds_by_customer_number_and_phone(): void
    {
        $owner = $this->owner();
        $arta = $this->customerOf($owner, ['name' => 'Arta Kola', 'phone' => '355681234567']);
        $this->quoteFor($owner, $arta)->create(['title' => 'Prizë e re']);
        $this->quoteFor($owner)->create(['title' => 'Ndriçim LED']);

        $count = fn (string $q) => collect($this->actingAs($owner)->getJson('/api/quotes?q='.urlencode($q))->json('columns'))->sum('count');

        $this->assertSame(1, $count('arta'));
        $this->assertSame(1, $count('123 4567'));
        $this->assertSame(1, $count('led'));
        $this->assertSame(2, $count(''));
    }

    public function test_pdf_downloads(): void
    {
        $owner = $this->owner();
        $quote = $this->quoteFor($owner)->sent()->create();

        $response = $this->actingAs($owner)->get("/api/quotes/{$quote->id}/pdf")->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
