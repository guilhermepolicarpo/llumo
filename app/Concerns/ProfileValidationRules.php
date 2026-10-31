<?php

namespace App\Concerns;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, Closure|ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * Emails are stored in lowercase, so uniqueness is checked against the lowercased input.
     *
     * @return array<int, Closure|ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            function (string $attribute, mixed $value, Closure $fail) use ($userId): void {
                $isTaken = User::query()
                    ->where('email', Str::lower($value))
                    ->when($userId !== null, fn ($query) => $query->whereKeyNot($userId))
                    ->exists();

                if ($isTaken) {
                    $fail('validation.unique')->translate();
                }
            },
        ];
    }
}
