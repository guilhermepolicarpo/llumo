<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;

class AssistedPersonRules
{
    /**
     * Get the validation rules used to validate an assisted person.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public static function all(): array
    {
        return [
            'name' => static::name(),
            'birthDate' => static::birthDate(),
            'phone' => static::phone(),
            'email' => static::email(),
            ...AddressRules::all(),
        ];
    }

    /**
     * Get the validation rules used to validate an assisted person's name.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    public static function name(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate an assisted person's birth date.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    public static function birthDate(): array
    {
        return ['nullable', 'date', 'before_or_equal:today'];
    }

    /**
     * Get the validation rules used to validate an assisted person's phone number.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    public static function phone(): array
    {
        return ['nullable', 'string', new Phone];
    }

    /**
     * Get the validation rules used to validate an assisted person's email.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    public static function email(): array
    {
        return ['nullable', 'string', 'email', 'max:255'];
    }
}
