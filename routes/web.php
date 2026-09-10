<?php

use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');

        Route::livewire('assisted-people', 'pages::assisted-people.index')->name('assisted-people.index');
        Route::livewire('assisted-people/create', 'pages::assisted-people.create')->name('assisted-people.create');
        Route::livewire('assisted-people/{assistedPerson}/edit', 'pages::assisted-people.edit')->name('assisted-people.edit');
    });

require __DIR__.'/settings.php';
