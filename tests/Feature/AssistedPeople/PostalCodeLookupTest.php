<?php

use App\Actions\Addresses\LookupPostalCode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * Build a complete BrasilAPI response body.
 *
 * @return array<string, mixed>
 */
function brasilApiResponse(array $overrides = []): array
{
    return array_merge([
        'cep' => '01310100',
        'state' => 'SP',
        'city' => 'São Paulo',
        'neighborhood' => 'Bela Vista',
        'street' => 'Avenida Paulista',
        'service' => 'open-cep',
        'ibge' => ['city' => '3550308', 'state' => '35'],
    ], $overrides);
}

test('a complete postal code response fills the whole address', function () {
    Http::fake([
        'brasilapi.com.br/*' => Http::response(brasilApiResponse()),
    ]);

    [$user] = memberOfTeam();

    $this->actingAs($user);

    Livewire::test('pages::assisted-people.create')
        ->set('form.postal_code', '01310-100')
        ->assertSet('form.street', 'Avenida Paulista')
        ->assertSet('form.district', 'Bela Vista')
        ->assertSet('form.city', 'São Paulo')
        ->assertSet('form.state', 'SP')
        ->assertSet('form.city_ibge_code', '3550308');
});

test('a partial postal code response keeps what was already typed', function () {
    Http::fake([
        'brasilapi.com.br/*' => Http::response(brasilApiResponse([
            'cep' => '38200000',
            'state' => 'MG',
            'city' => 'Frutal',
            'neighborhood' => null,
            'street' => null,
            'ibge' => ['city' => '3127107', 'state' => '31'],
        ])),
    ]);

    [$user] = memberOfTeam();

    $this->actingAs($user);

    Livewire::test('pages::assisted-people.create')
        ->set('form.street', 'Rua das Flores')
        ->set('form.district', 'Centro')
        ->set('form.postal_code', '38200-000')
        ->assertSet('form.street', 'Rua das Flores')
        ->assertSet('form.district', 'Centro')
        ->assertSet('form.city', 'Frutal')
        ->assertSet('form.state', 'MG');
});

test('an unknown postal code fills nothing and still allows saving', function () {
    Http::fake([
        'brasilapi.com.br/*' => Http::response(['message' => 'CEP não encontrado'], 404),
        'viacep.com.br/*' => Http::response(['erro' => true]),
    ]);

    [$user, $team] = memberOfTeam();

    $this->actingAs($user);

    Livewire::test('pages::assisted-people.create')
        ->set(assistedPersonFormValues([
            'postal_code' => '',
            'street' => '',
            'district' => '',
            'city' => '',
            'state' => '',
        ]))
        ->set('form.postal_code', '99999-999')
        ->assertSet('form.street', '')
        ->assertSet('form.city', '')
        ->assertHasNoErrors()
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('assisted_people', [
        'team_id' => $team->id,
        'name' => 'Maria da Silva',
        'postal_code' => '99999999',
        'city' => null,
    ]);
});

test('a failing brasil api falls back to via cep', function () {
    Http::fake([
        'brasilapi.com.br/*' => Http::response('', 500),
        'viacep.com.br/*' => Http::response([
            'cep' => '01310-100',
            'logradouro' => 'Avenida Paulista',
            'bairro' => 'Bela Vista',
            'localidade' => 'São Paulo',
            'uf' => 'SP',
            'ibge' => '3550308',
        ]),
    ]);

    [$user] = memberOfTeam();

    $this->actingAs($user);

    Livewire::test('pages::assisted-people.create')
        ->set('form.postal_code', '01310-100')
        ->assertSet('form.street', 'Avenida Paulista')
        ->assertSet('form.district', 'Bela Vista')
        ->assertSet('form.city', 'São Paulo')
        ->assertSet('form.state', 'SP')
        ->assertSet('form.city_ibge_code', '3550308');
});

test('both services timing out degrades silently', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    [$user, $team] = memberOfTeam();

    $this->actingAs($user);

    Livewire::test('pages::assisted-people.create')
        ->set(assistedPersonFormValues(['street' => '', 'city' => '', 'state' => '', 'district' => '']))
        ->set('form.postal_code', '01310-100')
        ->assertSet('form.street', '')
        ->assertSet('form.city', '')
        ->assertHasNoErrors()
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('assisted_people', [
        'team_id' => $team->id,
        'name' => 'Maria da Silva',
    ]);
});

test('looking the same postal code up twice only hits the network once', function () {
    Http::fake([
        'brasilapi.com.br/*' => Http::response(brasilApiResponse()),
    ]);

    $lookupPostalCode = app(LookupPostalCode::class);

    $first = $lookupPostalCode->handle('01310-100');
    $second = $lookupPostalCode->handle('01310100');

    expect($second)->toBe($first);

    Http::assertSentCount(1);
});

test('a postal code without eight digits is never looked up', function () {
    Http::fake();

    expect(app(LookupPostalCode::class)->handle('0131'))->toBeNull();

    Http::assertNothingSent();
});

test('a definitive not found from brasil api does not fall back to via cep', function () {
    Http::fake([
        'brasilapi.com.br/*' => Http::response(['message' => 'CEP não encontrado'], 404),
        'viacep.com.br/*' => Http::response(['erro' => true]),
    ]);

    expect(app(LookupPostalCode::class)->handle('99999-999'))->toBeNull();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'viacep.com.br'));
    Http::assertSentCount(1);
});

test('an unresolved postal code is only looked up once', function () {
    Http::fake([
        'brasilapi.com.br/*' => Http::response(['message' => 'CEP não encontrado'], 404),
    ]);

    $lookupPostalCode = app(LookupPostalCode::class);

    expect($lookupPostalCode->handle('99999-999'))->toBeNull()
        ->and($lookupPostalCode->handle('99999999'))->toBeNull();

    Http::assertSentCount(1);
});
