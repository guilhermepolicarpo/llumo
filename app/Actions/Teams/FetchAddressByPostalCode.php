<?php

namespace App\Actions\Teams;

use App\Concerns\NormalizesBlankStrings;
use App\Rules\PostalCode;
use Illuminate\Support\Facades\Http;
use Throwable;

class FetchAddressByPostalCode
{
    use NormalizesBlankStrings;

    /**
     * Look up a Brazilian address by postal code, returning null when unavailable.
     *
     * @return array{street: ?string, district: ?string, city: ?string, state: ?string}|null
     */
    public function handle(string $postalCode): ?array
    {
        $digits = PostalCode::digits($postalCode);

        if ($digits === null) {
            return null;
        }

        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->get(rtrim((string) config('services.brasil_api.url'), '/').'/cep/v2/'.$digits);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return [
            'street' => $this->blankToNull($response->json('street')),
            'district' => $this->blankToNull($response->json('neighborhood')),
            'city' => $this->blankToNull($response->json('city')),
            'state' => $this->blankToNull($response->json('state')),
        ];
    }
}
