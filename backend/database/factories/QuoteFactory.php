<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Quote;
use App\Quotes\ItemKind;
use App\Quotes\QuoteStatus;
use App\Quotes\QuoteTotals;
use App\Support\Tokens;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A quote with two lines (parts 85, labour 60). States put it where a test needs it:
 * Quote::factory()->sent(now()->subHours(50))->create().
 *
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    private static int $sequence = 0;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'organization_id' => fn (array $attributes) => Customer::withoutGlobalScope('organization')->find($attributes['customer_id'])->organization_id,
            'number' => sprintf('Q-%d-%04d', now()->year, ++self::$sequence),
            'title' => 'Ndërrim rubineti kuzhine',
            'status' => QuoteStatus::Draft,
            'proposed_for' => 'Të enjten në mëngjes',
            'validity_days' => 14,
            'public_token' => Tokens::urlToken(),
            'total_cents' => 14500,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Quote $quote) {
            $lines = [
                ['description' => 'Rubinet kuzhine me 2 zorra', 'qty' => 1, 'unit_price_cents' => 8500, 'kind' => ItemKind::Parts],
                ['description' => 'Punëdore', 'qty' => 1, 'unit_price_cents' => 6000, 'kind' => ItemKind::Labour],
            ];
            foreach ($lines as $position => $line) {
                $quote->items()->create([...$line, 'position' => $position, 'total_cents' => QuoteTotals::line($line['qty'], $line['unit_price_cents'])]);
            }
        });
    }

    public function sent(?DateTimeInterface $at = null, QuoteStatus $status = QuoteStatus::Sent): static
    {
        return $this->state(function () use ($at, $status) {
            $at ??= now();

            return [
                'status' => $status,
                'sent_at' => $at,
                'valid_until' => Carbon::instance($at)->tz('Europe/Tirane')->addDays(14)->toDateString(),
                'viewed_at' => $status === QuoteStatus::Viewed ? $at : null,
            ];
        });
    }
}
