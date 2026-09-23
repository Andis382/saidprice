<?php

namespace Tests\Feature;

use App\Models\Nudge;
use App\Quotes\NudgeStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MakesQuotes;
use Tests\TestCase;

/** One business never sees or touches another's quotes, customers or follow-ups. */
class TenantIsolationTest extends TestCase
{
    use MakesQuotes, RefreshDatabase;

    public function test_quotes_of_another_business_are_invisible(): void
    {
        $mine = $this->owner();
        $theirs = $this->owner(['name' => 'Termo Shkodra']);
        $foreign = $this->quoteFor($theirs)->sent()->create();
        $this->quoteFor($mine)->create();

        $this->actingAs($mine);
        $this->getJson("/api/quotes/{$foreign->id}")->assertNotFound();
        $this->putJson("/api/quotes/{$foreign->id}", ['title' => 'x'])->assertNotFound();
        $this->postJson("/api/quotes/{$foreign->id}/resend")->assertNotFound();
        $this->postJson("/api/quotes/{$foreign->id}/outcome", ['outcome' => 'LOST'])->assertNotFound();
        $this->postJson("/api/quotes/{$foreign->id}/duplicate")->assertNotFound();
        $this->deleteJson("/api/quotes/{$foreign->id}")->assertNotFound();
        $this->get("/api/quotes/{$foreign->id}/pdf")->assertNotFound();

        $board = collect($this->getJson('/api/quotes')->json('columns'));
        $this->assertSame(1, $board->sum('count'));
    }

    public function test_customers_and_follow_ups_of_another_business_are_invisible(): void
    {
        $mine = $this->owner();
        $theirs = $this->owner(['name' => 'Termo Shkodra']);
        $foreignCustomer = $this->customerOf($theirs, ['name' => 'Besa Gjoka']);
        $foreignQuote = $this->quoteFor($theirs, $foreignCustomer)->sent(now()->subDays(3))->create();
        $nudge = Nudge::create(['organization_id' => $theirs->organization_id, 'quote_id' => $foreignQuote->id, 'sequence' => 1, 'body' => 'Përshëndetje', 'status' => NudgeStatus::Drafted]);
        $this->customerOf($mine, ['name' => 'Arta Kola']);

        $this->actingAs($mine);
        $this->getJson('/api/customers')->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Arta Kola');
        $this->getJson("/api/customers/{$foreignCustomer->id}")->assertNotFound();
        $this->putJson("/api/customers/{$foreignCustomer->id}", ['name' => 'x', 'locale' => 'sq'])->assertNotFound();
        $this->getJson('/api/nudges')->assertOk()->assertJsonCount(0, 'pending');
        $this->postJson("/api/nudges/{$nudge->id}/send")->assertNotFound();
        $this->getJson('/api/overview')->assertOk()->assertJsonPath('followUps', 0);

        // Nor can a quote be pointed at their customer.
        $mineQuote = $this->quoteFor($mine)->create();
        $this->putJson("/api/quotes/{$mineQuote->id}", ['customer' => ['id' => $foreignCustomer->id, 'name' => 'Besa']])->assertNotFound();
        $this->postJson('/api/quotes', ['customerId' => $foreignCustomer->id])->assertNotFound();
    }
}
