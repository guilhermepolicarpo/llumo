<?php

use App\Enums\BookLoanStatus;
use App\Enums\TeamRole;
use App\Models\AssistedPerson;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookLoan;
use App\Models\Team;
use Livewire\Livewire;

/**
 * Create an available copy of a new book of the team.
 */
function availableCopy(Team $team): BookCopy
{
    return BookCopy::factory()->for(Book::factory()->for($team))->create();
}

test('a copy is lent with the due date suggested by the team loan period', function () {
    $this->travelTo('2026-10-18');
    [$user, $team] = actingAsTeamMember(TeamRole::Member);
    $team->update(['loan_period_days' => 21]);

    $copy = availableCopy($team);
    $person = AssistedPerson::factory()->for($team)->create();

    Livewire::test('pages::library.loans.create')
        ->assertSet('dueOn', '2026-11-08')
        ->call('selectAssistedPerson', $person->id)
        ->call('selectBookCopy', $copy->id)
        ->call('lendBookCopy')
        ->assertHasNoErrors()
        ->assertRedirectToRoute('library.loans.index');

    expect($team->bookLoans()->sole())
        ->book_copy_id->toBe($copy->id)
        ->assisted_person_id->toBe($person->id)
        ->due_on->toDateString()->toBe('2026-11-08')
        ->registered_by->toBe($user->id);
});

test('the copy and the borrower can be preselected from the link that opened the form', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);

    $copy = availableCopy($team);
    $person = AssistedPerson::factory()->for($team)->create();

    Livewire::withQueryParams(['book_copy' => $copy->id, 'assisted_person' => $person->id])
        ->test('pages::library.loans.create')
        ->assertSet('bookCopyId', $copy->id)
        ->assertSet('assistedPersonId', $person->id);
});

test('a copy already lent cannot be lent again', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);

    $copy = availableCopy($team);
    BookLoan::factory()->for($copy, 'copy')->create();

    Livewire::test('pages::library.loans.create')
        ->call('selectAssistedPerson', AssistedPerson::factory()->for($team)->create()->id)
        ->set('bookCopyId', $copy->id)
        ->call('lendBookCopy')
        ->assertHasErrors('bookCopyId');

    expect($copy->loans()->count())->toBe(1);
});

test('an assisted person with an overdue book cannot borrow another one', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);

    $person = AssistedPerson::factory()->for($team)->create();
    BookLoan::factory()->for(availableCopy($team), 'copy')->for($person)->overdue()->create();

    Livewire::test('pages::library.loans.create')
        ->call('selectAssistedPerson', $person->id)
        ->call('selectBookCopy', availableCopy($team)->id)
        ->call('lendBookCopy')
        ->assertHasErrors(['assistedPersonId' => __('This assisted person has an overdue book. Register its return or renew it before lending another one.')]);
});

test('an assisted person cannot hold more books than the team allows', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);
    $team->update(['loan_max_active_per_person' => 2]);

    $person = AssistedPerson::factory()->for($team)->create();
    BookLoan::factory()->count(2)->sequence(fn () => ['book_copy_id' => availableCopy($team)->id])->for($person)->create();
    BookLoan::factory()->for(availableCopy($team), 'copy')->for($person)->returned()->create();

    Livewire::test('pages::library.loans.create')
        ->call('selectAssistedPerson', $person->id)
        ->call('selectBookCopy', availableCopy($team)->id)
        ->call('lendBookCopy')
        ->assertHasErrors('assistedPersonId');

    $team->update(['loan_max_active_per_person' => 3]);

    Livewire::test('pages::library.loans.create')
        ->call('selectAssistedPerson', $person->id)
        ->call('selectBookCopy', availableCopy($team)->id)
        ->call('lendBookCopy')
        ->assertHasNoErrors();
});

test('copies of another team cannot be lent', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);

    Livewire::test('pages::library.loans.create')
        ->call('selectAssistedPerson', AssistedPerson::factory()->for($team)->create()->id)
        ->set('bookCopyId', availableCopy(Team::factory()->create())->id)
        ->call('lendBookCopy')
        ->assertHasErrors('bookCopyId');
});

test('a loan is renewed by the loan period from its due date, up to the team limit', function () {
    $this->travelTo('2026-10-18');
    [, $team] = actingAsTeamMember(TeamRole::Member);
    $team->update(['loan_period_days' => 14, 'loan_max_renewals' => 1]);

    $loan = BookLoan::factory()->for(availableCopy($team), 'copy')->create(['loaned_on' => '2026-10-10', 'due_on' => '2026-10-24']);

    Livewire::test('pages::library.loans.index')
        ->call('renewLoan', $loan->id)
        ->call('renewLoan', $loan->id);

    expect($loan->fresh())
        ->due_on->toDateString()->toBe('2026-11-07')
        ->renewals_count->toBe(1);
});

test('an overdue loan is renewed from today', function () {
    $this->travelTo('2026-10-18');
    [, $team] = actingAsTeamMember(TeamRole::Member);

    $loan = BookLoan::factory()->for(availableCopy($team), 'copy')->create(['loaned_on' => '2026-09-01', 'due_on' => '2026-09-15']);

    Livewire::test('pages::library.loans.index')->call('renewLoan', $loan->id);

    expect($loan->fresh()->due_on->toDateString())->toBe('2026-11-01');
});

test('returning a loan frees the copy and records who received it', function () {
    [$user, $team] = actingAsTeamMember(TeamRole::Member);

    $loan = BookLoan::factory()->for(availableCopy($team), 'copy')->create(['loaned_on' => today()->subDays(3)]);

    Livewire::test('pages::library.loans.index')
        ->call('confirmReturn', $loan->id)
        ->assertSet('returnedOn', today()->toDateString())
        ->call('returnLoan')
        ->assertHasNoErrors();

    expect($loan->fresh())
        ->returned_on->toDateString()->toBe(today()->toDateString())
        ->returned_to->toBe($user->id)
        ->and($loan->copy->fresh()->isAvailable())->toBeTrue();
});

test('the return date cannot be in the future', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);

    $loan = BookLoan::factory()->for(availableCopy($team), 'copy')->create();

    Livewire::test('pages::library.loans.index')
        ->call('confirmReturn', $loan->id)
        ->set('returnedOn', today()->addDay()->toDateString())
        ->call('returnLoan')
        ->assertHasErrors('returnedOn');
});

test('the status of a loan follows from its dates', function () {
    $this->travelTo('2026-10-18');
    [, $team] = actingAsTeamMember(TeamRole::Member);

    $dueToday = BookLoan::factory()->for(availableCopy($team), 'copy')->create(['due_on' => '2026-10-18']);
    $overdue = BookLoan::factory()->for(availableCopy($team), 'copy')->create(['due_on' => '2026-10-17']);
    $returned = BookLoan::factory()->for(availableCopy($team), 'copy')->create(['due_on' => '2026-10-01', 'returned_on' => '2026-10-02']);

    expect($dueToday->status)->toBe(BookLoanStatus::Open)
        ->and($overdue->status)->toBe(BookLoanStatus::Overdue)
        ->and($returned->status)->toBe(BookLoanStatus::Returned)
        ->and($team->bookLoans()->overdue()->pluck('id')->all())->toBe([$overdue->id]);
});

test('the loans list shows the books to return by default and can show the overdue or returned ones', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);

    $person = AssistedPerson::factory()->for($team)->create(['name' => 'Maria Silva']);
    BookLoan::factory()->for(BookCopy::factory()->for(Book::factory()->for($team)->state(['title' => 'Nosso Lar'])), 'copy')->for($person)->create();
    BookLoan::factory()->for(BookCopy::factory()->for(Book::factory()->for($team)->state(['title' => 'Os Mensageiros'])), 'copy')->for($person)->overdue()->create();
    BookLoan::factory()->for(BookCopy::factory()->for(Book::factory()->for($team)->state(['title' => 'Missionários da Luz'])), 'copy')->for($person)->returned()->create();

    Livewire::test('pages::library.loans.index')
        ->assertSee(['Nosso Lar', 'Os Mensageiros'])
        ->assertDontSee('Missionários da Luz')
        ->set('status', BookLoanStatus::Overdue->value)
        ->assertSee('Os Mensageiros')
        ->assertDontSee('Nosso Lar')
        ->set('status', BookLoanStatus::Returned->value)
        ->assertSee('Missionários da Luz')
        ->assertDontSee('Os Mensageiros');
});

test('the loans list opens on the status named in the link', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);

    lendBookTitled($team, 'Nosso Lar');
    lendBookTitled($team, 'Os Mensageiros', ['loaned_on' => today()->subDays(20)->toDateString(), 'due_on' => today()->subDays(6)->toDateString()]);

    Livewire::withQueryParams(['status' => BookLoanStatus::Overdue->value])
        ->test('pages::library.loans.index')
        ->assertSet('status', BookLoanStatus::Overdue->value)
        ->assertSee('Os Mensageiros')
        ->assertDontSee('Nosso Lar');
});

test('the history keeps the loans of a deleted assisted person', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);

    $person = AssistedPerson::factory()->for($team)->create(['name' => 'Maria Silva']);
    $loan = BookLoan::factory()->for(availableCopy($team), 'copy')->for($person)->returned()->create();
    $person->delete();

    Livewire::test('pages::library.books.show', ['book' => $loan->copy->book])
        ->assertSee('Maria Silva');
});

test('the assisted person page lists the books lent to them', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);

    $person = AssistedPerson::factory()->for($team)->create();
    BookLoan::factory()->for(BookCopy::factory()->for(Book::factory()->for($team)->state(['title' => 'Nosso Lar'])), 'copy')->for($person)->create();

    Livewire::test('pages::assisted-people.show', ['assistedPerson' => $person])
        ->assertSee('Nosso Lar');
});

test('loans of another team cannot be returned or renewed', function () {
    actingAsTeamMember(TeamRole::Member);

    $foreignLoan = BookLoan::factory()->for(availableCopy(Team::factory()->create()), 'copy')->create();

    Livewire::test('pages::library.loans.index')
        ->call('renewLoan', $foreignLoan->id)
        ->assertNotFound();

    expect($foreignLoan->fresh()->renewals_count)->toBe(0);
});

test('the loan list shows 5 loans per page by default and lets the member show more', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);
    BookLoan::factory()->count(6)->sequence(fn () => ['book_copy_id' => availableCopy($team)->id])->create();

    $component = Livewire::test('pages::library.loans.index');

    expect($component->get('loans')->perPage())->toBe(5)
        ->and($component->get('loans')->count())->toBe(5);

    $component->set('perPage', 10);

    expect($component->get('loans')->count())->toBe(6);
});

test('the loan list tells how long each open loan has until it is due', function () {
    $this->travelTo('2026-10-18');
    [, $team] = actingAsTeamMember(TeamRole::Member);
    BookLoan::factory()->for(availableCopy($team), 'copy')->create(['loaned_on' => '2026-10-01', 'due_on' => '2026-10-15']);
    BookLoan::factory()->for(availableCopy($team), 'copy')->create(['loaned_on' => '2026-10-04', 'due_on' => '2026-10-18']);
    BookLoan::factory()->for(availableCopy($team), 'copy')->create(['loaned_on' => '2026-10-06', 'due_on' => '2026-10-20', 'renewals_count' => 1]);

    Livewire::test('pages::library.loans.index')
        ->assertSeeInOrder([
            trans_choice('Overdue by :count day|Overdue by :count days', 3),
            __('Due today'),
            trans_choice('Due in :count day|Due in :count days', 2),
            trans_choice('Renewed :count time|Renewed :count times', 1),
        ]);
});

test('the new loan page shows the books the chosen person still holds against the open loans limit', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);
    $team->update(['loan_max_active_per_person' => 3]);
    $person = AssistedPerson::factory()->for($team)->create(['name' => 'Taís Fernandes']);
    $copy = BookCopy::factory()->for(Book::factory()->for($team)->create(['title' => 'Nosso Lar']))->create();
    BookLoan::factory()->for($copy, 'copy')->for($person)->create();

    Livewire::test('pages::library.loans.create')
        ->assertSeeHtml('data-test="loan-open-loans-empty"')
        ->call('selectAssistedPerson', $person->id)
        ->assertSeeHtml('data-test="loan-open-loans"')
        ->assertSee(__('With :name now', ['name' => 'Taís Fernandes']), false)
        ->assertSee(__(':count of :max open', ['count' => 1, 'max' => 3]))
        ->assertSee('Nosso Lar');
});

test('renewing a loan asks for confirmation in a modal that shows the new due date', function () {
    $this->travelTo('2026-10-18');
    [, $team] = actingAsTeamMember(TeamRole::Member);
    $team->update(['loan_period_days' => 14]);
    $loan = BookLoan::factory()->for(availableCopy($team), 'copy')->create(['loaned_on' => '2026-10-06', 'due_on' => '2026-10-20']);

    Livewire::test('pages::library.loans.index')
        ->call('confirmRenew', $loan->id)
        ->assertSet('renewingLoanId', $loan->id)
        ->assertSeeHtml('data-test="loan-renew-new-due-on"')
        ->assertSee('03/11/2026')
        ->call('renewLoan', $loan->id)
        ->assertSet('renewingLoanId', null);

    expect($loan->fresh()->due_on->toDateString())->toBe('2026-11-03');
});
