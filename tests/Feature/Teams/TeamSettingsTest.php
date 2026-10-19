<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

dataset('team settings pages', [
    'center details' => ['team-settings.profile', []],
    'members' => ['team-settings.members', []],
    'appointment types' => ['team-settings.appointment-types', []],
    'mentors' => ['team-settings.catalogs', ['catalog' => 'mentors']],
    'authors' => ['team-settings.library-catalogs', ['libraryCatalog' => 'authors']],
    'loan rules' => ['team-settings.loan-rules', []],
]);

test('owners and admins can open the spiritist center settings', function (TeamRole $role, string $routeName, array $parameters) {
    [, $team] = actingAsTeamMember($role);

    $this->get(route($routeName, ['current_team' => $team, ...$parameters]))->assertOk();
})->with([
    'owner' => TeamRole::Owner,
    'admin' => TeamRole::Admin,
])->with('team settings pages');

test('members cannot open the spiritist center settings', function (string $routeName, array $parameters) {
    [, $team] = actingAsTeamMember(TeamRole::Member);

    $this->get(route($routeName, ['current_team' => $team, ...$parameters]))->assertForbidden();
})->with('team settings pages');

test('the user menu links to the spiritist center settings only for owners and admins', function (TeamRole $role, bool $sees) {
    actingAsTeamMember($role);

    $response = $this->get(route('dashboard'))->assertOk();

    $sees
        ? $response->assertSee('data-test="user-menu-team-settings"', false)
        : $response->assertDontSee('data-test="user-menu-team-settings"', false);
})->with([
    'owner' => [TeamRole::Owner, true],
    'admin' => [TeamRole::Admin, true],
    'member' => [TeamRole::Member, false],
]);

test('the spiritist centers list links to the settings of the centers the user manages', function () {
    $user = User::factory()->create();
    $managedTeam = teamOwnedBy($user);
    $joinedTeam = Team::factory()->create();
    $joinedTeam->members()->attach($user, ['role' => TeamRole::Member->value]);

    $this->actingAs($user);

    Livewire::test('pages::teams.index')
        ->assertSeeHtml(route('team-settings.profile', ['current_team' => $managedTeam->slug]))
        ->assertDontSeeHtml(route('team-settings.profile', ['current_team' => $joinedTeam->slug]));
});
