<?php

declare(strict_types=1);

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * The bug this guards against is silent and only appears in production.
 *
 * Every signed-out rate limiter in AppServiceProvider keys on
 * `$request->user()?->id ?: $request->ip()`. Behind an untrusted proxy that IP
 * is the proxy's for every visitor on earth, so `bored` at 30/min stops being
 * 30 presses per visitor and becomes 30 presses per minute for the whole
 * internet — the product's headline control, throttled globally, with nothing
 * in the logs to say why. `X-Forwarded-Proto` is ignored in the same breath, so
 * the application builds http:// URLs while sitting behind TLS.
 *
 * Laravel's test client connects from 127.0.0.1, which the shipped default
 * trusts — so these assertions exercise the real wiring rather than a stub.
 */

beforeEach(function () {
    Route::middleware('web')->get('/_proxy-probe', fn (Request $request) => [
        'ip' => $request->ip(),
        'secure' => $request->isSecure(),
        'host' => $request->getHost(),
    ]);
});

it('reads the visitor address through a trusted proxy', function () {
    $this->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
        ->get('/_proxy-probe')
        ->assertOk()
        ->assertJsonPath('ip', '203.0.113.9');
});

it('sees TLS terminated at a trusted proxy', function () {
    // Without this the app builds http:// URLs behind https, which breaks
    // absolute links, redirects and anything that signs a URL.
    $this->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->get('/_proxy-probe')
        ->assertOk()
        ->assertJsonPath('secure', true);
});

it('gives two visitors behind one proxy two different rate-limit keys', function () {
    // The property the limiters actually depend on. If this collapses to one
    // value, every guest shares a bucket.
    $first = $this->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
        ->get('/_proxy-probe')->json('ip');

    $second = $this->withHeaders(['X-Forwarded-For' => '198.51.100.4'])
        ->get('/_proxy-probe')->json('ip');

    expect($first)->not->toBe($second)
        ->and($first)->toBe('203.0.113.9')
        ->and($second)->toBe('198.51.100.4');
});

it('ignores forwarded headers from an address it does not trust', function () {
    /*
     * The other half of the contract, and the reason the default list contains
     * no public address: a header is only believed when the connection carrying
     * it comes from somewhere on the list. Narrow the trust to a host the test
     * client is not, and the spoof is discarded.
     */
    config(['devlab.trusted_proxies' => ['10.0.0.1']]);
    TrustProxies::at(['10.0.0.1']);

    $this->withHeaders([
        'X-Forwarded-For' => '203.0.113.9',
        'X-Forwarded-Proto' => 'https',
    ])
        ->get('/_proxy-probe')
        ->assertOk()
        ->assertJsonPath('ip', '127.0.0.1')
        ->assertJsonPath('secure', false);
});

it('defaults to trusting loopback and the private ranges only', function () {
    $configured = config('devlab.trusted_proxies');

    expect($configured)->toContain('127.0.0.1')
        ->and($configured)->toContain('10.0.0.0/8')
        ->and($configured)->not->toContain('*');
});
