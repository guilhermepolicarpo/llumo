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

test('members cannot open the loan rules', function () {
    actingAsTeamMember(TeamRole::Member);

    Livewire::test('pages::library.settings')->assertForbidden();
});

test('the loan rules show until when a book lent today would stay out', function (?int $maxRenewals, string $example) {
    $this->travelTo('2026-10-18 10:00:00');
    [, $team] = actingAsTeamMember(TeamRole::Admin);
    $team->update(['loan_period_days' => 14, 'loan_max_renewals' => $maxRenewals]);

    Livewire::test('pages::library.settings')
        ->assertSeeHtml('data-test="loan-rules-example"')
        ->assertSee($example);
})->with([
    'limited renewals' => [2, 'Na prática: um livro emprestado hoje (18/10/2026) deve voltar até 01/11/2026 e, com 2 renovações, pode ficar até 29/11/2026.'],
    'no renewals' => [0, 'Na prática: um livro emprestado hoje (18/10/2026) deve voltar até 01/11/2026 e não pode ser renovado.'],
    'unlimited renewals' => [null, 'Na prática: um livro emprestado hoje (18/10/2026) deve voltar até 01/11/2026 e pode ser renovado sem limite.'],
]);
