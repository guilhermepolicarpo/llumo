<?php

use App\Models\User;

test('first name returns the first word of the name', function () {
    $user = new User(['name' => 'Guilherme Souza Policarpo']);

    expect($user->firstName())->toBe('Guilherme');
});

test('first name returns the full name when there is no last name', function () {
    $user = new User(['name' => 'Guilherme']);

    expect($user->firstName())->toBe('Guilherme');
});

test('first name ignores extra whitespace', function () {
    $user = new User(['name' => '  Guilherme   Policarpo  ']);

    expect($user->firstName())->toBe('Guilherme');
});

test('first and last name returns the first and last words of the name', function () {
    $user = new User(['name' => 'Guilherme Souza Policarpo dos Santos']);

    expect($user->firstAndLastName())->toBe('Guilherme Santos');
});

test('first and last name returns the full name when there are only two words', function () {
    $user = new User(['name' => 'Guilherme Policarpo']);

    expect($user->firstAndLastName())->toBe('Guilherme Policarpo');
});

test('first and last name returns the single name when there is no last name', function () {
    $user = new User(['name' => 'Guilherme']);

    expect($user->firstAndLastName())->toBe('Guilherme');
});

test('first and last name ignores extra whitespace', function () {
    $user = new User(['name' => '  Guilherme   Souza   Policarpo  ']);

    expect($user->firstAndLastName())->toBe('Guilherme Policarpo');
});
