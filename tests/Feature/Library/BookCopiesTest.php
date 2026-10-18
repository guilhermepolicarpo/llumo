<?php

use App\Enums\BookCopyStatus;
use App\Enums\TeamRole;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookLoan;
use Livewire\Livewire;

test('new copies are numbered after the highest code the library ever used, deleted copies included', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);

    $book = Book::factory()->for($team)->create();
    BookCopy::factory()->for($book)->create(['code' => 41]);
    BookCopy::factory()->for($book)->trashed()->create(['code' => 42]);
    BookCopy::factory()->for(Book::factory())->create(['code' => 900]);

    Livewire::test('pages::library.books.show', ['book' => $book])
        ->set('newCopiesCount', '2')
        ->call('addCopies')
        ->assertHasNoErrors();

    expect($book->copies()->orderBy('code')->pluck('code')->all())->toBe([41, 43, 44]);
});

test('admins can change a copy code, which no other copy of the library may have used', function () {
    [, $team] = actingAsTeamMember(TeamRole::Admin);

    $book = Book::factory()->for($team)->create();
    $copy = BookCopy::factory()->for($book)->create(['code' => 1]);
    BookCopy::factory()->for($book)->trashed()->create(['code' => 7]);

    $component = Livewire::test('pages::library.books.show', ['book' => $book])
        ->call('editCopy', $copy->id)
        ->set('copyCode', '7')
        ->call('saveCopy')
        ->assertHasErrors('copyCode');

    $component->set('copyCode', '120')
        ->set('copyStatus', BookCopyStatus::Lost->value)
        ->call('saveCopy')
        ->assertHasNoErrors();

    expect($copy->fresh())
        ->code->toBe(120)
        ->status->toBe(BookCopyStatus::Lost);
});

test('a lent copy cannot be marked as lost or deleted', function () {
    [, $team] = actingAsTeamMember(TeamRole::Admin);

    $copy = BookCopy::factory()->for(Book::factory()->for($team))->create();
    BookLoan::factory()->for($copy, 'copy')->create();

    Livewire::test('pages::library.books.show', ['book' => $copy->book])
        ->call('editCopy', $copy->id)
        ->set('copyStatus', BookCopyStatus::Lost->value)
        ->call('saveCopy')
        ->assertHasErrors('copyStatus')
        ->call('confirmDeleteCopy', $copy->id)
        ->call('deleteCopy');

    expect($copy->fresh())
        ->status->toBe(BookCopyStatus::Active)
        ->trashed()->toBeFalse();
});

test('members can add copies but cannot change or delete them', function () {
    [, $team] = actingAsTeamMember(TeamRole::Member);

    $copy = BookCopy::factory()->for(Book::factory()->for($team))->create();

    Livewire::test('pages::library.books.show', ['book' => $copy->book])
        ->call('editCopy', $copy->id)
        ->assertForbidden();

    Livewire::test('pages::library.books.show', ['book' => $copy->book])
        ->call('confirmDeleteCopy', $copy->id)
        ->assertForbidden();
});

test('a copy code can be typed with or without the zeros of its label', function (string $typed, ?int $code) {
    expect(BookCopy::parseCode($typed))->toBe($code);
})->with([
    'padded' => ['0041', 41],
    'plain' => [' 41 ', 41],
    'only zeros' => ['0000', null],
    'a title' => ['Nosso Lar', null],
]);
