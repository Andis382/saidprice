<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Someone the business quotes for. Messages go out in their language. */
class Customer extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    private const TITLES = ['znj.', 'z.', 'mrs', 'mrs.', 'mr', 'mr.', 'ms', 'ms.', 'miss', 'dr', 'dr.', 'zonja', 'zoti'];

    protected $fillable = ['organization_id', 'name', 'phone', 'address', 'locale'];

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /** "Znj. Hoxha" stays as it is; "Arta Kola" becomes "Arta" in a friendly message. */
    public function greetingName(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        if (count($parts) > 1 && ! in_array(mb_strtolower($parts[0]), self::TITLES, true)) {
            return $parts[0];
        }

        return trim($this->name);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'locale' => $this->locale,
        ];
    }
}
