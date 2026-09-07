<?php

namespace App\Actions\Addresses;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class LookupPostalCode
{
    /**
     * How long a resolved postal code stays cached. Postal code data is static,
     * and both upstream services are free community projects.
     */
    protected const CACHE_TTL_DAYS = 30;

    /**
     * Unresolved postal codes are cached far more briefly, since an unknown one
     * may well be published later.
     */
    protected const MISS_CACHE_TTL_HOURS = 1;

    /**
     * Requests run inside a Livewire round trip, so a slow API must never hang the form.
     */
    protected const TIMEOUT_SECONDS = 3;

    /**
     * Maps each address field to the key holding it in the service's response.
     */
    protected const BRASIL_API_FIELDS = [
        'street' => 'street',
        'district' => 'neighborhood',
        'city' => 'city',
        'state' => 'state',
        'city_ibge_code' => 'ibge.city',
    ];

    protected const VIA_CEP_FIELDS = [
        'street' => 'logradouro',
        'district' => 'bairro',
        'city' => 'localidade',
        'state' => 'uf',
        'city_ibge_code' => 'ibge',
    ];

    /**
     * Look up a Brazilian postal code, returning null when it cannot be resolved.
     *
     * A partial answer is the normal case: the general postal code of a city
     * resolves to city and state only, with no street or district.
     *
     * @return array{postal_code: string, street: ?string, district: ?string, city: ?string, state: ?string, city_ibge_code: ?string}|null
     */
    public function handle(string $postalCode): ?array
    {
        $digits = (string) preg_replace('/\D/', '', $postalCode);

        if (strlen($digits) !== 8) {
            return null;
        }

        $key = "postal-code:{$digits}";

        /** @var array{postal_code: string, street: ?string, district: ?string, city: ?string, state: ?string, city_ibge_code: ?string}|false|null $cached */
        $cached = Cache::get($key);

        // A miss is stored as false: Cache::remember() cannot tell a cached null
        // from an absent key, so caching null would re-run every failed lookup.
        if ($cached !== null) {
            return $cached ?: null;
        }

        $address = $this->resolve($digits);

        Cache::put($key, $address ?? false, $address === null
            ? now()->addHours(self::MISS_CACHE_TTL_HOURS)
            : now()->addDays(self::CACHE_TTL_DAYS));

        return $address;
    }

    /**
     * Resolve the postal code through BrasilAPI, which already falls back between
     * the Correios, ViaCEP and OpenCEP sources internally. ViaCEP is asked directly
     * only when BrasilAPI itself is unreachable or broken -- a 404 from it is a
     * definitive answer, so there is nothing left to ask.
     *
     * @return array{postal_code: string, street: ?string, district: ?string, city: ?string, state: ?string, city_ibge_code: ?string}|null
     */
    protected function resolve(string $digits): ?array
    {
        $response = $this->request("https://brasilapi.com.br/api/cep/v2/{$digits}");

        if ($response?->status() === 404) {
            return null;
        }

        if ($data = $this->usableJson($response)) {
            return $this->toAddress($digits, $data, self::BRASIL_API_FIELDS);
        }

        $data = $this->usableJson($this->request("https://viacep.com.br/ws/{$digits}/json/"));

        return $data === null ? null : $this->toAddress($digits, $data, self::VIA_CEP_FIELDS);
    }

    /**
     * Send the request, treating an unreachable service as no response at all.
     */
    protected function request(string $url): ?Response
    {
        try {
            return Http::timeout(self::TIMEOUT_SECONDS)->get($url);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get the decoded body, or null when the response cannot be used.
     *
     * ViaCEP signals an unknown postal code with `{"erro": true}` and a 200.
     *
     * @return array<string, mixed>|null
     */
    protected function usableJson(?Response $response): ?array
    {
        if ($response === null || ! $response->successful()) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        if (filter_var($data['erro'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        return $data ?: null;
    }

    /**
     * Build the address from a service response, reading each field by its mapped key.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $fields
     * @return array{postal_code: string, street: ?string, district: ?string, city: ?string, state: ?string, city_ibge_code: ?string}
     */
    protected function toAddress(string $digits, array $data, array $fields): array
    {
        /** @var array{postal_code: string, street: ?string, district: ?string, city: ?string, state: ?string, city_ibge_code: ?string} $address */
        $address = ['postal_code' => $digits] + array_map(
            fn (string $key): ?string => $this->stringOrNull(data_get($data, $key)),
            $fields,
        );

        return $address;
    }

    /**
     * Cast the value to a trimmed string, treating blanks as absent.
     */
    protected function stringOrNull(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }
}
