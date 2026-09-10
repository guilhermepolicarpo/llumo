<?php

namespace App\Actions\AssistedPeople;

use App\Concerns\NormalizesAssistedPersonAttributes;
use App\Models\AssistedPerson;

class UpdateAssistedPerson
{
    use NormalizesAssistedPersonAttributes;

    /**
     * Update an existing assisted person's profile.
     *
     * @param  array{name: string, birth_date?: ?string, phone?: ?string, email?: ?string,
     *              postal_code?: ?string, street?: ?string, number?: ?string, complement?: ?string,
     *              district?: ?string, city?: ?string, state?: ?string}  $attributes
     */
    public function handle(AssistedPerson $assistedPerson, array $attributes): AssistedPerson
    {
        $assistedPerson->update($this->attributesToPersist($attributes));

        return $assistedPerson;
    }
}
