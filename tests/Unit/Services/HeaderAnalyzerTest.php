<?php

use App\Services\RedirectTesting\HeaderAnalyzer;

function analyzedHeader(array $headers, string $name, string $url = 'https://example.com'): array
{
    $analysis = (new HeaderAnalyzer)->analyze($headers, $url)->toArray();

    return collect($analysis['analyses'])->firstWhere('header', $name);
}

it('marks recommended headers as good case insensitively', function () {
    $headers = [
        'CONTENT-SECURITY-POLICY' => ["frame-ancestors 'self'; base-uri 'self'; object-src 'none';"],
        'X-Frame-Options' => ['SAMEORIGIN'],
        'Strict-Transport-Security' => ['max-age=31536000; includeSubDomains'],
        'X-Content-Type-Options' => ['nosniff'],
        'Referrer-Policy' => ['strict-origin-when-cross-origin'],
        'Permissions-Policy' => ['camera=(), microphone=(), geolocation=(), payment=(), usb=()'],
        'X-Permitted-Cross-Domain-Policies' => ['none'],
        'X-XSS-Protection' => ['0'],
        'Cache-Control' => ['public, max-age=60'],
        'Content-Type' => ['text/html; charset=UTF-8'],
    ];

    $result = (new HeaderAnalyzer)->analyze($headers, 'https://example.com')->toArray();

    expect($result['checked'])->toBe(10)
        ->and($result['counts']['good'])->toBe(10)
        ->and($result['raw_headers'])->toHaveKey('content-security-policy');
});

it('reports missing headers and treats a missing legacy xss header as info', function () {
    $result = (new HeaderAnalyzer)->analyze([], 'https://example.com')->toArray();

    expect($result['counts']['missing'])->toBeGreaterThan(0)
        ->and(analyzedHeader([], 'Content-Security-Policy')['status'])->toBe('Missing')
        ->and(analyzedHeader([], 'X-XSS-Protection')['status'])->toBe('Info');
});

it('detects identical and conflicting duplicate values', function () {
    expect(analyzedHeader(['Referrer-Policy' => ['same-origin', 'same-origin']], 'Referrer-Policy')['status'])->toBe('Duplicate')
        ->and(analyzedHeader(['Referrer-Policy' => ['same-origin', 'strict-origin-when-cross-origin']], 'Referrer-Policy')['explanation'])->toContain('different values');
});

it('handles hsts differently for http and https pages', function () {
    expect(analyzedHeader([], 'Strict-Transport-Security', 'https://example.com')['status'])->toBe('Missing')
        ->and(analyzedHeader([], 'Strict-Transport-Security', 'http://example.com')['status'])->toBe('Warning')
        ->and(analyzedHeader([], 'Strict-Transport-Security', 'http://example.com')['recommended_value'])->toBeNull();
});

it('warns when the hsts max age is invalid or shorter than one year', function (string $value) {
    $result = analyzedHeader(['Strict-Transport-Security' => [$value]], 'Strict-Transport-Security');

    expect($result['status'])->toBe('Warning')
        ->and($result['explanation'])->toContain('at least 31536000 seconds');
})->with([
    'disabled' => 'max-age=0',
    'one second' => 'max-age=1',
    'just under one year' => 'max-age=31535999',
    'non-numeric' => 'max-age=forever',
    'invalid directive name' => 'not-max-age=31536000',
    'trailing junk' => 'max-age=31536000seconds',
    'duplicate directive' => 'max-age=31536000; max-age=63072000',
]);

it('accepts valid hsts max ages of at least one year', function (string $value) {
    expect(analyzedHeader(['Strict-Transport-Security' => [$value]], 'Strict-Transport-Security')['status'])->toBe('Good');
})->with([
    'one year' => 'max-age=31536000',
    'two years' => 'max-age=63072000; includeSubDomains',
    'quoted and case insensitive' => 'MAX-AGE="31536000"',
    'optional whitespace' => 'max-age = 31536000',
    'arbitrarily large' => 'max-age=999999999999999999999999999999999999',
]);

it('warns when csp is missing frame ancestors or framing protection is absent', function () {
    $csp = analyzedHeader(['Content-Security-Policy' => ["default-src 'self'"]], 'Content-Security-Policy');
    $missing = analyzedHeader([], 'Content-Security-Policy');

    expect($csp['status'])->toBe('Warning')
        ->and($csp['explanation'])->toContain('does not define frame-ancestors')
        ->and($missing['explanation'])->toContain('iframe/embed');
});

it('warns about the deprecated enabled xss protection mode', function () {
    $result = analyzedHeader(['X-XSS-Protection' => ['1; mode=block']], 'X-XSS-Protection');

    expect($result['status'])->toBe('Warning')
        ->and($result['recommended_value'])->toBe('0');
});

it('reports a missing permissions policy', function () {
    expect(analyzedHeader([], 'Permissions-Policy')['status'])->toBe('Missing');
});
