<?php

use App\Enums\BookLoanStatus;
use App\Enums\TeamRole;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\AssistedPerson;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
});

test('today appointments are counted by status, leaving out the canceled ones and the other days', function () {
    $this->travelTo('2026-10-26 15:00');
    [, $team] = actingAsTeamMember(TeamRole::Member);

    Appointment::factory()->for($team)->count(2)->create(['scheduled_on' => '2026-10-26']);
    Appointment::factory()->for($team)->waiting()->create();
    Appointment::factory()->for($team)->completed()->count(3)->create();
    Appointment::factory()->for($team)->canceled()->create(['scheduled_on' => '2026-10-26']);
    Appointment::factory()->for($team)->create(['scheduled_on' => '2026-10-27']);

    $component = Livewire::test('pages::dashboard.index')
        ->assertSeeHtml('3 de 6');

    expect($component->instance()->todayCounts)->toEqualCanonicalizing([
        'scheduled' => 2,
        'waiting' => 1,
        'completed' => 3,
    ]);
});

test('the reception queue lists those being attended first, then those waiting by order of arrival', function () {
    $this->travelTo('2026-10-26 15:00');
    [, $team] = actingAsTeamMember(TeamRole::Member);

    $person = fn (string $name) => AssistedPerson::factory()->for($team)->state(['name' => $name]);
    $recordType = AppointmentType::factory()->for($team)->withRecord()->create();

    Appointment::factory()->for($team)->waiting()->for($person('Arrived last'))->create(['received_at' => now()->subMinutes(5), 'appointment_type_id' => $recordType->id]);
    Appointment::factory()->for($team)->waiting()->for($person('Arrived first'))->create(['received_at' => now()->subMinutes(40), 'appointment_type_id' => $recordType->id]);
    Appointment::factory()->for($team)->inProgress()->for($person('Being attended'))->create();
    Appointment::factory()->for($team)->completed()->for($person('Already attended'))->create();
    Appointment::factory()->for($team)->for($person('Not arrived'))->create(['scheduled_on' => '2026-10-26']);

    Livewire::test('pages::dashboard.index')
        ->assertSeeInOrder(['Being attended', 'Arrived first', '40 min', 'Arrived last', '5 min'])
        ->assertDontSee('Already attended')
        ->assertDontSee('Not arrived')
        ->assertSeeHtml('data-test="dashboard-queue-attend"')
        ->assertSet('printableSheetCount', 2);
});

test('the appointments of the next seven days are counted per day and link to their day in the schedule', function () {
    $this->travelTo('2026-10-26');
    [, $team] = actingAsTeamMember(TeamRole::Member);

    Appointment::factory()->for($team)->count(2)->create(['scheduled_on' => '2026-10-27']);
    Appointment::factory()->for($team)->create(['scheduled_on' => '2026-11-02']);
    Appointment::factory()->for($team)->canceled()->create(['scheduled_on' => '2026-10-27']);
    Appointment::factory()->for($team)->create(['scheduled_on' => '2026-11-03']);

    $component = Livewire::test('pages::dashboard.index')
        ->assertSeeHtml(route('appointments.index', ['date' => '2026-10-27']));

    expect($component->instance()->upcomingCounts)->toBe([
        '2026-10-27' => 2,
        '2026-10-28' => 0,
        '2026-10-29' => 0,
        '2026-10-30' => 0,
        '2026-10-31' => 0,
        '2026-11-01' => 0,
        '2026-11-02' => 1,
    ]);
});

test('the pending appointments and the loans needing attention link to their filtered lists', function () {
    $this->travelTo('2026-10-26');
    [, $team] = actingAsTeamMember(TeamRole::Member);

    Appointment::factory()->for($team)->count(2)->create(['scheduled_on' => '2026-10-23']);
    Appointment::factory()->for($team)->completed()->create(['scheduled_on' => '2026-10-23']);
    lendBookTitled($team, 'Overdue', ['due_on' => '2026-10-20']);
    lendBookTitled($team, 'Due soon', ['due_on' => '2026-10-28']);
    lendBookTitled($team, 'Due later', ['due_on' => '2026-11-10']);

    Livewire::test('pages::dashboard.index')
        ->assertSet('pendingCount', 2)
        ->assertSet('overdueLoanCount', 1)
        ->assertSet('dueSoonLoanCount', 1)
        ->assertSeeHtml(route('appointments.index', ['pending' => 1]))
        ->assertSeeHtml(route('library.loans.index', ['status' => BookLoanStatus::Overdue->value]))
        ->assertDontSeeHtml('data-test="dashboard-attention-empty"');
});

test('only the overdue loans are listed, the longest overdue first', function () {
    $this->travelTo('2026-10-26');
    [, $team] = actingAsTeamMember(TeamRole::Member);

    lendBookTitled($team, 'Lately overdue', ['due_on' => '2026-10-24']);
    lendBookTitled($team, 'Long overdue', ['due_on' => '2026-10-10']);
    lendBookTitled($team, 'Due soon', ['due_on' => '2026-10-28']);
    lendBookTitled($team, 'Returned', ['loaned_on' => '2026-10-06', 'due_on' => '2026-10-20', 'returned_on' => '2026-10-19']);

    $component = Livewire::test('pages::dashboard.index')
        ->assertSeeInOrder(['Long overdue', 'Lately overdue'])
        ->assertDontSee('Returned');

    expect($component->instance()->overdueLoans->pluck('copy.book.title')->all())->toBe(['Long overdue', 'Lately overdue']);
});

test('the attention card says all is caught up when nothing needs attention', function () {
    actingAsTeamMember(TeamRole::Member);

    Livewire::test('pages::dashboard.index')
        ->assertSeeHtml('data-test="dashboard-attention-empty"');
});

test('the data of another team is left out', function () {
    $this->travelTo('2026-10-26');
    actingAsTeamMember(TeamRole::Member);
    $otherTeam = Team::factory()->create();

    Appointment::factory()->for($otherTeam)->create(['scheduled_on' => '2026-10-26']);
    Appointment::factory()->for($otherTeam)->waiting()->create();
    Appointment::factory()->for($otherTeam)->create(['scheduled_on' => '2026-10-23']);
    Appointment::factory()->for($otherTeam)->create(['scheduled_on' => '2026-10-27']);
    lendBookTitled($otherTeam, 'Other center', ['loaned_on' => '2026-10-06', 'due_on' => '2026-10-20']);

    $component = Livewire::test('pages::dashboard.index')
        ->assertSet('pendingCount', 0)
        ->assertSet('printableSheetCount', 0)
        ->assertSet('overdueLoanCount', 0);

    expect($component->instance()->todayCounts)->toBe([])
        ->and($component->instance()->queue)->toBeEmpty()
        ->and(array_sum($component->instance()->upcomingCounts))->toBe(0)
        ->and($component->instance()->overdueLoans)->toBeEmpty();
});
