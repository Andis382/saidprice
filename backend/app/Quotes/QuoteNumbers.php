<?php

namespace App\Quotes;

use App\Models\Organization;
use App\Models\Quote;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** "Q-2026-0042": per business, per year, no gaps from concurrent drafts. */
class QuoteNumbers
{
    /** Call inside a transaction: the organisation row is locked until it commits. */
    public function next(int $organizationId, CarbonInterface $at): string
    {
        Organization::whereKey($organizationId)->lockForUpdate()->first();
        $prefix = 'Q-'.$at->year.'-';
        $last = Quote::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->where('number', 'like', $prefix.'%')
            ->max(DB::raw('CAST(SUBSTRING(number FROM '.(strlen($prefix) + 1).') AS INTEGER)'));

        return $prefix.str_pad((string) ((int) $last + 1), 4, '0', STR_PAD_LEFT);
    }
}
