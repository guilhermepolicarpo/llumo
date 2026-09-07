<?php

namespace App\Livewire\Forms;

use App\Actions\Addresses\LookupPostalCode;
use App\Enums\BrazilianState;
use App\Models\AssistedPerson;
use App\Models\Team;
use App\Rules\DigitCount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Livewire\Form;

class AssistedPersonForm extends Form
{
    public string $name = '';

    public string $birth_date = '';

    public string $email = '';

    public string $phone = '';

    public string $postal_code = '';

    public string $street = '';

    public string $number = '';

    public string $complement = '';

    public string $district = '';

    public string $city = '';

    public string $state = '';

    public ?string $city_ibge_code = null;

    /**
     * Fill the form with an existing assisted person.
     */
    public function setAssistedPerson(AssistedPerson $assistedPerson): void
    {
        $this->name = $assistedPerson->name;
        $this->birth_date = $assistedPerson->birth_date->format('Y-m-d');
        $this->email = (string) $assistedPerson->email;
        $this->phone = (string) $assistedPerson->phone;
        $this->postal_code = (string) $assistedPerson->postal_code;
        $this->street = (string) $assistedPerson->street;
        $this->number = (string) $assistedPerson->number;
        $this->complement = (string) $assistedPerson->complement;
        $this->district = (string) $assistedPerson->district;
        $this->city = (string) $assistedPerson->city;
        $this->state = (string) $assistedPerson->state?->value;
        $this->city_ibge_code = $assistedPerson->city_ibge_code;
    }

    /**
     * Resolve the address whenever the postal code changes.
     */
    public function updatedPostalCode(): void
    {
        $this->fillAddressFromPostalCode();
    }

    /**
     * Validate the form and store a new assisted person for the given team.
     */
    public function store(Team $team): AssistedPerson
    {
        $this->validate();

        return $team->assistedPeople()->create($this->persistableAttributes());
    }

    /**
     * Validate the form and update the given assisted person.
     */
    public function update(AssistedPerson $assistedPerson): AssistedPerson
    {
        $this->validate();

        $assistedPerson->update($this->persistableAttributes());

        return $assistedPerson;
    }

    /**
     * Get the validation rules used to validate assisted people.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20', new DigitCount([10, 11], __('The phone must have 10 or 11 digits.'))],
            'postal_code' => ['nullable', 'string', 'max:9', new DigitCount([8], __('The postal code must have 8 digits.'))],
            'street' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', Rule::enum(BrazilianState::class)],
        ];
    }

    /**
     * Fill the address from the postal code lookup.
     *
     * A lookup that resolves nothing, or resolves only part of the address,
     * leaves the remaining fields untouched so they stay editable by hand.
     */
    protected function fillAddressFromPostalCode(): void
    {
        $address = app(LookupPostalCode::class)->handle($this->postal_code);

        if ($address === null) {
            return;
        }

        foreach (['street', 'district', 'city', 'state'] as $field) {
            if (filled($address[$field])) {
                $this->{$field} = (string) $address[$field];
            }
        }

        $this->city_ibge_code = $address['city_ibge_code'];
    }

    /**
     * Get the attributes ready to be persisted.
     *
     * Blank optional fields are stored as null rather than as empty strings, so
     * casts such as the state enum never receive an empty value.
     *
     * @return array<string, mixed>
     */
    protected function persistableAttributes(): array
    {
        return array_map(
            fn (mixed $value): mixed => is_string($value) && trim($value) === '' ? null : $value,
            $this->all(),
        );
    }
}
