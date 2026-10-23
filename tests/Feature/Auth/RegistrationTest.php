<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;

test('registration screen can be rendered on an empty installation', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('the first user of an empty installation can register and is sent to onboarding', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('onboarding', absolute: false));

    $this->assertAuthenticated();

    expect(Team::count())->toBe(0);
});

test('registration screen redirects to login when registration is by invitation only', function () {
    User::factory()->create();

    $this->get(route('register'))->assertRedirect(route('login'));
});

test('users cannot register without an invitation when registration is by invitation only', function () {
    User::factory()->create();

    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors([
        'email' => 'O cadastro é feito apenas por convite. Peça um convite a um administrador do seu Centro Espírita.',
    ]);

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
});

test('the sign up link is hidden on login when registration is by invitation only', function () {
    User::factory()->create();

    $this->get(route('login'))->assertDontSeeHtml('data-test="register-link"');
});

test('users can register without an invitation when registration is open', function () {
    config(['llumo.open_registration' => true]);
    User::factory()->create();

    $this->get(route('register'))->assertOk();

    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('onboarding', absolute: false));

    $user = User::where('email', 'test@example.com')->sole();

    expect($user->teams)->toBeEmpty();
});

test('invited users register straight into the inviting center with the invited role', function () {
    $invitation = TeamInvitation::factory()->create([
        'email' => 'invited@example.com',
        'role' => TeamRole::Admin,
    ]);

    $this->get(route('register', ['invitation' => $invitation->code]))
        ->assertOk()
        ->assertSee('invited@example.com');

    $response = $this->post(route('register.store'), [
        'name' => 'Invited Person',
        'email' => 'Invited@Example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'invitation' => $invitation->code,
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', ['current_team' => $invitation->team->slug], absolute: false));

    $user = User::where('email', 'invited@example.com')->sole();

    expect($user->teams)->toHaveCount(1)
        ->and($user->current_team_id)->toBe($invitation->team_id)
        ->and($user->teamRole($invitation->team))->toBe(TeamRole::Admin)
        ->and($invitation->fresh()->isAccepted())->toBeTrue();
});

test('users cannot register through an invitation sent to another email', function () {
    $invitation = TeamInvitation::factory()->create(['email' => 'invited@example.com']);

    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'someone-else@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'invitation' => $invitation->code,
    ]);

    $response->assertSessionHasErrors([
        'email' => 'Este convite foi enviado para um endereço de e-mail diferente.',
    ]);

    $this->assertGuest();
    expect($invitation->fresh()->isAccepted())->toBeFalse();
});

test('users cannot register through an expired invitation', function () {
    $invitation = TeamInvitation::factory()->expired()->create(['email' => 'invited@example.com']);

    $response = $this->post(route('register.store'), [
        'name' => 'Invited Person',
        'email' => 'invited@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'invitation' => $invitation->code,
    ]);

    $response->assertSessionHasErrors([
        'email' => 'Este convite não é mais válido.',
    ]);

    $this->assertGuest();
});
