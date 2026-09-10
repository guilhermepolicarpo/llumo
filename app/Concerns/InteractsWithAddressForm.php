<?php

namespace App\Concerns;

use App\Actions\Teams\FetchAddressByPostalCode;
use App\Enums\BrazilianState;
use Livewire\Attributes\Computed;

trait InteractsWithAddressForm
{
    public string $postalCode = '';

    public string $street = '';

    public string $number = '';

    public string $complement = '';

    public string $district = '';

    public string $city = '';

    public string $state = '';

    /**
     * Fill the blank address fields from the postal code lookup.
     */
    public function updatedPostalCode(string $value): void
    {
        $address = app(FetchAddressByPostalCode::class)->handle($value);

        if ($address === null) {
            return;
        }

        foreach (['street', 'district', 'city', 'state'] as $field) {
            if ($this->{$field} === '' && $address[$field] !== null) {
                $this->{$field} = $address[$field];
            }
        }
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function states(): array
    {
        return BrazilianState::options();
    }
}
