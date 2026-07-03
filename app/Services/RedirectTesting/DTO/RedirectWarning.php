<?php

namespace App\Services\RedirectTesting\DTO;

final readonly class RedirectWarning
{
    public function __construct(
        public string $code,
        public string $severity,
        public string $message,
        public ?string $url = null,
    ) {}

    /**
     * @return array{code: string, severity: string, message: string, url: string|null}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'severity' => $this->severity,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }
}
