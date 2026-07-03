<?php

namespace App\Services\RedirectTesting\DTO;

final readonly class RedirectHop
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public int $index,
        public string $url,
        public string $method,
        public ?int $status,
        public ?string $reason,
        public ?string $ipAddress,
        public int $durationMs,
        public ?string $contentType,
        public ?string $redirectType,
        public ?string $redirectTo,
        public array $headers,
        public bool $bodyInspected,
    ) {}

    /**
     * @return array{index: int, url: string, method: string, status: int|null, reason: string|null, ip_address: string|null, duration_ms: int, content_type: string|null, redirect_type: string|null, redirect_to: string|null, headers: array<string, string>, body_inspected: bool}
     */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'url' => $this->url,
            'method' => $this->method,
            'status' => $this->status,
            'reason' => $this->reason,
            'ip_address' => $this->ipAddress,
            'duration_ms' => $this->durationMs,
            'content_type' => $this->contentType,
            'redirect_type' => $this->redirectType,
            'redirect_to' => $this->redirectTo,
            'headers' => $this->headers,
            'body_inspected' => $this->bodyInspected,
        ];
    }
}
