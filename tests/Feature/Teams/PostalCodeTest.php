<?php

use App\Rules\PostalCode;
use Illuminate\Support\Facades\Validator;

test('valid postal codes pass', function (string $value) {
    $validator = Validator::make(['cep' => $value], ['cep' => new PostalCode]);

    expect($validator->passes())->toBeTrue();
})->with(['01310100', '01310-100']);

test('invalid postal codes fail', function (mixed $value) {
    $validator = Validator::make(['cep' => $value], ['cep' => new PostalCode]);

    expect($validator->passes())->toBeFalse();
})->with([
    'too short' => '123',
    'too long' => '013101000',
    'letters' => 'abcde-fgh',
    'wrong separator' => '01310.100',
    'not a string' => 1310100,
]);

test('digits keeps only the eight significant digits', function () {
    expect(PostalCode::digits('01310-100'))->toBe('01310100')
        ->and(PostalCode::digits('01310100'))->toBe('01310100')
        ->and(PostalCode::digits('123'))->toBeNull()
        ->and(PostalCode::digits(null))->toBeNull();
});

test('format renders a postal code for display', function () {
    expect(PostalCode::format('01310100'))->toBe('01310-100')
        ->and(PostalCode::format('01310-100'))->toBe('01310-100')
        ->and(PostalCode::format(null))->toBe('')
        ->and(PostalCode::format('123'))->toBe('');
});
