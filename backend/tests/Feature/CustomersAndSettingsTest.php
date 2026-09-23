<?php

namespace Tests\Feature;

use App\Models\User;
use App\Quotes\QuoteOutcome;
use App\Quotes\QuoteStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MakesQuotes;
use Tests\TestCase;

class CustomersAndSettingsTest extends TestCase
{
    use MakesQuotes, RefreshDatabase;

    public function test_customer_history_counts_what_was_quoted_and_won(): void
    {
        $owner = $this->owner();
        $arta = $this->customerOf($owner, ['name' => 'Arta Kola']);
        $this->quoteFor($owner, $arta)->sent(now()->subDays(20), QuoteStatus::Accepted)->create(['outcome' => QuoteOutcome::Won]);
        $this->quoteFor($owner, $arta)->sent(now()->subDays(9), QuoteStatus::Declined)->create();
        $this->quoteFor($owner, $arta)->create();

        $this->actingAs($owner)->getJson('/api/customers')->assertOk()
            ->assertJsonPath('0.quotesCount', 2)
            ->assertJsonPath('0.wonCount', 1)
            ->assertJsonPath('0.wonCents', 14500)
            ->assertJsonPath('0.quotedCents', 29000);

        $this->actingAs($owner)->getJson("/api/customers/{$arta->id}")->assertOk()
            ->assertJsonCount(3, 'quotes')
            ->assertJsonPath('quotes.0.status', 'DRAFT');
    }

    public function test_adding_a_customer_checks_the_phone(): void
    {
        $owner = $this->owner();
        $this->customerOf($owner, ['name' => 'Arta Kola', 'phone' => '355681234567']);

        $this->actingAs($owner)->postJson('/api/customers', ['name' => 'Ilir Dervishi', 'phone' => '0691112222', 'locale' => 'sq'])
            ->assertCreated()->assertJsonPath('phone', '355691112222');
        $this->actingAs($owner)->postJson('/api/customers', ['name' => 'Arta K.', 'phone' => '068 123 4567', 'locale' => 'sq'])
            ->assertUnprocessable()->assertJsonPath('errors.phone.0', 'This number already belongs to Arta Kola.');
        $this->actingAs($owner)->postJson('/api/customers', ['name' => 'Ilir', 'phone' => '123', 'locale' => 'sq'])
            ->assertUnprocessable()->assertJsonValidationErrors(['phone']);
    }

    public function test_quoting_settings_have_defaults_and_only_the_owner_changes_them(): void
    {
        $owner = $this->owner();
        $assistant = User::factory()->for($owner->organization)->assistant()->create();

        $this->actingAs($assistant)->getJson('/api/settings/quoting')->assertOk()
            ->assertJsonPath('defaultValidityDays', 14)
            ->assertJsonPath('firstNudgeHours', 48)
            ->assertJsonPath('secondNudgeDays', 5);
        $this->actingAs($assistant)->putJson('/api/settings/quoting', ['defaultValidityDays' => 7, 'firstNudgeHours' => 24, 'secondNudgeDays' => 3])
            ->assertForbidden();

        $this->asVisitor();
        $this->actingAs($owner)->putJson('/api/settings/quoting', ['defaultValidityDays' => 0, 'firstNudgeHours' => 2, 'secondNudgeDays' => 90])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['defaultValidityDays', 'firstNudgeHours', 'secondNudgeDays']);
        $this->actingAs($owner)->putJson('/api/settings/quoting', ['trade' => 'Hidraulik & elektricist', 'defaultValidityDays' => 10, 'firstNudgeHours' => 24, 'secondNudgeDays' => 4, 'terms' => 'Pagesa në dorë.'])
            ->assertOk()
            ->assertJsonPath('trade', 'Hidraulik & elektricist')
            ->assertJsonPath('defaultValidityDays', 10);
    }

    public function test_an_assistant_can_draft_and_send(): void
    {
        $owner = $this->owner();
        $assistant = User::factory()->for($owner->organization)->assistant()->create();
        $quote = $this->quoteFor($owner)->create();

        $this->actingAs($assistant)->postJson("/api/quotes/{$quote->id}/send")->assertOk()->assertJsonPath('quote.status', 'SENT');
        $this->actingAs($assistant)->postJson('/api/quotes', ['transcript' => 'riparim rrjedhjeje 40 euro'])->assertCreated();
    }
}
