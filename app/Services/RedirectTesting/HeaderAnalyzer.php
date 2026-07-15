<?php

namespace App\Services\RedirectTesting;

use App\Services\RedirectTesting\DTO\HeaderAnalysisResult;
use App\Services\RedirectTesting\DTO\SecurityHeadersResult;

final class HeaderAnalyzer
{
    /**
     * @var array<string, array{name: string, recommended: string|null, explanation: string}>
     */
    private const HEADERS = [
        'content-security-policy' => ['name' => 'Content-Security-Policy', 'recommended' => "frame-ancestors 'self'; base-uri 'self'; object-src 'none';", 'explanation' => 'Controls which resources and embedding contexts the browser permits.'],
        'x-frame-options' => ['name' => 'X-Frame-Options', 'recommended' => 'SAMEORIGIN', 'explanation' => 'Helps prevent other sites from framing this page. CSP frame-ancestors is more flexible, and using both is acceptable for compatibility.'],
        'strict-transport-security' => ['name' => 'Strict-Transport-Security', 'recommended' => 'max-age=31536000; includeSubDomains', 'explanation' => 'Tells browsers to use HTTPS for future requests.'],
        'x-content-type-options' => ['name' => 'X-Content-Type-Options', 'recommended' => 'nosniff', 'explanation' => 'Prevents browsers from MIME-sniffing files as a different content type.'],
        'referrer-policy' => ['name' => 'Referrer-Policy', 'recommended' => 'strict-origin-when-cross-origin', 'explanation' => 'Controls how much referrer information is shared with other sites.'],
        'permissions-policy' => ['name' => 'Permissions-Policy', 'recommended' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()', 'explanation' => 'Blocks sensitive browser and device APIs that a basic content site usually does not need.'],
        'x-permitted-cross-domain-policies' => ['name' => 'X-Permitted-Cross-Domain-Policies', 'recommended' => 'none', 'explanation' => 'Disables legacy Adobe and Flash cross-domain policy files.'],
        'x-xss-protection' => ['name' => 'X-XSS-Protection', 'recommended' => '0', 'explanation' => 'This legacy browser filter is deprecated; modern sites should rely on CSP.'],
        'cache-control' => ['name' => 'Cache-Control', 'recommended' => null, 'explanation' => 'Controls browser and intermediary caching. The correct policy depends on the content.'],
        'content-type' => ['name' => 'Content-Type', 'recommended' => 'text/html; charset=UTF-8', 'explanation' => 'Declares the response media type and character encoding.'],
    ];

    /**
     * @param  array<string, list<string>>  $headers
     */
    public function analyze(array $headers, string $finalUrl): SecurityHeadersResult
    {
        $normalized = $this->normalize($headers);
        $analyses = [];
        $isHttps = parse_url($finalUrl, PHP_URL_SCHEME) === 'https';
        $csp = implode('; ', $normalized['content-security-policy'] ?? []);
        $hasFrameAncestors = str_contains(strtolower($csp), 'frame-ancestors');

        foreach (self::HEADERS as $key => $definition) {
            $values = $normalized[$key] ?? [];
            $analyses[] = $this->analyzeHeader($key, $definition, $values, $isHttps, $hasFrameAncestors, isset($normalized['x-frame-options']));
        }

        $counts = ['good' => 0, 'missing' => 0, 'warning' => 0, 'duplicate' => 0, 'info' => 0];

        foreach ($analyses as $analysis) {
            $counts[strtolower($analysis->status)]++;
        }

        return new SecurityHeadersResult(
            scanned: true,
            analyses: $analyses,
            rawHeaders: $normalized,
            counts: $counts,
            verdict: $this->verdict($analyses),
        );
    }

    /**
     * @param  array{name: string, recommended: string|null, explanation: string}  $definition
     * @param  list<string>  $values
     */
    private function analyzeHeader(string $key, array $definition, array $values, bool $isHttps, bool $hasFrameAncestors, bool $hasXFrameOptions): HeaderAnalysisResult
    {
        if (count($values) > 1) {
            $conflicting = count(array_unique(array_map(fn (string $value): string => strtolower(trim($value)), $values))) > 1;

            return new HeaderAnalysisResult(
                $definition['name'],
                'Duplicate',
                $values,
                $definition['recommended'],
                $conflicting ? 'Multiple headers were found with different values, which can produce unclear or inconsistent behavior.' : 'The same header was sent more than once. Some HTTP clients may combine duplicate values.',
                'Send this header once with one clear value.',
            );
        }

        if ($values === []) {
            if ($key === 'strict-transport-security' && ! $isHttps) {
                return new HeaderAnalysisResult($definition['name'], 'Warning', [], null, 'HSTS only works over HTTPS. This page should redirect to HTTPS before HSTS can be useful.', 'Redirect the page to HTTPS, then add HSTS on the HTTPS response.');
            }

            if ($key === 'x-xss-protection' || $key === 'cache-control') {
                return new HeaderAnalysisResult($definition['name'], 'Info', [], $definition['recommended'], $definition['explanation'], $key === 'x-xss-protection' ? 'No action is required. If you send this legacy header, use X-XSS-Protection: 0.' : 'Review whether an explicit cache policy is appropriate for this response.');
            }

            $explanation = $definition['explanation'];

            if ($key === 'content-security-policy' && ! $hasXFrameOptions) {
                $explanation .= ' Other sites may be able to iframe/embed this page because X-Frame-Options and CSP frame-ancestors are both missing.';
            }

            return new HeaderAnalysisResult($definition['name'], 'Missing', [], $definition['recommended'], $explanation, 'Send '.$definition['name'].': '.$definition['recommended']);
        }

        $value = $values[0];
        $lowerValue = strtolower($value);

        if ($key === 'content-security-policy' && ! $hasFrameAncestors) {
            return new HeaderAnalysisResult($definition['name'], 'Warning', $values, $definition['recommended'], 'A CSP is present, but it does not define frame-ancestors. CSP is not controlling which sites can embed this page. Stricter policies should be tested with Content-Security-Policy-Report-Only before enforcement.', 'Add a frame-ancestors directive. The recommended starter policy is conservative and unlikely to break common third-party scripts.');
        }

        if ($key === 'x-xss-protection' && $lowerValue !== '0') {
            return new HeaderAnalysisResult($definition['name'], 'Warning', $values, '0', 'X-XSS-Protection is legacy/deprecated. Modern sites usually disable it and rely on CSP instead.', 'Send X-XSS-Protection: 0.');
        }

        if ($key === 'strict-transport-security') {
            if (! $isHttps) {
                return new HeaderAnalysisResult($definition['name'], 'Warning', $values, null, 'HSTS is ignored over HTTP.', 'Redirect this page to HTTPS and send HSTS only over HTTPS.');
            }

            if (! str_contains($lowerValue, 'max-age=')) {
                return new HeaderAnalysisResult($definition['name'], 'Warning', $values, $definition['recommended'], 'HSTS is present but does not include a valid max-age directive.', 'Set a max-age. Only use includeSubDomains if every subdomain supports HTTPS; preload is an advanced opt-in and is not recommended by default.');
            }

            return new HeaderAnalysisResult($definition['name'], 'Good', $values, $definition['recommended'], $definition['explanation'].' Only use includeSubDomains if every subdomain supports HTTPS. Preload is an advanced option.', 'No change required; verify all subdomains support HTTPS before adding includeSubDomains.');
        }

        $matchesRecommendation = match ($key) {
            'x-content-type-options' => $lowerValue === 'nosniff',
            'referrer-policy' => $lowerValue === 'strict-origin-when-cross-origin',
            'x-frame-options' => in_array($lowerValue, ['sameorigin', 'deny'], true),
            'x-permitted-cross-domain-policies' => $lowerValue === 'none',
            'x-xss-protection' => $lowerValue === '0',
            default => true,
        };

        return new HeaderAnalysisResult(
            $definition['name'],
            $matchesRecommendation ? 'Good' : 'Warning',
            $values,
            $definition['recommended'],
            $matchesRecommendation ? $definition['explanation'] : 'The header is present, but its value differs from the practical baseline for a basic content site.',
            $matchesRecommendation ? 'No change required.' : 'Consider sending '.$definition['name'].': '.$definition['recommended'],
        );
    }

    /**
     * @param  array<string, list<string>>  $headers
     * @return array<string, list<string>>
     */
    private function normalize(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $values) {
            $key = strtolower($name);
            $normalized[$key] = [...($normalized[$key] ?? []), ...array_values($values)];
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param  list<HeaderAnalysisResult>  $analyses
     */
    private function verdict(array $analyses): string
    {
        $problems = array_values(array_filter($analyses, fn (HeaderAnalysisResult $analysis): bool => in_array($analysis->status, ['Missing', 'Warning', 'Duplicate'], true)));

        if ($problems === []) {
            return 'This page has a solid basic header setup.';
        }

        $names = array_map(fn (HeaderAnalysisResult $analysis): string => $analysis->header, array_slice($problems, 0, 3));

        return 'This page needs attention for '.implode(', ', $names).(count($problems) > 3 ? ' and '.(count($problems) - 3).' other headers.' : '.');
    }
}
