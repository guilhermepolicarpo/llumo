<?php

namespace App\Concerns;

use App\Rules\Phone;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasFormattedPhone
{
    /**
     * Get the model's phone number formatted for display.
     *
     * @return Attribute<string|null, never>
     */
    protected function formattedPhone(): Attribute
    {
        return Attribute::make(get: fn (): ?string => Phone::format($this->phone) ?: null);
    }
}
