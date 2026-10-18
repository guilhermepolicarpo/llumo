<?php

use App\Rules\Isbn;

test('a valid isbn-13 is kept and its separators dropped', function (string $typed) {
    expect(Isbn::toIsbn13($typed))->toBe('9788573289442');
})->with([
    'plain' => '9788573289442',
    'with dashes' => '978-85-7328-944-2',
    'with spaces' => ' 978 85 7328 944 2 ',
]);

test('a valid isbn-10 is converted to its isbn-13', function (string $typed, string $isbn13) {
    expect(Isbn::toIsbn13($typed))->toBe($isbn13);
})->with([
    'numeric check digit' => ['8573289449', '9788573289442'],
    'x check digit' => ['0-8044-2957-X', '9780804429573'],
    'lower case x' => ['080442957x', '9780804429573'],
]);

test('an isbn with a wrong check digit or length is rejected', function (string $typed) {
    expect(Isbn::toIsbn13($typed))->toBeNull();
})->with([
    'isbn-13 wrong check digit' => '9788573289443',
    'isbn-10 wrong check digit' => '8573289448',
    'too short' => '978857328944',
    'letters' => '97885732894AB',
]);
