<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

test('teams index page can be rendered', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('teams.index'));

    $response->assertOk();
});

test('teams can be created', function () {
    config(['llumo.allow_center_creation' => true]);
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::teams.index')
        ->set('name', 'Test Team')
        ->call('createTeam')
        ->assertHasNoErrors();

    $team = Team::where('name', 'Test Team')->firstOrFail();

    expect($user->fresh()->ownsTeam($team))->toBeTrue();
});

test('teams cannot be created when the installation serves a single center', function (string $component, string $nameField) {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test($component)
        ->set($nameField, 'Second Center')
        ->call('createTeam')
        ->assertForbidden();

    $this->assertDatabaseMissing('teams', ['name' => 'Second Center']);
})->with([
    'settings page' => ['pages::teams.index', 'name'],
    'team switcher modal' => ['create-team-modal', 'teamName'],
]);

test('the new center button is hidden when the installation serves a single center', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::teams.index')
        ->assertDontSeeHtml('data-test="teams-new-team-button"');
});

test('only the first center can be created when the installation serves a single center', function () {
    $user = User::factory()->withoutTeam()->create();

    expect(Gate::forUser($user)->allows('create', Team::class))->toBeTrue();

    Team::factory()->trashed()->create();

    expect(Gate::forUser($user)->allows('create', Team::class))->toBeFalse();
});

test('any user can create centers when center creation is allowed', function () {
    config(['llumo.allow_center_creation' => true]);
    $user = User::factory()->create();

    expect(Gate::forUser($user)->allows('create', Team::class))->toBeTrue();
});

test('team slug uses next available suffix', function () {
    config(['llumo.allow_center_creation' => true]);
    $user = User::factory()->create();

    Team::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
    Team::factory()->create(['name' => 'Acme One', 'slug' => 'acme-1']);
    Team::factory()->create(['name' => 'Acme Ten', 'slug' => 'acme-10']);

    $this->actingAs($user);

    Livewire::test('pages::teams.index')
        ->set('name', 'Acme')
        ->call('createTeam')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('teams', [
        'name' => 'Acme',
        'slug' => 'acme-11',
    ]);
});

test('the center details page can be rendered by owners', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($user)
        ->get(route('team-settings.profile', ['current_team' => $team]));

    $response->assertOk();
});

test('teams can be updated by owners', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['name' => 'Original Name']);

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $this->actingAs($user);

    auth()->user()->switchTeam($team);

    Livewire::test('pages::team-settings.profile')
        ->set('teamName', 'Updated Name')
        ->call('updateTeam')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'name' => 'Updated Name',
    ]);
});

test('teams cannot be updated by members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($member);

    auth()->user()->switchTeam($team);

    Livewire::test('pages::team-settings.profile')->assertForbidden();

    expect($team->fresh()->name)->not->toBe('Updated Name');
});

test('teams can be deleted by owners', function () {
    config(['llumo.allow_center_creation' => true]);
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $this->actingAs($user);

    Livewire::test('pages::teams.delete-team-modal', ['team' => $team])
        ->set('deleteName', $team->name)
        ->call('deleteTeam')
        ->assertHasNoErrors();

    $this->assertSoftDeleted('teams', [
        'id' => $team->id,
    ]);
});

test('team deletion requires name confirmation', function () {
    config(['llumo.allow_center_creation' => true]);
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $this->actingAs($user);

    Livewire::test('pages::teams.delete-team-modal', ['team' => $team])
        ->set('deleteName', 'Wrong Name')
        ->call('deleteTeam')
        ->assertHasErrors(['deleteName']);

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'deleted_at' => null,
    ]);
});

test('deleting current team switches to alphabetically first remaining team', function () {
    config(['llumo.allow_center_creation' => true]);
    $user = User::factory()->create(['name' => 'Mike']);

    $zuluTeam = Team::factory()->create(['name' => 'Zulu Team']);
    $zuluTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $alphaTeam = Team::factory()->create(['name' => 'Alpha Team']);
    $alphaTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $betaTeam = Team::factory()->create(['name' => 'Beta Team']);
    $betaTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $user->update(['current_team_id' => $zuluTeam->id]);

    $this->actingAs($user);

    Livewire::test('pages::teams.delete-team-modal', ['team' => $zuluTeam])
        ->set('deleteName', $zuluTeam->name)
        ->call('deleteTeam')
        ->assertHasNoErrors();

    $this->assertSoftDeleted('teams', [
        'id' => $zuluTeam->id,
    ]);

    expect($user->fresh()->current_team_id)->toEqual($alphaTeam->id);
});

test('deleting the only team of a user clears their current team', function () {
    config(['llumo.allow_center_creation' => true]);
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user);

    Livewire::test('pages::teams.delete-team-modal', ['team' => $team])
        ->set('deleteName', $team->name)
        ->call('deleteTeam')
        ->assertHasNoErrors();

    $this->assertSoftDeleted('teams', [
        'id' => $team->id,
    ]);

    expect($user->fresh()->current_team_id)->toBeNull();
});

test('deleting non current team leaves current team unchanged', function () {
    config(['llumo.allow_center_creation' => true]);
    $user = User::factory()->create();
    $currentTeam = $user->currentTeam;
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $this->actingAs($user);

    Livewire::test('pages::teams.delete-team-modal', ['team' => $team])
        ->set('deleteName', $team->name)
        ->call('deleteTeam')
        ->assertHasNoErrors();

    $this->assertSoftDeleted('teams', [
        'id' => $team->id,
    ]);

    expect($user->fresh()->current_team_id)->toEqual($currentTeam->id);
});

test('members can leave teams', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($member);

    Livewire::test('pages::teams.index')
        ->call('leaveTeam', $team->id)
        ->assertHasNoErrors();

    expect($member->fresh()->belongsToTeam($team))->toBeFalse();
});

test('leaving current team switches to alphabetically first remaining team', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create(['name' => 'Mike']);

    $zuluTeam = Team::factory()->create(['name' => 'Zulu Team']);
    $zuluTeam->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $zuluTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

    $alphaTeam = Team::factory()->create(['name' => 'Alpha Team']);
    $alphaTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

    $betaTeam = Team::factory()->create(['name' => 'Beta Team']);
    $betaTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

    $member->update(['current_team_id' => $zuluTeam->id]);

    $this->actingAs($member);

    Livewire::test('pages::teams.index')
        ->call('leaveTeam', $zuluTeam->id)
        ->assertHasNoErrors();

    expect($member->fresh()->belongsToTeam($zuluTeam))->toBeFalse();
    expect($member->fresh()->current_team_id)->toEqual($alphaTeam->id);
});

test('team owners cannot leave their team', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $this->actingAs($owner);

    Livewire::test('pages::teams.index')
        ->call('leaveTeam', $team->id)
        ->assertForbidden();

    expect($owner->fresh()->belongsToTeam($team))->toBeTrue();
});

test('users cannot leave teams they dont belong to', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::teams.index')
        ->call('leaveTeam', $team->id)
        ->assertForbidden();
});

test('leave control is only rendered for leaveable teams', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $leaveableTeam = Team::factory()->create();

    $leaveableTeam->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $leaveableTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($member);

    Livewire::test('pages::teams.index')
        ->assertSeeHtml('data-test="team-leave-button"');
});

test('leave control is not rendered for owned teams', function () {
    $user = User::factory()->create();
    $ownedTeam = Team::factory()->create();

    $ownedTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $this->actingAs($user);

    Livewire::test('pages::teams.index')
        ->assertDontSeeHtml('data-test="team-leave-button"');
});

test('deleting team clears the current team of other affected users', function () {
    config(['llumo.allow_center_creation' => true]);
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $owner->update(['current_team_id' => $team->id]);
    $member->update(['current_team_id' => $team->id]);

    $this->actingAs($owner);

    Livewire::test('pages::teams.delete-team-modal', ['team' => $team])
        ->set('deleteName', $team->name)
        ->call('deleteTeam')
        ->assertHasNoErrors();

    expect($member->fresh()->current_team_id)->toBeNull();
});

test('teams cannot be deleted when the installation serves a single center', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user);

    Livewire::test('pages::teams.delete-team-modal', ['team' => $team])
        ->set('deleteName', $team->name)
        ->call('deleteTeam')
        ->assertForbidden();

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'deleted_at' => null,
    ]);
});

test('teams cannot be deleted by non owners', function () {
    config(['llumo.allow_center_creation' => true]);
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($member);

    Livewire::test('pages::teams.delete-team-modal', ['team' => $team])
        ->set('deleteName', $team->name)
        ->call('deleteTeam')
        ->assertForbidden();
});

test('guests cannot access teams', function () {
    $response = $this->get(route('teams.index'));

    $response->assertRedirect(route('login'));
});
