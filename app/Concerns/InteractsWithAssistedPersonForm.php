<?php

namespace App\Concerns;

trait InteractsWithAssistedPersonForm
{
    public string $name = '';

    public string $birthDate = '';

    public string $phone = '';

    public string $email = '';

    /**
     * Map validated form data to the attribute shape expected by the create/update actions.
     *
     * @param  array<string, mixed>  $validated
     * @return array{name: string, birth_date: ?string, phone: ?string, email: ?string,
     *              postal_code: ?string, street: ?string, number: ?string, complement: ?string,
     *              district: ?string, city: ?string, state: ?string}
     */
    protected function assistedPersonAttributes(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'birth_date' => $validated['birthDate'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'postal_code' => $validated['postalCode'],
            'street' => $validated['street'],
            'number' => $validated['number'],
            'complement' => $validated['complement'],
            'district' => $validated['district'],
            'city' => $validated['city'],
            'state' => $validated['state'],
        ];
    }
}
