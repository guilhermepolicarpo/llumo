<?php

namespace App\Rules;

use App\Enums\BrazilianState;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class AddressRules
{
    /**
     * Get the validation rules used to validate a Brazilian address.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public static function all(): array
    {
        return [
            'postalCode' => ['nullable', 'string', new PostalCode],
            'street' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', Rule::enum(BrazilianState::class)],
        ];
    }
}
