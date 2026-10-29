<?php

use App\Enums\TeamRole;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Teams\TeamMemberRemoved;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('membership factory persists the correct role for each state', function () {
    $team = Team::factory()->create();

    $owner = Membership::factory()->for($team)->owner()->create();
    $admin = Membership::factory()->for($team)->admin()->create();
    $member = Membership::factory()->for($team)->member()->create();

    expect($owner->role)->toEqual(TeamRole::Owner)
        ->and($admin->role)->toEqual(TeamRole::Admin)
        ->and($member->role)->toEqual(TeamRole::Member);
});

test('membership factory respects the unique team and user constraint', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();

    Membership::factory()->for($team)->for($user)->member()->create();

    expect(fn () => Membership::factory()->for($team)->for($user)->admin()->create())
        ->toThrow(QueryException::class);
});

test('team member role can be updated by owner', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($owner);

    auth()->user()->switchTeam($team);

    Livewire::test('pages::team-settings.members')
        ->call('updateMember', $member->id, TeamRole::Admin->value)
        ->assertHasNoErrors();

    expect($team->members()->where('user_id', $member->id)->first()->pivot->role->value)->toEqual(TeamRole::Admin->value);
});

test('team member role cannot be updated by members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $otherMember = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $team->members()->attach($otherMember, ['role' => TeamRole::Member->value]);

    $this->actingAs($member);

    auth()->user()->switchTeam($team);

    Livewire::test('pages::team-settings.members')
        ->assertForbidden();

    expect($member->can('updateMember', [$team, $otherMember]))->toBeFalse();
});

test('team member role can be updated by admins', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($admin);

    auth()->user()->switchTeam($team);

    Livewire::test('pages::team-settings.members')
        ->call('updateMember', $member->id, TeamRole::Admin->value)
        ->assertHasNoErrors();

    expect($member->teamRole($team))->toBe(TeamRole::Admin);
});

test('admins cannot change the role of the owner or their own role', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $this->actingAs($admin);

    auth()->user()->switchTeam($team);

    Livewire::test('pages::team-settings.members')
        ->call('updateMember', $owner->id, TeamRole::Member->value)
        ->assertForbidden();

    Livewire::test('pages::team-settings.members')
        ->call('updateMember', $admin->id, TeamRole::Member->value)
        ->assertForbidden();

    expect($owner->teamRole($team))->toBe(TeamRole::Owner)
        ->and($admin->teamRole($team))->toBe(TeamRole::Admin);
});

test('team member can be removed by owner', function () {
    Notification::fake();

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

    Notification::assertSentTo(
        $member,
        TeamMemberRemoved::class,
        fn (TeamMemberRemoved $notification): bool => $notification->team->is($team),
    );
});

test('team member cannot be removed by members', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $member = User::factory()->create();
    $otherMember = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $team->members()->attach($otherMember, ['role' => TeamRole::Member->value]);

    $this->actingAs($member);

    Livewire::test('pages::teams.remove-member-modal', ['team' => $team])
        ->call('confirmRemoveMember', $otherMember->id, $otherMember->name)
        ->call('removeMember')
        ->assertForbidden();

    expect($otherMember->fresh()->belongsToTeam($team))->toBeTrue();

    Notification::assertNothingSent();
});

test('admins can remove other admins', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $otherAdmin = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($otherAdmin, ['role' => TeamRole::Admin->value]);

    $this->actingAs($admin);

    Livewire::test('pages::teams.remove-member-modal', ['team' => $team])
        ->call('confirmRemoveMember', $otherAdmin->id, $otherAdmin->name)
        ->call('removeMember')
        ->assertHasNoErrors()
        ->assertDispatched('member-removed');

    expect($otherAdmin->fresh()->belongsToTeam($team))->toBeFalse();

    Notification::assertSentTo($otherAdmin, TeamMemberRemoved::class);
});

test('admins cannot remove the owner or themselves', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $this->actingAs($admin);

    Livewire::test('pages::teams.remove-member-modal', ['team' => $team])
        ->call('confirmRemoveMember', $owner->id, $owner->name)
        ->call('removeMember')
        ->assertForbidden();

    Livewire::test('pages::teams.remove-member-modal', ['team' => $team])
        ->call('confirmRemoveMember', $admin->id, $admin->name)
        ->call('removeMember')
        ->assertForbidden();

    expect($owner->fresh()->belongsToTeam($team))->toBeTrue()
        ->and($admin->fresh()->belongsToTeam($team))->toBeTrue();
});

test('owner can transfer ownership to a member and becomes an admin', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($owner);

    Livewire::test('pages::teams.transfer-ownership-modal', ['team' => $team])
        ->dispatch('confirm-transfer-ownership', memberId: $member->id, memberName: $member->name)
        ->call('transferOwnership')
        ->assertHasNoErrors()
        ->assertDispatched('ownership-transferred');

    expect($member->teamRole($team))->toBe(TeamRole::Owner)
        ->and($owner->teamRole($team))->toBe(TeamRole::Admin)
        ->and($owner->fresh()->can('leave', $team))->toBeTrue();
});

test('admins cannot transfer ownership', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $this->actingAs($admin);

    Livewire::test('pages::teams.transfer-ownership-modal', ['team' => $team])
        ->call('confirmTransferOwnership', $admin->id, $admin->name)
        ->call('transferOwnership')
        ->assertForbidden();

    expect($owner->teamRole($team))->toBe(TeamRole::Owner)
        ->and($admin->teamRole($team))->toBe(TeamRole::Admin);
});

test('ownership cannot be transferred to someone outside the team', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $this->actingAs($owner);

    Livewire::test('pages::teams.transfer-ownership-modal', ['team' => $team])
        ->call('confirmTransferOwnership', $outsider->id, $outsider->name)
        ->call('transferOwnership')
        ->assertForbidden();

    expect($owner->teamRole($team))->toBe(TeamRole::Owner)
        ->and($outsider->belongsToTeam($team))->toBeFalse();
});

test('owner cannot remove themselves via a tampered member id', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $this->actingAs($owner);

    Livewire::test('pages::teams.remove-member-modal', ['team' => $team])
        ->call('confirmRemoveMember', $owner->id, $owner->name)
        ->call('removeMember')
        ->assertForbidden();

    expect($owner->fresh()->belongsToTeam($team))->toBeTrue();

    Notification::assertNothingSent();
});

test('owner cannot promote a member to owner via updateMember', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($owner);

    auth()->user()->switchTeam($team);

    Livewire::test('pages::team-settings.members')
        ->call('updateMember', $member->id, TeamRole::Owner->value)
        ->assertHasErrors(['role']);

    expect($team->members()->where('user_id', $member->id)->first()->pivot->role)->toEqual(TeamRole::Member);
});

test('owner role cannot be changed via updateMember even with a tampered role value', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $this->actingAs($owner);

    auth()->user()->switchTeam($team);

    Livewire::test('pages::team-settings.members')
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

test('removing a member clears their current team when it was the team they were removed from', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $member->update(['current_team_id' => $team->id]);

    $this->actingAs($owner);

    Livewire::test('pages::teams.remove-member-modal', ['team' => $team])
        ->call('confirmRemoveMember', $member->id, $member->name)
        ->call('removeMember')
        ->assertHasNoErrors();

    expect($member->fresh()->current_team_id)->toBeNull();
});

test('the members page shows an empty state when there are no pending invitations', function () {
    actingAsTeamMember(TeamRole::Owner);

    Livewire::test('pages::team-settings.members')
        ->assertSeeHtml('data-test="invitations-empty"');
});
