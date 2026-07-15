<?php

namespace App\Services\RedirectTesting\DTO;

final readonly class SecurityHeadersResult
{
    /**
     * @param  list<HeaderAnalysisResult>  $analyses
     * @param  array<string, list<string>>  $rawHeaders
     * @param  array{good: int, missing: int, warning: int, duplicate: int, info: int}  $counts
     */
    public function __construct(
        public bool $scanned,
        public array $analyses,
        public array $rawHeaders,
        public array $counts,
        public string $verdict,
    ) {}

    /**
     * @return array{scanned: bool, checked: int, counts: array{good: int, missing: int, warning: int, duplicate: int, info: int}, verdict: string, analyses: list<array<string, mixed>>, raw_headers: array<string, list<string>>}
     */
    public function toArray(): array
    {
        return [
            'scanned' => $this->scanned,
            'checked' => count($this->analyses),
            'counts' => $this->counts,
            'verdict' => $this->verdict,
            'analyses' => array_map(fn (HeaderAnalysisResult $analysis): array => $analysis->toArray(), $this->analyses),
            'raw_headers' => $this->rawHeaders,
        ];
    }
}
