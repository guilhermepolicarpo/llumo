<?php

use App\Models\Team;
use App\Models\User;

test('install creates a verified user who owns the first center', function () {
    $this->artisan('llumo:install')
        ->expectsQuestion('Nome do Centro Espírita', 'Centro Espírita Luz')
        ->expectsQuestion('Seu nome', 'Maria Souza')
        ->expectsQuestion('Endereço de e-mail', 'maria@example.com')
        ->expectsQuestion('Senha', 'password')
        ->expectsQuestion('Confirmação de senha', 'password')
        ->assertSuccessful();

    $user = User::where('email', 'maria@example.com')->sole();
    $team = Team::where('name', 'Centro Espírita Luz')->sole();

    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->ownsTeam($team))->toBeTrue()
        ->and($user->current_team_id)->toBe($team->id);
});

test('install refuses to run when the installation already has a center', function () {
    Team::factory()->create();

    $this->artisan('llumo:install')
        ->expectsOutputToContain('Esta instalação já tem um Centro Espírita.')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

test('install rejects passwords that do not match', function () {
    $this->artisan('llumo:install')
        ->expectsQuestion('Nome do Centro Espírita', 'Centro Espírita Luz')
        ->expectsQuestion('Seu nome', 'Maria Souza')
        ->expectsQuestion('Endereço de e-mail', 'maria@example.com')
        ->expectsQuestion('Senha', 'password')
        ->expectsQuestion('Confirmação de senha', 'different')
        ->assertFailed();

    expect(User::count())->toBe(0)
        ->and(Team::count())->toBe(0);
});
