<?php

namespace App\Models;

use App\Enums\BrazilianState;
use Database\Factories\AssistedPersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property Carbon $birth_date
 * @property string|null $email
 * @property string $phone
 * @property string|null $postal_code
 * @property string|null $street
 * @property string|null $number
 * @property string|null $complement
 * @property string|null $district
 * @property string|null $city
 * @property BrazilianState|null $state
 * @property string|null $city_ibge_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read int $age
 * @property-read string $streetLine
 * @property-read string $cityLine
 */
#[Fillable([
    'team_id',
    'name',
    'birth_date',
    'email',
    'phone',
    'postal_code',
    'street',
    'number',
    'complement',
    'district',
    'city',
    'state',
    'city_ibge_code',
])]
class AssistedPerson extends Model
{
    /** @use HasFactory<AssistedPersonFactory> */
    use HasFactory, SoftDeletes;

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
     * Filter the assisted people matching the given term by name, email or phone.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $digits = (string) preg_replace('/\D/', '', $term);

        $query->where(function (Builder $query) use ($term, $digits) {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");

            if ($digits !== '') {
                $query->orWhere('phone', 'like', "%{$digits}%");
            }
        });
    }

    /**
     * Determine whether any part of the address has been filled in.
     */
    public function hasAddress(): bool
    {
        return $this->streetLine !== '' || $this->cityLine !== '';
    }

    /**
     * Normalize the name by trimming and collapsing whitespace.
     */
    protected function name(): Attribute
    {
        return Attribute::set(fn (?string $value): string => Str::squish((string) $value));
    }

    /**
     * Normalize the city by trimming, collapsing whitespace and title casing it.
     */
    protected function city(): Attribute
    {
        return Attribute::set(function (?string $value): ?string {
            $city = Str::squish((string) $value);

            return $city === '' ? null : Str::title($city);
        });
    }

    /**
     * Store the phone as digits only.
     */
    protected function phone(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => $this->digitsOrNull($value));
    }

    /**
     * Store the postal code as digits only.
     */
    protected function postalCode(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => $this->digitsOrNull($value));
    }

    /**
     * Get the age in whole years.
     *
     * @return Attribute<int, never>
     */
    protected function age(): Attribute
    {
        return Attribute::get(fn (): int => $this->birth_date->age);
    }

    /**
     * Get the street, number and district as a single line.
     *
     * @return Attribute<string, never>
     */
    protected function streetLine(): Attribute
    {
        return Attribute::get(fn (): string => $this->joinFilled([
            $this->joinFilled([$this->street, $this->number], ', '),
            $this->district,
        ], ' - '));
    }

    /**
     * Get the city and state as a single line.
     *
     * @return Attribute<string, never>
     */
    protected function cityLine(): Attribute
    {
        return Attribute::get(fn (): string => $this->joinFilled([
            $this->city,
            $this->state?->value,
        ], ' - '));
    }

    /**
     * Join the given parts, discarding the empty ones so the separator never stands alone.
     *
     * @param  array<int, string|null>  $parts
     */
    protected function joinFilled(array $parts, string $separator): string
    {
        return collect($parts)
            ->map(fn (?string $part): string => trim((string) $part))
            ->filter(fn (string $part): bool => $part !== '')
            ->implode($separator);
    }

    /**
     * Reduce the value to its digits, returning null when nothing is left.
     */
    protected function digitsOrNull(?string $value): ?string
    {
        $digits = (string) preg_replace('/\D/', '', (string) $value);

        return $digits === '' ? null : $digits;
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
