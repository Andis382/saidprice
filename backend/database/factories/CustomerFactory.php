<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->name(),
            'phone' => '35569'.fake()->unique()->numerify('#######'),
            'address' => 'Rruga e Kavajës '.fake()->numberBetween(1, 200),
            'locale' => 'sq',
        ];
    }
}
