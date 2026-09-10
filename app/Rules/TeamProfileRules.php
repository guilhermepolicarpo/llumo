<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;

class TeamProfileRules
{
    /**
     * Get the validation rules used to validate a team profile.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public static function all(): array
    {
        return [
            'teamName' => static::name(),
            'logo' => static::logo(),
            ...static::address(),
        ];
    }

    /**
     * Get the validation rules used to validate team names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    public static function name(): array
    {
        return ['required', 'string', 'max:255', new TeamName];
    }

    /**
     * Get the validation rules used to validate team logos.
     *
     * SVG is intentionally excluded: logos are served from the application's own
     * origin, where a crafted SVG would run as same-origin script.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    public static function logo(): array
    {
        return ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=2000,max_height=2000'];
    }

    /**
     * Get the validation rules used to validate a Brazilian address.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public static function address(): array
    {
        return AddressRules::all();
    }
}
