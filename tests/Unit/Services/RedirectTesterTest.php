<?php

use App\Services\RedirectTester;
use App\Services\RedirectTesting\CappedResponseBody;
use App\Services\RedirectTesting\DnsResolver;
use App\Services\RedirectTesting\HeaderAnalyzer;
use App\Services\RedirectTesting\UrlSafetyException;
use GuzzleHttp\Psr7\Utils;
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
    }, new HeaderAnalyzer);
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
        ->and($result['security_headers']['checked'])->toBe(10)
        ->and(collect($result['security_headers']['analyses'])->firstWhere('header', 'Strict-Transport-Security')['status'])->toBe('Good')
        ->and(collect($result['security_headers']['analyses'])->firstWhere('header', 'Content-Security-Policy')['status'])->toBe('Missing');
});

it('blocks ipv6 loopback and unspecified addresses', function (string $ipv6, string $description) {
    Http::preventStrayRequests();

    $result = redirectTesterWithDns([
        'ipv6-loopback.test' => [$ipv6],
    ])->test('http://ipv6-loopback.test')->toArray();

    expect($result['warnings'][0]['code'])->toBe('blocked_ip')
        ->and($result['chain'])->toBe([]);
})->with([
    'loopback' => ['::1', 'IPv6 loopback'],
    'unspecified' => ['::', 'IPv6 unspecified'],
]);

it('blocks ipv6 link-local addresses', function (string $ipv6, string $description) {
    Http::preventStrayRequests();

    $result = redirectTesterWithDns([
        'ipv6-linklocal.test' => [$ipv6],
    ])->test('http://ipv6-linklocal.test')->toArray();

    expect($result['warnings'][0]['code'])->toBe('blocked_ip')
        ->and($result['chain'])->toBe([]);
})->with([
    'link-local-1' => ['fe80::1', 'link-local fe80::1'],
    'link-local-2' => ['febf:ffff:ffff:ffff:ffff:ffff:ffff:ffff', 'link-local upper bound'],
]);

it('blocks ipv6 unique-local addresses', function (string $ipv6, string $description) {
    Http::preventStrayRequests();

    $result = redirectTesterWithDns([
        'ipv6-unique-local.test' => [$ipv6],
    ])->test('http://ipv6-unique-local.test')->toArray();

    expect($result['warnings'][0]['code'])->toBe('blocked_ip')
        ->and($result['chain'])->toBe([]);
})->with([
    'unique-local-fc' => ['fc00::1', 'unique-local fc00::/7'],
    'unique-local-fd' => ['fdff:ffff:ffff:ffff:ffff:ffff:ffff:ffff', 'unique-local upper bound'],
]);

it('blocks ipv6 multicast addresses', function (string $ipv6, string $description) {
    Http::preventStrayRequests();

    $result = redirectTesterWithDns([
        'ipv6-multicast.test' => [$ipv6],
    ])->test('http://ipv6-multicast.test')->toArray();

    expect($result['warnings'][0]['code'])->toBe('blocked_ip')
        ->and($result['chain'])->toBe([]);
})->with([
    'multicast-1' => ['ff02::1', 'multicast ff02::1'],
    'multicast-2' => ['ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff', 'multicast upper bound'],
]);

it('blocks ipv6 documentation addresses', function (string $ipv6, string $description) {
    Http::preventStrayRequests();

    $result = redirectTesterWithDns([
        'ipv6-doc.test' => [$ipv6],
    ])->test('http://ipv6-doc.test')->toArray();

    expect($result['warnings'][0]['code'])->toBe('blocked_ip')
        ->and($result['chain'])->toBe([]);
})->with([
    'documentation-1' => ['2001:db8::1', 'documentation 2001:db8::/32'],
    'documentation-2' => ['2001:db8:ffff:ffff:ffff:ffff:ffff:ffff', 'documentation upper bound'],
]);

it('blocks ipv4-mapped ipv6 addresses with private embedded ipv4', function (string $ipv6, string $description) {
    Http::preventStrayRequests();

    $result = redirectTesterWithDns([
        'ipv6-mapped.test' => [$ipv6],
    ])->test('http://ipv6-mapped.test')->toArray();

    expect($result['warnings'][0]['code'])->toBe('blocked_ip')
        ->and($result['chain'])->toBe([]);
})->with([
    'mapped-loopback' => ['::ffff:127.0.0.1', 'IPv4-mapped loopback'],
    'mapped-private-10' => ['::ffff:10.0.0.1', 'IPv4-mapped 10.0.0.0/8'],
    'mapped-private-192' => ['::ffff:192.168.1.1', 'IPv4-mapped 192.168.0.0/16'],
    'mapped-reserved-203' => ['::ffff:203.0.113.10', 'IPv4-mapped documentation'],
    'mapped-expanded-loopback' => ['0:0:0:0:0:ffff:127.0.0.1', 'expanded IPv4-mapped loopback'],
]);

it('blocks deprecated ipv4-compatible ipv6 addresses', function (string $ipv6) {
    Http::preventStrayRequests();

    $result = redirectTesterWithDns([
        'ipv6-compatible.test' => [$ipv6],
    ])->test('http://ipv6-compatible.test')->toArray();

    expect($result['warnings'][0]['code'])->toBe('blocked_ip')
        ->and($result['chain'])->toBe([]);
})->with([
    'compatible-loopback' => '::127.0.0.1',
    'compatible-loopback-hex' => '::7f00:1',
]);

it('blocks ipv4-mapped ipv6 addresses with public embedded ipv4 for safety', function (string $ipv6, string $description) {
    Http::preventStrayRequests();

    $result = redirectTesterWithDns([
        'ipv6-mapped-public.test' => [$ipv6],
    ])->test('http://ipv6-mapped-public.test')->toArray();

    expect($result['warnings'][0]['code'])->toBe('blocked_ip')
        ->and($result['chain'])->toBe([]);
})->with([
    'mapped-public-google' => ['::ffff:8.8.8.8', 'IPv4-mapped public DNS'],
    'mapped-public-cloudflare' => ['::ffff:1.1.1.1', 'IPv4-mapped public DNS'],
]);

it('blocks ipv4/ipv6 translation addresses with private embedded ipv4', function (string $ipv6, string $description) {
    Http::preventStrayRequests();

    $result = redirectTesterWithDns([
        'ipv6-translation.test' => [$ipv6],
    ])->test('http://ipv6-translation.test')->toArray();

    expect($result['warnings'][0]['code'])->toBe('blocked_ip')
        ->and($result['chain'])->toBe([]);
})->with([
    'translation-loopback' => ['64:ff9b::7f00:1', 'translation 127.0.0.1'],
    'translation-private-10' => ['64:ff9b::a00:1', 'translation 10.0.0.1'],
    'translation-private-192' => ['64:ff9b::c0a8:0101', 'translation 192.168.1.1'],
]);

it('blocks ipv4/ipv6 translation addresses with public embedded ipv4 for safety', function (string $ipv6, string $description) {
    Http::preventStrayRequests();

    $result = redirectTesterWithDns([
        'ipv6-translation-public.test' => [$ipv6],
    ])->test('http://ipv6-translation-public.test')->toArray();

    expect($result['warnings'][0]['code'])->toBe('blocked_ip')
        ->and($result['chain'])->toBe([]);
})->with([
    'translation-public-google' => ['64:ff9b::808:808', 'translation 8.8.8.8'],
    'translation-public-cloudflare' => ['64:ff9b::101:101', 'translation 1.1.1.1'],
]);

it('blocks reserved ipv6 ranges', function (string $ipv6, string $description) {
    Http::preventStrayRequests();

    $result = redirectTesterWithDns([
        'ipv6-reserved.test' => [$ipv6],
    ])->test('http://ipv6-reserved.test')->toArray();

    expect($result['warnings'][0]['code'])->toBe('blocked_ip')
        ->and($result['chain'])->toBe([]);
})->with([
    'orchid' => ['2001:10::1', 'ORCHID 2001:10::/28'],
    'orchid-v2' => ['2001:20::1', 'ORCHIDv2 2001:20::/28'],
    'benchmarking' => ['2001:2::1', 'Benchmarking 2001:2::/48'],
    'discard-only' => ['100::1', 'Discard-only 100::/64'],
    'local-translation' => ['64:ff9b:1::1', 'Local-use translation 64:ff9b:1::/48'],
    'sixbone' => ['3ffe::1', '6bone 3ffe::/16'],
]);

it('allows public ipv6 addresses', function (string $ipv6, string $description) {
    Http::fake([
        '*' => Http::response('ok', 200, ['Content-Type' => 'text/plain']),
    ]);

    $result = redirectTesterWithDns([
        'ipv6-public.test' => [$ipv6],
    ])->test('http://ipv6-public.test')->toArray();

    expect($result['final_status'])->toBe(200)
        ->and($result['warnings'])->sequence(
            fn ($warning) => $warning->code->toBe('html_not_scanned'),
        );
})->with([
    'google-dns' => ['2001:4860:4860::8888', 'Google DNS'],
    'cloudflare-dns' => ['2606:4700:4700::1111', 'Cloudflare DNS'],
    'random-public' => ['2001:19f0:7001:1234::1', 'Random public IPv6'],
]);

it('caps response body writes before the buffer exceeds its byte limit', function () {
    $body = new CappedResponseBody(Utils::streamFor(fopen('php://temp', 'w+')), 16);

    expect($body->write(str_repeat('a', 10)))->toBe(10)
        ->and($body->write(str_repeat('b', 10)))->toBe(0)
        ->and($body->wasTruncated())->toBeTrue()
        ->and($body->getSize())->toBe(16)
        ->and((string) $body)->toBe(str_repeat('a', 10).str_repeat('b', 6));
});

it('brackets ipv6 addresses when pinning curl dns resolution', function () {
    if (! defined('CURLOPT_RESOLVE')) {
        $this->markTestSkipped('cURL is not available.');
    }

    $method = new ReflectionMethod(RedirectTester::class, 'curlOptions');
    $options = $method->invoke(redirectTesterWithDns(), 'https://example.com/path', '2001:4860:4860::8888');

    expect($options[CURLOPT_RESOLVE])->toBe(['example.com:443:[2001:4860:4860::8888]']);
});
