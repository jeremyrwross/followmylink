<?php

namespace App\Services;

use App\Services\RedirectTesting\CappedResponseBody;
use App\Services\RedirectTesting\DnsResolver;
use App\Services\RedirectTesting\DTO\CanonicalResult;
use App\Services\RedirectTesting\DTO\RedirectHop;
use App\Services\RedirectTesting\DTO\RedirectTestResult;
use App\Services\RedirectTesting\DTO\RedirectWarning;
use App\Services\RedirectTesting\DTO\SecurityHeadersResult;
use App\Services\RedirectTesting\UrlSafetyException;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class RedirectTester
{
    public const MAX_HOPS = 10;

    private const TOTAL_BUDGET_SECONDS = 30;

    private const BODY_LIMIT_BYTES = 524288;

    /**
     * @var array<string, array{label: string, value: string}>
     */
    private const USER_AGENTS = [
        'desktop_chrome' => [
            'label' => 'Desktop Chrome',
            'value' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36',
        ],
        'mobile_safari' => [
            'label' => 'Mobile Safari',
            'value' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1',
        ],
        'googlebot' => [
            'label' => 'Googlebot',
            'value' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        ],
        'bingbot' => [
            'label' => 'Bingbot',
            'value' => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
        ],
        'facebook' => [
            'label' => 'Facebook crawler',
            'value' => 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
        ],
        'slack' => [
            'label' => 'Slack unfurl bot',
            'value' => 'Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)',
        ],
    ];

    public function __construct(
        private readonly DnsResolver $dnsResolver,
    ) {}

    /**
     * @return array<string, array{label: string, value: string}>
     */
    public static function userAgents(): array
    {
        return self::USER_AGENTS;
    }

    public function test(string $url, string $userAgentKey = 'desktop_chrome'): RedirectTestResult
    {
        $startedAt = microtime(true);
        $warnings = [];
        $chain = [];
        $visited = [];

        try {
            $currentUrl = $this->normalizeUrl($url);
        } catch (UrlSafetyException $exception) {
            $userAgent = $this->userAgent($userAgentKey);

            return new RedirectTestResult(
                requestedUrl: $url,
                normalizedUrl: trim($url),
                finalUrl: null,
                finalStatus: null,
                finalContentType: null,
                redirectCount: 0,
                durationMs: $this->elapsedMs($startedAt),
                httpsUpgrade: false,
                userAgent: $userAgent,
                chain: [],
                canonical: new CanonicalResult(false, null, null, 'No response was available to scan.'),
                securityHeaders: new SecurityHeadersResult(false, [], [], 0),
                warnings: [new RedirectWarning($exception->codeName, 'error', $exception->getMessage(), trim($url))],
                generatedAt: Carbon::now(),
            );
        }

        $initialUrl = $currentUrl;
        $userAgent = $this->userAgent($userAgentKey);
        $finalResponse = null;
        $finalBody = '';

        for ($index = 0; $index < self::MAX_HOPS; $index++) {
            if ((microtime(true) - $startedAt) >= self::TOTAL_BUDGET_SECONDS) {
                $warnings[] = new RedirectWarning('time_budget_exceeded', 'error', 'The redirect chain exceeded the 30 second budget.', $currentUrl);
                break;
            }

            $comparableUrl = $this->comparableUrl($currentUrl);

            if (in_array($comparableUrl, $visited, true)) {
                $warnings[] = new RedirectWarning('redirect_loop', 'error', 'A redirect loop was detected.', $currentUrl);
                break;
            }

            $visited[] = $comparableUrl;

            try {
                $resolved = $this->validateAndResolveUrl($currentUrl);
            } catch (UrlSafetyException $exception) {
                $warnings[] = new RedirectWarning($exception->codeName, 'error', $exception->getMessage(), $currentUrl);
                break;
            }

            $requestStartedAt = microtime(true);

            $responseBody = new CappedResponseBody(Utils::streamFor(fopen('php://temp', 'w+')), self::BODY_LIMIT_BYTES);

            try {
                $response = Http::withHeaders([
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'User-Agent' => $userAgent['value'],
                ])
                    ->connectTimeout(5)
                    ->timeout(10)
                    ->withOptions([
                        'allow_redirects' => false,
                        'curl' => $this->curlOptions($currentUrl, $resolved['ip']),
                        'sink' => $responseBody,
                    ])
                    ->get($currentUrl);
            } catch (ConnectionException $exception) {
                $chain[] = new RedirectHop($index, $currentUrl, 'GET', null, $exception->getMessage(), $resolved['ip'], $this->elapsedMs($requestStartedAt), null, null, null, [], false);
                $warnings[] = new RedirectWarning('request_failed', 'error', 'The request failed before a response was received.', $currentUrl);
                break;
            }

            $headers = $this->headers($response);
            $contentType = $this->headerValue($headers, 'content-type');
            $body = $this->limitedBody($response);

            if ($responseBody->wasTruncated()) {
                $warnings[] = new RedirectWarning('body_truncated', 'info', 'The response body exceeded the 512 KiB inspection limit and was truncated.', $currentUrl);
            }
            $redirectType = null;
            $redirectTo = null;

            if ($this->isServerRedirect($response)) {
                $redirectType = 'server';
                $location = $this->headerValue($headers, 'location');

                if ($location === null || trim($location) === '') {
                    $warnings[] = new RedirectWarning('missing_redirect_target', 'error', 'The response was a redirect but did not include a Location header.', $currentUrl);
                } else {
                    $redirectTo = $this->resolveUrl($currentUrl, $location);
                }
            } elseif ($this->isHtml($contentType)) {
                $clientRedirect = $this->detectClientRedirect($body, $currentUrl);

                if ($clientRedirect !== null) {
                    $redirectType = $clientRedirect['type'];
                    $redirectTo = $clientRedirect['url'];
                    $warnings[] = new RedirectWarning($clientRedirect['type'].'_redirect', 'info', $clientRedirect['message'], $currentUrl);
                }
            }

            $chain[] = new RedirectHop($index, $currentUrl, 'GET', $response->status(), $response->reason(), $resolved['ip'], $this->elapsedMs($requestStartedAt), $contentType, $redirectType, $redirectTo, $headers, $this->isHtml($contentType));
            $finalResponse = $response;
            $finalBody = $body;

            if ($redirectTo === null) {
                break;
            }

            try {
                $currentUrl = $this->normalizeUrl($redirectTo);
                $this->validateAndResolveUrl($currentUrl);
            } catch (UrlSafetyException $exception) {
                $warnings[] = new RedirectWarning($exception->codeName, 'error', $exception->getMessage(), $redirectTo);
                break;
            }
        }

        $lastHop = $chain[array_key_last($chain)] ?? null;

        if (count($chain) >= self::MAX_HOPS && $lastHop?->redirectTo !== null) {
            $warnings[] = new RedirectWarning('too_many_redirects', 'error', 'The redirect chain exceeded the 10 hop limit.', $lastHop->redirectTo);
        }

        $finalHop = $chain[array_key_last($chain)] ?? null;
        $finalUrl = $finalHop?->url;
        $finalContentType = $finalHop?->contentType;

        if ($finalResponse !== null && ! $this->isHtml($finalContentType)) {
            $warnings[] = new RedirectWarning('html_not_scanned', 'info', 'The final response was not HTML, so canonical and client-side redirect checks were skipped.', $finalUrl);
        }

        return new RedirectTestResult(
            requestedUrl: $url,
            normalizedUrl: $initialUrl,
            finalUrl: $finalUrl,
            finalStatus: $finalHop?->status,
            finalContentType: $finalContentType,
            redirectCount: max(0, count($chain) - 1),
            durationMs: $this->elapsedMs($startedAt),
            httpsUpgrade: $this->isHttpsUpgrade($initialUrl, $finalUrl),
            userAgent: $userAgent,
            chain: $chain,
            canonical: $this->canonicalResult($finalBody, $finalUrl, $finalContentType),
            securityHeaders: $this->securityHeadersResult($finalResponse),
            warnings: $warnings,
            generatedAt: Carbon::now(),
        );
    }

    public function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            throw new UrlSafetyException('empty_url', 'Enter a URL to test.');
        }

        if (! Str::contains($url, '://')) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new UrlSafetyException('invalid_url', 'The URL could not be parsed.');
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new UrlSafetyException('unsupported_scheme', 'Only HTTP and HTTPS URLs are supported.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new UrlSafetyException('embedded_credentials', 'URLs with embedded usernames or passwords are not allowed.');
        }

        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $scheme.'://'.$host.$port.$path.$query;
    }

    public function resolveUrl(string $baseUrl, string $target): string
    {
        $target = trim($target);

        if (Str::startsWith($target, ['http://', 'https://'])) {
            return $target;
        }

        $base = parse_url($baseUrl);

        if ($base === false || ! isset($base['scheme'], $base['host'])) {
            throw new UrlSafetyException('invalid_url', 'The base URL could not be parsed.');
        }

        if (Str::startsWith($target, '//')) {
            return $base['scheme'].':'.$target;
        }

        $origin = $base['scheme'].'://'.$base['host'].(isset($base['port']) ? ':'.$base['port'] : '');

        if (Str::startsWith($target, '/')) {
            return $origin.$target;
        }

        $directory = preg_replace('#/[^/]*$#', '/', $base['path'] ?? '/');

        return $this->removeDotSegments($origin.$directory.$target);
    }

    public function comparableUrl(string $url): string
    {
        $parts = parse_url($this->normalizeUrl($url));

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return strtolower($url);
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $port = $parts['port'] ?? null;
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $portText = ($port !== null && ! (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))) ? ':'.$port : '';

        return $scheme.'://'.$host.$portText.$path.$query;
    }

    /**
     * @return array{url: string, matches_final_url: bool|null}
     */
    public function extractCanonical(string $html, ?string $finalUrl): array
    {
        if (! preg_match('/<link\b[^>]*rel=["\']?canonical["\']?[^>]*>/i', $html, $tagMatch)) {
            return ['url' => '', 'matches_final_url' => null];
        }

        if (! preg_match('/href=["\']([^"\']+)["\']/i', $tagMatch[0], $hrefMatch)) {
            return ['url' => '', 'matches_final_url' => null];
        }

        $canonicalUrl = html_entity_decode($hrefMatch[1], ENT_QUOTES | ENT_HTML5);

        if ($finalUrl !== null && ! Str::startsWith($canonicalUrl, ['http://', 'https://'])) {
            $canonicalUrl = $this->resolveUrl($finalUrl, $canonicalUrl);
        }

        return [
            'url' => $canonicalUrl,
            'matches_final_url' => $finalUrl === null ? null : $this->comparableUrl($canonicalUrl) === $this->comparableUrl($finalUrl),
        ];
    }

    /**
     * @return array{host: string, ip: string}
     */
    private function validateAndResolveUrl(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new UrlSafetyException('invalid_url', 'The URL could not be parsed.');
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new UrlSafetyException('unsupported_scheme', 'Only HTTP and HTTPS URLs are supported.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new UrlSafetyException('embedded_credentials', 'URLs with embedded usernames or passwords are not allowed.');
        }

        $port = $parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80);

        if (! in_array($port, [80, 443, 8080, 8443], true)) {
            throw new UrlSafetyException('blocked_port', 'Only ports 80, 443, 8080, and 8443 are allowed.');
        }

        $addresses = filter_var($parts['host'], FILTER_VALIDATE_IP) ? [$parts['host']] : $this->dnsResolver->resolve($parts['host']);

        if ($addresses === []) {
            throw new UrlSafetyException('dns_failed', 'The hostname did not resolve to an IP address.');
        }

        foreach ($addresses as $address) {
            if ($this->isPublicIp($address)) {
                return ['host' => $parts['host'], 'ip' => $address];
            }
        }

        throw new UrlSafetyException('blocked_ip', 'The hostname resolves only to private, reserved, local, or metadata IP addresses.');
    }

    /**
     * @return array{key: string, label: string, value: string}
     */
    private function userAgent(string $key): array
    {
        $preset = self::USER_AGENTS[$key] ?? self::USER_AGENTS['desktop_chrome'];

        return [
            'key' => array_key_exists($key, self::USER_AGENTS) ? $key : 'desktop_chrome',
            'label' => $preset['label'],
            'value' => $preset['value'],
        ];
    }

    /**
     * @return array<int, list<string>>
     */
    private function curlOptions(string $url, string $ip): array
    {
        if (! defined('CURLOPT_RESOLVE')) {
            return [];
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'], $parts['scheme'])) {
            return [];
        }

        $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);

        return [
            CURLOPT_RESOLVE => [$parts['host'].':'.$port.':'.(str_contains($ip, ':') ? '['.$ip.']' : $ip)],
        ];
    }

    private function isPublicIp(string $address): bool
    {
        if (! filter_var($address, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($address);

            if ($long === false) {
                return false;
            }

            $ranges = [
                ['0.0.0.0', 8],
                ['10.0.0.0', 8],
                ['100.64.0.0', 10],
                ['127.0.0.0', 8],
                ['169.254.0.0', 16],
                ['172.16.0.0', 12],
                ['192.0.0.0', 24],
                ['192.0.2.0', 24],
                ['192.168.0.0', 16],
                ['198.18.0.0', 15],
                ['198.51.100.0', 24],
                ['203.0.113.0', 24],
                ['224.0.0.0', 4],
                ['240.0.0.0', 4],
            ];

            foreach ($ranges as [$network, $bits]) {
                $mask = -1 << (32 - $bits);

                if (($long & $mask) === (ip2long($network) & $mask)) {
                    return false;
                }
            }

            return true;
        }

        $normalized = strtolower($address);

        $ipv6PrivateRanges = [
            '::/96',
            '::ffff:0:0/96',
            '64:ff9b::/96',
            '64:ff9b:1::/48',
            '100::/64',
            '2001:2::/48',
            '2001:10::/28',
            '2001:20::/28',
            '2001:db8::/32',
            '3ffe::/16',
            'fc00::/7',
            'fe80::/10',
            'ff00::/8',
        ];

        foreach ($ipv6PrivateRanges as $cidr) {
            if ($this->ipv6InRange($normalized, $cidr)) {
                return false;
            }
        }

        return true;
    }

    private function ipv6InRange(string $ip, string $cidr): bool
    {
        [$network, $bits] = explode('/', $cidr);
        $ipBin = inet_pton($ip);
        $netBin = inet_pton($network);

        if ($ipBin === false || $netBin === false) {
            return false;
        }

        $maskBytes = [];
        for ($i = 0; $i < 16; $i++) {
            if ($bits >= 8) {
                $maskBytes[] = 255;
                $bits -= 8;
            } elseif ($bits > 0) {
                $maskBytes[] = 255 << (8 - $bits);
                $bits = 0;
            } else {
                $maskBytes[] = 0;
            }
        }

        for ($i = 0; $i < 16; $i++) {
            $ipByte = ord($ipBin[$i]);
            $netByte = ord($netBin[$i]);
            $maskByte = $maskBytes[$i];

            if (($ipByte & $maskByte) !== ($netByte & $maskByte)) {
                return false;
            }
        }

        return true;
    }

    private function isServerRedirect(Response $response): bool
    {
        return $response->status() >= 300 && $response->status() < 400;
    }

    private function isHtml(?string $contentType): bool
    {
        return $contentType !== null && Str::contains(strtolower($contentType), ['text/html', 'application/xhtml+xml']);
    }

    private function limitedBody(Response $response): string
    {
        return Str::limit($response->body(), self::BODY_LIMIT_BYTES, '');
    }

    /**
     * @return array<string, string>
     */
    private function headers(Response $response): array
    {
        $headers = [];

        foreach ($response->headers() as $name => $values) {
            $headers[strtolower($name)] = implode(', ', $values);
        }

        return $headers;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function headerValue(array $headers, string $name): ?string
    {
        return $headers[strtolower($name)] ?? null;
    }

    /**
     * @return array{type: string, url: string, message: string}|null
     */
    private function detectClientRedirect(string $html, string $baseUrl): ?array
    {
        if (preg_match('/<meta\b[^>]*http-equiv=["\']?refresh["\']?[^>]*content=["\'][^"\']*url=([^"\']+)["\'][^>]*>/i', $html, $match)) {
            return [
                'type' => 'meta_refresh',
                'url' => $this->resolveUrl($baseUrl, html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5)),
                'message' => 'A meta refresh redirect was found in the HTML.',
            ];
        }

        if (preg_match('/(?:window|document)\.location(?:\.href)?\s*=\s*["\']([^"\']+)["\']/i', $html, $match)) {
            return [
                'type' => 'javascript',
                'url' => $this->resolveUrl($baseUrl, html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5)),
                'message' => 'A basic JavaScript redirect pattern was found without executing JavaScript.',
            ];
        }

        return null;
    }

    private function canonicalResult(string $body, ?string $finalUrl, ?string $contentType): CanonicalResult
    {
        if (! $this->isHtml($contentType)) {
            return new CanonicalResult(false, null, null, 'Final response was not HTML.');
        }

        $canonical = $this->extractCanonical($body, $finalUrl);

        if ($canonical['url'] === '') {
            return new CanonicalResult(true, null, null, 'No canonical tag was found.');
        }

        return new CanonicalResult(
            true,
            $canonical['url'],
            $canonical['matches_final_url'],
            $canonical['matches_final_url'] === true ? 'Canonical matches the final URL.' : 'Canonical does not match the final URL.',
        );
    }

    private function securityHeadersResult(?Response $response): SecurityHeadersResult
    {
        if ($response === null) {
            return new SecurityHeadersResult(false, [], [], 0);
        }

        $headers = $this->headers($response);
        $expected = [
            'strict-transport-security',
            'content-security-policy',
            'x-content-type-options',
            'referrer-policy',
            'permissions-policy',
        ];
        $present = [];
        $missing = [];

        foreach ($expected as $header) {
            if (isset($headers[$header])) {
                $present[$header] = $headers[$header];
            } else {
                $missing[] = $header;
            }
        }

        return new SecurityHeadersResult(true, $present, $missing, count($present));
    }

    private function isHttpsUpgrade(string $initialUrl, ?string $finalUrl): bool
    {
        if ($finalUrl === null) {
            return false;
        }

        $initial = parse_url($initialUrl);
        $final = parse_url($finalUrl);

        return ($initial['scheme'] ?? null) === 'http'
            && ($final['scheme'] ?? null) === 'https'
            && strtolower($initial['host'] ?? '') === strtolower($final['host'] ?? '');
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function removeDotSegments(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $segments = [];

        foreach (explode('/', $parts['path'] ?? '/') as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').'/'.implode('/', $segments).(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
