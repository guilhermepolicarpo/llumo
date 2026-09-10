<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('team member role can be updated by owner', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($owner);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->call('updateMember', $member->id, TeamRole::Admin->value)
        ->assertHasNoErrors();

    expect($team->members()->where('user_id', $member->id)->first()->pivot->role->value)->toEqual(TeamRole::Admin->value);
});

test('team member role cannot be updated by non owner', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($admin);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->call('updateMember', $member->id, TeamRole::Admin->value)
        ->assertForbidden();
});

test('team member can be removed by owner', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($owner);

    Livewire::test('pages::teams.remove-member-modal', ['team' => $team])
        ->call('confirmRemoveMember', $member->id, $member->name)
        ->call('removeMember')
        ->assertHasNoErrors()
        ->assertDispatched('member-removed')
        ->assertNoRedirect();

    expect($member->fresh()->belongsToTeam($team))->toBeFalse();
});

test('team member cannot be removed by non owners', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($admin);

    Livewire::test('pages::teams.remove-member-modal', ['team' => $team])
        ->call('confirmRemoveMember', $member->id, $member->name)
        ->call('removeMember')
        ->assertForbidden();
});

test('owner cannot remove themselves via a tampered member id', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $this->actingAs($owner);

    Livewire::test('pages::teams.remove-member-modal', ['team' => $team])
        ->call('confirmRemoveMember', $owner->id, $owner->name)
        ->call('removeMember')
        ->assertForbidden();

    expect($owner->fresh()->belongsToTeam($team))->toBeTrue();
});

test('owner cannot promote a member to owner via updateMember', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($owner);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->call('updateMember', $member->id, TeamRole::Owner->value)
        ->assertHasErrors(['role']);

    expect($team->members()->where('user_id', $member->id)->first()->pivot->role)->toEqual(TeamRole::Member);
});

test('owner role cannot be changed via updateMember even with a tampered role value', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $this->actingAs($owner);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->call('updateMember', $owner->id, TeamRole::Admin->value)
        ->assertForbidden();

    expect($team->members()->where('user_id', $owner->id)->first()->pivot->role)->toEqual(TeamRole::Owner);
});

test('confirming removal from the shared modal sets the target member and shows the modal', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create(['name' => 'Removable Member']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($owner);

    Livewire::test('pages::teams.remove-member-modal', ['team' => $team])
        ->dispatch('confirm-remove-member', memberId: $member->id, memberName: $member->name)
        ->assertSet('memberId', $member->id)
        ->assertSet('memberName', $member->name)
        ->assertSee('Removable Member');
});

test('removed members current team is set to personal team', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $personalTeam = $member->personalTeam();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $member->update(['current_team_id' => $team->id]);

    $this->actingAs($owner);

    Livewire::test('pages::teams.remove-member-modal', ['team' => $team])
        ->call('confirmRemoveMember', $member->id, $member->name)
        ->call('removeMember')
        ->assertHasNoErrors();

    expect($member->fresh()->current_team_id)->toEqual($personalTeam->id);
});
