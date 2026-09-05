<?php

use App\Models\User;

test('the sidebar user menu labels the user with their first and last name', function () {
    $user = User::factory()->create(['name' => 'Guilherme Souza Policarpo Silva']);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('>Guilherme Silva</span>', false);
    $response->assertDontSee(">{$user->name}</span>", false);
});
