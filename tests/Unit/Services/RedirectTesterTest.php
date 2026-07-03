<?php

use App\Services\RedirectTester;
use App\Services\RedirectTesting\DnsResolver;
use App\Services\RedirectTesting\UrlSafetyException;
use Illuminate\Support\Facades\Http;

function redirectTesterWithDns(array $records = ['example.com' => ['93.184.216.34']]): RedirectTester
{
    return new RedirectTester(new class($records) implements DnsResolver
    {
        public function __construct(private readonly array $records) {}

        public function resolve(string $host): array
        {
            return $this->records[$host] ?? [];
        }
    });
}

it('normalizes urls and defaults to https', function () {
    $tester = redirectTesterWithDns();

    expect($tester->normalizeUrl('Example.com/path?x=1'))->toBe('https://example.com/path?x=1')
        ->and($tester->normalizeUrl('HTTP://Example.com'))->toBe('http://example.com/');
});

it('rejects unsupported schemes and embedded credentials', function () {
    $tester = redirectTesterWithDns();

    expect(fn () => $tester->normalizeUrl('ftp://example.com'))->toThrow(UrlSafetyException::class)
        ->and(fn () => $tester->normalizeUrl('https://user:pass@example.com'))->toThrow(UrlSafetyException::class)
        ->and($tester->test('ftp://example.com')->toArray()['warnings'][0]['code'])->toBe('unsupported_scheme');
});

it('blocks localhost private reserved addresses and disallowed ports', function (string $url, string $code) {
    Http::preventStrayRequests();

    $result = redirectTesterWithDns([
        'localhost' => ['127.0.0.1'],
        'private.test' => ['10.0.0.10'],
        'reserved.test' => ['203.0.113.10'],
        'port.test' => ['93.184.216.34'],
    ])->test($url)->toArray();

    expect($result['warnings'][0]['code'])->toBe($code)
        ->and($result['chain'])->toBe([]);
})->with([
    'localhost' => ['http://localhost', 'blocked_ip'],
    'private' => ['http://private.test', 'blocked_ip'],
    'reserved' => ['http://reserved.test', 'blocked_ip'],
    'port' => ['http://port.test:81', 'blocked_port'],
]);

it('allows standard and configured alternate ports', function (string $url) {
    Http::fake([
        '*' => Http::response('ok', 200, ['Content-Type' => 'text/plain']),
    ]);

    $result = redirectTesterWithDns(['example.com' => ['93.184.216.34']])->test($url)->toArray();

    expect($result['final_status'])->toBe(200)
        ->and($result['warnings'])->sequence(
            fn ($warning) => $warning->code->toBe('html_not_scanned'),
        );
})->with([
    'http' => ['http://example.com'],
    'https' => ['https://example.com'],
    '8080' => ['http://example.com:8080'],
    '8443' => ['https://example.com:8443'],
]);

it('resolves relative redirects and normalizes comparable urls', function () {
    $tester = redirectTesterWithDns();

    expect($tester->resolveUrl('https://example.com/a/b/page', '../target?x=1'))->toBe('https://example.com/a/target?x=1')
        ->and($tester->resolveUrl('https://example.com/a/b/page', '/root'))->toBe('https://example.com/root')
        ->and($tester->comparableUrl('https://Example.com:443/path'))->toBe('https://example.com/path');
});

it('extracts canonical urls and detects mismatch', function () {
    $tester = redirectTesterWithDns();

    $matching = $tester->extractCanonical('<link rel="canonical" href="/final">', 'https://example.com/final');
    $mismatched = $tester->extractCanonical('<link rel="canonical" href="https://example.com/other">', 'https://example.com/final');

    expect($matching['url'])->toBe('https://example.com/final')
        ->and($matching['matches_final_url'])->toBeTrue()
        ->and($mismatched['matches_final_url'])->toBeFalse();
});

it('returns stable json output with the selected user agent and security headers', function () {
    Http::fake([
        'https://example.com/' => Http::response('<link rel="canonical" href="https://example.com/">', 200, [
            'Content-Type' => 'text/html',
            'Strict-Transport-Security' => 'max-age=31536000',
            'X-Content-Type-Options' => 'nosniff',
        ]),
    ]);

    $result = redirectTesterWithDns()->test('https://example.com', 'googlebot')->toArray();

    expect($result)->toHaveKeys([
        'requested_url',
        'normalized_url',
        'final_url',
        'final_status',
        'final_content_type',
        'redirect_count',
        'duration_ms',
        'https_upgrade',
        'user_agent',
        'chain',
        'canonical',
        'security_headers',
        'warnings',
        'generated_at',
    ])
        ->and($result['user_agent']['label'])->toBe('Googlebot')
        ->and($result['security_headers']['present'])->toHaveKeys(['strict-transport-security', 'x-content-type-options'])
        ->and($result['security_headers']['missing'])->toContain('content-security-policy');
});
