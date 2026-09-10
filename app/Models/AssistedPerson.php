<?php

namespace App\Models;

use App\Concerns\HasFormattedAddress;
use App\Enums\BrazilianState;
use Database\Factories\AssistedPersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property Carbon|null $birth_date
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $postal_code
 * @property string|null $street
 * @property string|null $number
 * @property string|null $complement
 * @property string|null $district
 * @property string|null $city
 * @property BrazilianState|null $state
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string|null $formatted_address
 * @property-read int|null $age
 * @property-read Team $team
 */
#[Fillable([
    'team_id',
    'name',
    'birth_date',
    'phone',
    'email',
    'postal_code',
    'street',
    'number',
    'complement',
    'district',
    'city',
    'state',
])]
class AssistedPerson extends Model
{
    /** @use HasFactory<AssistedPersonFactory> */
    use HasFactory, HasFormattedAddress, SoftDeletes;

    /**
     * Get the team this assisted person belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the assisted person's age in years, based on their birth date.
     *
     * @return Attribute<int|null, never>
     */
    protected function age(): Attribute
    {
        return Attribute::make(get: fn (): ?int => $this->birth_date?->diffInYears(today()));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'state' => BrazilianState::class,
        ];
    }
}
