<?php

use App\Models\User;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;

/**
 * Get the address of the site link carrying the given data-test attribute.
 */
function siteLinkHref(TestResponse $response, string $dataTest): string
{
    preg_match('/<a href="([^"]+)"[^>]*data-test="'.$dataTest.'"/', (string) $response->getContent(), $matches);

    return $matches[1];
}

test('the site runs no database queries and sets no cookies so the CDN can cache it', function () {
    $this->expectsDatabaseQueryCount(0);

    $response = $this->get(route('home'))->assertOk();

    expect($response->headers->getCookies())->toBeEmpty();
});

test('the site tells shared caches and browsers how long to keep it', function () {
    $cacheControl = $this->get(route('home'))->headers->get('Cache-Control');

    expect($cacheControl)->toContain('public', 'max-age=300', 's-maxage=604800');
});

test('the site is the same for guests and signed-in people', function () {
    $user = User::factory()->create();

    // The font preloads are printed only once per application instance, so the page settles after the first request.
    $this->get(route('home'));

    $guestPage = $this->get(route('home'))->assertOk()->getContent();
    $memberPage = $this->actingAs($user)->get(route('home'))->assertOk()->getContent();

    expect($memberPage)->toBe($guestPage);
});

test('a copy of the site in the CDN needs no stylesheet file outside of development', function () {
    Vite::useHotFile(storage_path('framework/testing/no-hot-file'));

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeHtml('<style>')
        ->assertDontSeeHtml('rel="stylesheet"');
});

test('a closed installation sends visitors to llumo cloud and only offers logging in', function () {
    $response = $this->get(route('home'))
        ->assertOk()
        ->assertDontSeeHtml('data-test="nav-register"');

    expect(siteLinkHref($response, 'hero-primary-action'))->toBe('https://llumo.app.br')
        ->and(siteLinkHref($response, 'nav-login'))->toBe(route('login'));
});

test('an installation with open registration sends the main action to its own registration', function () {
    config(['llumo.open_registration' => true]);

    $response = $this->get(route('home'))->assertOk();

    expect(siteLinkHref($response, 'hero-primary-action'))->toBe(route('register'))
        ->and(siteLinkHref($response, 'nav-register'))->toBe(route('register'))
        ->and(siteLinkHref($response, 'nav-login'))->toBe(route('login'));
});

test('the llumo cloud card points to the hosted service on any installation', function (bool $hasOpenRegistration) {
    config(['llumo.open_registration' => $hasOpenRegistration]);

    $response = $this->get(route('home'))->assertOk();

    expect(siteLinkHref($response, 'how-to-use-cloud'))->toBe('https://llumo.app.br/register');
})->with([
    'open registration' => true,
    'closed registration' => false,
]);

test('search engines are pointed to llumo cloud from any installation', function () {
    config(['app.url' => 'https://casa.example']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeHtml('<link rel="canonical" href="https://llumo.app.br/">')
        ->assertSeeHtml('<meta property="og:url" content="https://llumo.app.br/">');
});

test('the site describes the product to search engines as structured data', function () {
    $content = (string) $this->get(route('home'))->assertOk()->getContent();

    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $content, $matches);

    expect(json_decode($matches[1], true))
        ->toMatchArray(['@type' => 'SoftwareApplication', 'name' => 'llumo', 'url' => 'https://llumo.app.br/']);
});

test('other pages keep their own address in the link preview', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSeeHtml('<meta property="og:url" content="'.route('login').'">');
});

test('a forged proxy prefix can not change the links of the cached page', function () {
    TrustProxies::at('*');

    $response = $this->withHeader('X-Forwarded-Prefix', '.example.invalid')
        ->get(route('home'))
        ->assertOk()
        ->assertDontSee('example.invalid');

    expect(siteLinkHref($response, 'nav-login'))->toBe(route('login'));

    TrustProxies::flushState();
});
