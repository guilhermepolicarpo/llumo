<?php

namespace App\Concerns;

use App\Rules\Phone;
use App\Rules\PostalCode;

trait NormalizesAssistedPersonAttributes
{
    use NormalizesBlankStrings;

    /**
     * Build the assisted person attribute list ready for persistence.
     *
     * @param  array{name: string, birth_date?: ?string, phone?: ?string, email?: ?string,
     *              postal_code?: ?string, street?: ?string, number?: ?string, complement?: ?string,
     *              district?: ?string, city?: ?string, state?: ?string}  $attributes
     * @return array<string, mixed>
     */
    protected function attributesToPersist(array $attributes): array
    {
        return [
            'name' => $attributes['name'],
            'birth_date' => $this->blankToNull($attributes['birth_date'] ?? null),
            'phone' => Phone::digits($attributes['phone'] ?? null),
            'email' => $this->blankToNull($attributes['email'] ?? null),
            'postal_code' => PostalCode::digits($attributes['postal_code'] ?? null),
            'street' => $this->blankToNull($attributes['street'] ?? null),
            'number' => $this->blankToNull($attributes['number'] ?? null),
            'complement' => $this->blankToNull($attributes['complement'] ?? null),
            'district' => $this->blankToNull($attributes['district'] ?? null),
            'city' => $this->blankToNull($attributes['city'] ?? null),
            'state' => $this->blankToNull($attributes['state'] ?? null),
        ];
    }
}
