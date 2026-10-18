<?php

use App\Enums\TeamRole;
use Livewire\Livewire;

test('admins can change the loan rules, leaving the limits blank for none', function () {
    [, $team] = actingAsTeamMember(TeamRole::Admin);

    Livewire::test('pages::library.settings')
        ->assertSet('loanPeriodDays', '14')
        ->set('loanPeriodDays', '21')
        ->set('loanMaxRenewals', '2')
        ->set('loanMaxActivePerPerson', '')
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect($team->fresh())
        ->loan_period_days->toBe(21)
        ->loan_max_renewals->toBe(2)
        ->loan_max_active_per_person->toBeNull();
});

test('the loan period must be at least one day', function () {
    actingAsTeamMember(TeamRole::Admin);

    Livewire::test('pages::library.settings')
        ->set('loanPeriodDays', '0')
        ->call('saveSettings')
        ->assertHasErrors('loanPeriodDays');
});

test('members cannot change the loan rules', function () {
    actingAsTeamMember(TeamRole::Member);

    Livewire::test('pages::library.settings')
        ->call('saveSettings')
        ->assertForbidden();
});
