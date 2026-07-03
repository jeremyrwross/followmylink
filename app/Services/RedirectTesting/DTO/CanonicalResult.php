<?php

namespace App\Services\RedirectTesting\DTO;

final readonly class CanonicalResult
{
    public function __construct(
        public bool $scanned,
        public ?string $url,
        public ?bool $matchesFinalUrl,
        public string $message,
    ) {}

    /**
     * @return array{scanned: bool, url: string|null, matches_final_url: bool|null, message: string}
     */
    public function toArray(): array
    {
        return [
            'scanned' => $this->scanned,
            'url' => $this->url,
            'matches_final_url' => $this->matchesFinalUrl,
            'message' => $this->message,
        ];
    }
}
