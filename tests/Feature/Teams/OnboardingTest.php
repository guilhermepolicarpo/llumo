<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('users without a center can create the first center of the installation', function () {
    $user = User::factory()->withoutTeam()->create();

    $this->actingAs($user)
        ->get(route('onboarding'))
        ->assertOk()
        ->assertSeeHtml('data-test="onboarding-team-name"');

    Livewire::test('pages::onboarding.index')
        ->set('name', 'Centro Espírita Luz')
        ->call('createTeam')
        ->assertHasNoErrors()
        ->assertRedirect(route('team-settings.profile', ['current_team' => 'centro-espirita-luz']));

    $team = Team::where('name', 'Centro Espírita Luz')->sole();

    expect($user->fresh()->ownsTeam($team))->toBeTrue()
        ->and($user->fresh()->current_team_id)->toBe($team->id);
});

test('users without a center are asked for an invitation when the installation already has its center', function () {
    Team::factory()->create();
    $user = User::factory()->withoutTeam()->create();

    $this->actingAs($user)
        ->get(route('onboarding'))
        ->assertOk()
        ->assertSeeHtml('data-test="onboarding-no-team-message"')
        ->assertDontSeeHtml('data-test="onboarding-team-name"')
        ->assertSeeHtml('data-test="delete-user-button"');

    Livewire::test('pages::onboarding.index')
        ->set('name', 'Another Center')
        ->call('createTeam')
        ->assertForbidden();

    $this->assertDatabaseMissing('teams', ['name' => 'Another Center']);
});

test('users with a center are sent from onboarding to their dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::onboarding.index')
        ->assertRedirect(route('dashboard', ['current_team' => $user->currentTeam->slug]));
});

test('users without a center are sent to onboarding after logging in', function () {
    $user = User::factory()->withoutTeam()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('onboarding', absolute: false));
});

test('logged in users without a center visiting the login page are sent to onboarding', function () {
    $user = User::factory()->withoutTeam()->create();

    $this->actingAs($user)
        ->get(route('login'))
        ->assertRedirect(route('onboarding'));
});

test('users without a center visiting account settings are sent to onboarding', function () {
    $user = User::factory()->withoutTeam()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertRedirect(route('onboarding'));
});

test('users whose current center was cleared are switched to another of their centers', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $user->update(['current_team_id' => null]);

    $this->actingAs($user->fresh())
        ->get(route('profile.edit'))
        ->assertOk();

    expect($user->fresh()->current_team_id)->toBe($team->id);
});

test('members who leave their only center are sent to onboarding on their next login', function () {
    $owner = User::factory()->create();
    $member = User::factory()->withoutTeam()->create();
    $team = $owner->currentTeam;
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $this->actingAs($member);

    Livewire::test('pages::teams.index')
        ->call('leaveTeam', $team->id)
        ->assertHasNoErrors();

    auth()->logout();

    $this->post(route('login.store'), [
        'email' => $member->email,
        'password' => 'password',
    ])->assertRedirect(route('onboarding', absolute: false));
});

test('users still pointing at a center they no longer belong to are sent to onboarding', function () {
    $owner = User::factory()->create();
    $user = User::factory()->withoutTeam()->create();
    $user->forceFill(['current_team_id' => $owner->current_team_id])->save();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertRedirect(route('onboarding'));

    expect($user->fresh()->current_team_id)->toBeNull();
});
