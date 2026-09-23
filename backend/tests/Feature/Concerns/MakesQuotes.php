<?php

namespace Tests\Feature\Concerns;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\Quote;
use App\Models\User;
use Database\Factories\QuoteFactory;

trait MakesQuotes
{
    /** A plumber in Tirana with a business phone, quoting in euro, Albanian by default. */
    protected function owner(array $organization = []): User
    {
        $org = Organization::factory()->create([
            'name' => 'Elektro-Hidro Beqiri',
            'phone' => '355692124480',
            'locale' => 'sq',
            'timezone' => 'Europe/Tirane',
            'currency' => 'EUR',
            ...$organization,
        ]);

        return User::factory()->for($org)->create(['role' => User::OWNER]);
    }

    protected function customerOf(User $user, array $attributes = []): Customer
    {
        return Customer::factory()->for($user->organization)->create($attributes);
    }

    protected function quoteFor(User $user, ?Customer $customer = null): QuoteFactory
    {
        return Quote::factory()->for($customer ?? $this->customerOf($user));
    }

    /** A different browser: no session, no signed-in user. */
    protected function asVisitor(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
    }
}
