<?php

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('login screen has link preview meta tags', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<meta property="og:image" content="'.asset('og-image.png').'">', escape: false)
        ->assertSee('<meta property="og:title" content="'.__('Log in').' - '.config('app.name').'">', escape: false);
});

test('login screen shows the book a quote was taken from', function () {
    config(['quotes' => [[
        'message' => 'Fora da caridade não há salvação.',
        'author' => 'Allan Kardec',
        'book' => 'O Evangelho Segundo o Espiritismo',
    ]]]);

    $this->get(route('login'))
        ->assertOk()
        ->assertSeeInOrder(['Allan Kardec', 'O Evangelho Segundo o Espiritismo']);
});

test('login screen shows a quote without a book', function () {
    config(['quotes' => [[
        'message' => 'Nunca deixe de tentar.',
        'author' => 'Divaldo Franco',
    ]]]);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Divaldo Franco')
        ->assertDontSee('<cite', escape: false);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('passkey login response redirects to the current team dashboard', function () {
    $user = User::factory()->create();

    $request = Request::create(route('login', absolute: false), 'GET', server: [
        'HTTP_ACCEPT' => 'application/json',
    ]);
    $request->setLaravelSession($this->app['session.store']);
    $request->setUserResolver(fn () => $user);

    $jsonResponse = app(PasskeyLoginResponse::class)->toResponse($request);

    expect($jsonResponse->getData()->redirect)->toBe(route('dashboard', ['current_team' => $user->currentTeam->slug]));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrorsIn('email');

    $this->assertGuest();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});
