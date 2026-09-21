<?php

use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->scopeBindings()
    ->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');

        Route::livewire('assisted-people', 'pages::assisted-people.index')->name('assisted-people.index');
        Route::livewire('assisted-people/create', 'pages::assisted-people.create')->name('assisted-people.create');
        Route::livewire('assisted-people/{assistedPerson}/edit', 'pages::assisted-people.edit')->name('assisted-people.edit');

        Route::livewire('appointments', 'pages::appointments.index')->name('appointments.index');
        Route::livewire('appointments/create', 'pages::appointments.create')->name('appointments.create');
        Route::livewire('appointments/{appointment}/edit', 'pages::appointments.edit')->name('appointments.edit');
        Route::livewire('appointments/{appointment}/attend', 'pages::appointments.attend')->name('appointments.attend');
    });

require __DIR__.'/settings.php';
