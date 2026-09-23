<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Quotes\FollowUps\FollowUps;
use Illuminate\Console\Command;

/** Hourly: drafts follow-ups for unanswered quotes, marks silent and expired ones. Sends nothing. */
class FollowUpQuotes extends Command
{
    protected $signature = 'quotes:follow-up';

    protected $description = 'Draft follow-ups for unanswered quotes and close silent or expired ones';

    public function handle(FollowUps $followUps): int
    {
        $totals = ['drafted' => 0, 'silent' => 0, 'expired' => 0];
        Organization::query()->orderBy('id')->each(function (Organization $organization) use ($followUps, &$totals) {
            foreach ($followUps->run($organization) as $key => $count) {
                $totals[$key] += $count;
            }
        });
        $this->info("Drafted {$totals['drafted']} follow-ups, {$totals['silent']} quotes went silent, {$totals['expired']} expired.");

        return self::SUCCESS;
    }
}
