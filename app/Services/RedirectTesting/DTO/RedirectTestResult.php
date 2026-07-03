<?php

namespace App\Services\RedirectTesting\DTO;

use Carbon\CarbonInterface;

final readonly class RedirectTestResult
{
    /**
     * @param  array{key: string, label: string, value: string}  $userAgent
     * @param  list<RedirectHop>  $chain
     * @param  list<RedirectWarning>  $warnings
     */
    public function __construct(
        public string $requestedUrl,
        public string $normalizedUrl,
        public ?string $finalUrl,
        public ?int $finalStatus,
        public ?string $finalContentType,
        public int $redirectCount,
        public int $durationMs,
        public bool $httpsUpgrade,
        public array $userAgent,
        public array $chain,
        public CanonicalResult $canonical,
        public SecurityHeadersResult $securityHeaders,
        public array $warnings,
        public CarbonInterface $generatedAt,
    ) {}

    /**
     * @return array{
     *     requested_url: string,
     *     normalized_url: string,
     *     final_url: string|null,
     *     final_status: int|null,
     *     final_content_type: string|null,
     *     redirect_count: int,
     *     duration_ms: int,
     *     https_upgrade: bool,
     *     user_agent: array{key: string, label: string, value: string},
     *     chain: list<array<string, mixed>>,
     *     canonical: array<string, mixed>,
     *     security_headers: array<string, mixed>,
     *     warnings: list<array<string, mixed>>,
     *     generated_at: string
     * }
     */
    public function toArray(): array
    {
        return [
            'requested_url' => $this->requestedUrl,
            'normalized_url' => $this->normalizedUrl,
            'final_url' => $this->finalUrl,
            'final_status' => $this->finalStatus,
            'final_content_type' => $this->finalContentType,
            'redirect_count' => $this->redirectCount,
            'duration_ms' => $this->durationMs,
            'https_upgrade' => $this->httpsUpgrade,
            'user_agent' => $this->userAgent,
            'chain' => array_map(fn (RedirectHop $hop): array => $hop->toArray(), $this->chain),
            'canonical' => $this->canonical->toArray(),
            'security_headers' => $this->securityHeaders->toArray(),
            'warnings' => array_map(fn (RedirectWarning $warning): array => $warning->toArray(), $this->warnings),
            'generated_at' => $this->generatedAt->toIso8601String(),
        ];
    }
}
