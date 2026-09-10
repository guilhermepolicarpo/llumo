<?php

namespace App\Concerns;

use App\Rules\PostalCode;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasFormattedAddress
{
    /**
     * Get the model's address as a single display string.
     *
     * @return Attribute<string|null, never>
     */
    protected function formattedAddress(): Attribute
    {
        return Attribute::make(get: function (): ?string {
            $streetLine = collect([$this->street, $this->number])
                ->filter()
                ->implode(', ');

            $cityLine = $this->city && $this->state
                ? $this->city.'/'.$this->state->value
                : ($this->city ?? $this->state?->value);

            $address = collect([
                $streetLine,
                $this->complement,
                $this->district,
                $cityLine,
                $this->postal_code ? __('Postal code :code', ['code' => PostalCode::format($this->postal_code)]) : null,
            ])->filter()->implode(' — ');

            return $address === '' ? null : $address;
        });
    }
}
