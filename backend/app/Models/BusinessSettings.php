<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** How one business quotes: default validity, follow-up timing, standard terms. */
class BusinessSettings extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'trade', 'default_validity_days', 'first_nudge_hours', 'second_nudge_days', 'terms'];

    protected function casts(): array
    {
        return [
            'default_validity_days' => 'integer',
            'first_nudge_hours' => 'integer',
            'second_nudge_days' => 'integer',
        ];
    }

    public static function for(Organization $organization): self
    {
        return static::withoutGlobalScope('organization')->firstOrCreate(
            ['organization_id' => $organization->id],
            [
                'default_validity_days' => 14,
                'first_nudge_hours' => 48,
                'second_nudge_days' => 5,
                'terms' => trans('quotes.default_terms', [], $organization->locale),
            ],
        );
    }

    public function toApi(): array
    {
        return [
            'trade' => $this->trade,
            'defaultValidityDays' => $this->default_validity_days,
            'firstNudgeHours' => $this->first_nudge_hours,
            'secondNudgeDays' => $this->second_nudge_days,
            'terms' => $this->terms,
        ];
    }
}
