<?php

use App\Enums\BrazilianState;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function teamOwnedBy(User $user, array $attributes = []): Team
{
    $team = Team::factory()->create($attributes);

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    return $team;
}

beforeEach(function () {
    Storage::fake('public');
    Http::preventStrayRequests();
});

test('owners can update the full team address', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->set('postalCode', '01310-100')
        ->set('street', 'Avenida Paulista')
        ->set('number', '1578')
        ->set('complement', 'Sala 12')
        ->set('district', 'Bela Vista')
        ->set('city', 'São Paulo')
        ->set('state', 'SP')
        ->call('updateTeam')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'postal_code' => '01310100',
        'street' => 'Avenida Paulista',
        'number' => '1578',
        'complement' => 'Sala 12',
        'district' => 'Bela Vista',
        'city' => 'São Paulo',
        'state' => 'SP',
    ]);
});

test('renaming the team redirects to the new slug url', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user, ['name' => 'Old Name']);

    $this->actingAs($user);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->set('teamName', 'New Name')
        ->call('updateTeam')
        ->assertHasNoErrors()
        ->assertRedirectToRoute('teams.edit', ['team' => $team->fresh()->slug]);
});

test('updating the address without renaming the team does not redirect', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->set('city', 'São Paulo')
        ->call('updateTeam')
        ->assertHasNoErrors()
        ->assertNoRedirect();
});

test('the invitation-created event refreshes the pending invitations list in place', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);

    $component = Livewire::test('pages::teams.edit', ['team' => $team])
        ->assertDontSee('invited@example.com');

    $team->invitations()->create([
        'email' => 'invited@example.com',
        'role' => TeamRole::Member,
        'invited_by' => $user->id,
        'expires_at' => now()->addDays(3),
    ]);

    $component->dispatch('invitation-created')
        ->assertSee('invited@example.com')
        ->assertNoRedirect();
});

test('the invitation-cancelled event refreshes the pending invitations list in place', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $team->invitations()->create([
        'email' => 'invited@example.com',
        'role' => TeamRole::Member,
        'invited_by' => $user->id,
        'expires_at' => now()->addDays(3),
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::teams.edit', ['team' => $team])
        ->assertSee('invited@example.com');

    $team->invitations()->delete();

    $component->dispatch('invitation-cancelled')
        ->assertDontSee('invited@example.com')
        ->assertNoRedirect();
});

test('blank address fields are stored as null', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user, ['city' => 'Santos']);

    $this->actingAs($user);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->set('city', '')
        ->call('updateTeam')
        ->assertHasNoErrors();

    expect($team->fresh()->city)->toBeNull();
});

test('owners can upload a team logo', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->set('logo', UploadedFile::fake()->image('logo.png', 400, 400))
        ->call('updateTeam')
        ->assertHasNoErrors();

    $logoPath = $team->fresh()->logo_path;

    expect($logoPath)->toStartWith('team-logos/');

    Storage::disk('public')->assertExists($logoPath);
});

test('uploading a new logo deletes the previous one', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $team->update(['logo_path' => UploadedFile::fake()->image('old.png')->store('team-logos', 'public')]);

    $previousPath = $team->logo_path;

    $this->actingAs($user);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->set('logo', UploadedFile::fake()->image('new.png', 400, 400))
        ->call('updateTeam')
        ->assertHasNoErrors();

    $newPath = $team->fresh()->logo_path;

    expect($newPath)->not->toBe($previousPath);

    Storage::disk('public')->assertMissing($previousPath);
    Storage::disk('public')->assertExists($newPath);
});

test('owners can remove the team logo', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);
    $team->update(['logo_path' => UploadedFile::fake()->image('logo.png')->store('team-logos', 'public')]);

    $previousPath = $team->logo_path;

    $this->actingAs($user);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->call('removeLogo')
        ->assertHasNoErrors();

    expect($team->fresh()->logo_path)->toBeNull();

    Storage::disk('public')->assertMissing($previousPath);
});

test('removing the logo keeps the rest of the profile intact', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user, [
        'name' => 'Centro Espirita Luz',
        'postal_code' => '01310100',
        'city' => 'São Paulo',
        'state' => BrazilianState::SaoPaulo,
    ]);
    $team->update(['logo_path' => UploadedFile::fake()->image('logo.png')->store('team-logos', 'public')]);

    $this->actingAs($user);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->call('removeLogo')
        ->assertHasNoErrors();

    $team = $team->fresh();

    expect($team->name)->toBe('Centro Espirita Luz')
        ->and($team->postal_code)->toBe('01310100')
        ->and($team->city)->toBe('São Paulo')
        ->and($team->state)->toBe(BrazilianState::SaoPaulo);
});

test('logo must be an image within the size limit', function (UploadedFile $file) {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->set('logo', $file)
        ->call('updateTeam')
        ->assertHasErrors('logo');

    expect($team->fresh()->logo_path)->toBeNull();
})->with([
    'pdf' => fn () => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf'),
    'oversized image' => fn () => UploadedFile::fake()->image('logo.png', 400, 400)->size(3000),
    'oversized dimensions' => fn () => UploadedFile::fake()->image('logo.png', 2400, 2400),
]);

test('postal code must be a valid brazilian postal code', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->set('postalCode', '123')
        ->call('updateTeam')
        ->assertHasErrors('postalCode');
});

test('state must be a known brazilian state', function () {
    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->set('state', 'XX')
        ->call('updateTeam')
        ->assertHasErrors('state');
});

test('members cannot update the team profile or remove the logo', function (string $method) {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = teamOwnedBy($owner);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($member);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->call($method)
        ->assertForbidden();
})->with(['updateTeam', 'removeLogo']);

test('the postal code lookup fills the blank address fields', function () {
    Http::fake([
        'brasilapi.com.br/api/cep/v2/01310100' => Http::response([
            'cep' => '01310100',
            'street' => 'Avenida Paulista',
            'neighborhood' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
        ]),
    ]);

    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->set('postalCode', '01310-100')
        ->assertSet('street', 'Avenida Paulista')
        ->assertSet('district', 'Bela Vista')
        ->assertSet('city', 'São Paulo')
        ->assertSet('state', 'SP');
});

test('the postal code lookup does not overwrite fields the user already filled', function () {
    Http::fake([
        'brasilapi.com.br/api/cep/v2/01310100' => Http::response([
            'street' => 'Avenida Paulista',
            'neighborhood' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
        ]),
    ]);

    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->set('street', 'Rua da Casa')
        ->set('postalCode', '01310-100')
        ->assertSet('street', 'Rua da Casa')
        ->assertSet('district', 'Bela Vista');
});

test('an unknown postal code leaves the address fields untouched', function () {
    Http::fake([
        'brasilapi.com.br/*' => Http::response(['message' => 'CEP não encontrado'], 404),
    ]);

    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->set('postalCode', '00000-000')
        ->assertHasNoErrors()
        ->assertSet('street', '')
        ->assertSet('city', '');
});

test('an unreachable postal code service leaves the address fields untouched', function () {
    Http::fake(fn () => throw new ConnectionException('Timed out'));

    $user = User::factory()->create();
    $team = teamOwnedBy($user);

    $this->actingAs($user);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->set('postalCode', '01310-100')
        ->assertHasNoErrors()
        ->assertSet('street', '');
});

test('personal teams also show the logo and address sections', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::teams.edit', ['team' => $user->personalTeam()])
        ->assertSeeHtml('data-test="team-logo-input"')
        ->assertSeeHtml('data-test="team-postal-code-input"');
});

test('owners can set a logo and address on their personal team', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();

    $this->actingAs($user);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->set('logo', UploadedFile::fake()->image('logo.png', 400, 400))
        ->set('city', 'São Paulo')
        ->set('state', 'SP')
        ->call('updateTeam')
        ->assertHasNoErrors();

    $team = $team->fresh();

    expect($team->logo_path)->not->toBeNull()
        ->and($team->city)->toBe('São Paulo')
        ->and($team->state)->toBe(BrazilianState::SaoPaulo);
});

test('members see the team logo and address in read only mode', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = teamOwnedBy($owner, [
        'street' => 'Avenida Paulista',
        'number' => '1578',
        'city' => 'São Paulo',
        'state' => BrazilianState::SaoPaulo,
    ]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($member);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->assertDontSeeHtml('data-test="team-postal-code-input"')
        ->assertSeeHtml('data-test="team-formatted-address"')
        ->assertSee('Avenida Paulista, 1578');
});

test('the formatted address skips the parts that are not filled in', function () {
    $team = Team::factory()->create([
        'street' => 'Avenida Paulista',
        'number' => '1578',
        'city' => 'São Paulo',
        'state' => BrazilianState::SaoPaulo,
        'postal_code' => '01310100',
    ]);

    expect($team->formatted_address)
        ->toContain('Avenida Paulista, 1578')
        ->toContain('São Paulo/SP')
        ->toContain('01310-100')
        ->not->toContain('  ');

    expect(Team::factory()->create()->formatted_address)->toBeNull();
});

test('the logo url is null until a logo is stored', function () {
    $team = Team::factory()->create();

    expect($team->logo_url)->toBeNull();

    $team = Team::factory()->withLogo()->create();

    expect($team->logo_url)->toContain($team->logo_path);
});
