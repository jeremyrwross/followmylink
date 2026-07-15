<?php

use App\Services\RedirectTester;
use App\Services\RedirectTesting\DnsResolver;
use Illuminate\Support\Facades\Http;

function bindRedirectDns(array $records = ['example.com' => ['93.184.216.34'], 'www.example.com' => ['93.184.216.34']]): void
{
    app()->bind(DnsResolver::class, fn () => new class($records) implements DnsResolver
    {
        public function __construct(private readonly array $records) {}

        public function resolve(string $host): array
        {
            return $this->records[$host] ?? [];
        }
    });
}

it('follows 301 302 307 and 308 redirect chains manually', function () {
    bindRedirectDns();

    Http::fake([
        'http://example.com/' => Http::response('', 301, ['Location' => 'https://example.com/step-one']),
        'https://example.com/step-one' => Http::response('', 302, ['Location' => '/step-two']),
        'https://example.com/step-two' => Http::response('', 307, ['Location' => '/step-three']),
        'https://example.com/step-three' => Http::response('', 308, ['Location' => '/final']),
        'https://example.com/final' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $result = app(RedirectTester::class)->test('http://example.com')->toArray();

    expect($result['final_url'])->toBe('https://example.com/final')
        ->and($result['redirect_count'])->toBe(4)
        ->and($result['https_upgrade'])->toBeTrue();
});

it('detects redirect loops', function () {
    bindRedirectDns();

    Http::fake([
        'https://example.com/' => Http::response('', 302, ['Location' => '/again']),
        'https://example.com/again' => Http::response('', 302, ['Location' => '/']),
    ]);

    $result = app(RedirectTester::class)->test('https://example.com')->toArray();

    expect(collect($result['warnings'])->pluck('code')->all())->toContain('redirect_loop');
});

it('stops after too many redirects', function () {
    bindRedirectDns();

    Http::fake(function ($request) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?: '/';
        $number = $path === '/' ? 1 : ((int) trim($path, '/')) + 1;

        return Http::response('', 302, ['Location' => '/'.$number]);
    });

    $result = app(RedirectTester::class)->test('https://example.com')->toArray();

    expect($result['chain'])->toHaveCount(10)
        ->and(collect($result['warnings'])->pluck('code')->all())->toContain('too_many_redirects');
});

it('captures final 404 and 500 responses', function (int $status) {
    bindRedirectDns();

    Http::fake([
        'https://example.com/' => Http::response('error', $status, ['Content-Type' => 'text/plain']),
    ]);

    $result = app(RedirectTester::class)->test('https://example.com')->toArray();

    expect($result['final_status'])->toBe($status)
        ->and($result['chain'])->toHaveCount(1);
})->with([404, 500]);

it('follows meta refresh redirects', function () {
    bindRedirectDns();

    Http::fake([
        'https://example.com/' => Http::response('<meta http-equiv="refresh" content="0; url=/next">', 200, ['Content-Type' => 'text/html']),
        'https://example.com/next' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $result = app(RedirectTester::class)->test('https://example.com')->toArray();

    expect($result['final_url'])->toBe('https://example.com/next')
        ->and(collect($result['warnings'])->pluck('code')->all())->toContain('meta_refresh_redirect');
});

it('follows basic javascript redirect heuristics without executing javascript', function () {
    bindRedirectDns();

    Http::fake([
        'https://example.com/' => Http::response('<script>window.location = "/js-next";</script>', 200, ['Content-Type' => 'text/html']),
        'https://example.com/js-next' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $result = app(RedirectTester::class)->test('https://example.com')->toArray();

    expect($result['final_url'])->toBe('https://example.com/js-next')
        ->and(collect($result['warnings'])->pluck('code')->all())->toContain('javascript_redirect');
});

it('warns for unsafe redirect targets', function () {
    bindRedirectDns(['example.com' => ['93.184.216.34'], 'private.test' => ['10.0.0.1']]);

    Http::fake([
        'https://example.com/' => Http::response('', 302, ['Location' => 'http://private.test/']),
    ]);

    $unsafe = app(RedirectTester::class)->test('https://example.com')->toArray();

    expect(collect($unsafe['warnings'])->pluck('code')->all())->toContain('blocked_ip');
});

it('warns when http is not upgraded and when https is downgraded', function () {
    bindRedirectDns();

    Http::fake([
        'http://example.com/' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $httpResult = app(RedirectTester::class)->test('http://example.com')->toArray();

    Http::fake([
        'https://example.com/' => Http::response('', 302, ['Location' => 'http://www.example.com/']),
        'http://www.example.com/' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $downgradeResult = app(RedirectTester::class)->test('https://example.com')->toArray();

    expect(collect($httpResult['warnings'])->pluck('code')->all())->toContain('http_not_upgraded')
        ->and(collect($downgradeResult['warnings'])->pluck('code')->all())->toContain('https_downgrade');
});

it('warns for missing redirect targets', function () {
    bindRedirectDns(['example.com' => ['93.184.216.34']]);

    Http::fake([
        'https://example.com/' => Http::response('', 302),
    ]);

    $missing = app(RedirectTester::class)->test('https://example.com')->toArray();

    expect(collect($missing['warnings'])->pluck('code')->all())->toContain('missing_redirect_target');
});
