<?php

namespace App\Services\RedirectTesting\DTO;

final readonly class SecurityHeadersResult
{
    /**
     * @param  array<string, string>  $present
     * @param  list<string>  $missing
     */
    public function __construct(
        public bool $scanned,
        public array $present,
        public array $missing,
        public int $score,
    ) {}

    /**
     * @return array{scanned: bool, present: array<string, string>, missing: list<string>, score: int}
     */
    public function toArray(): array
    {
        return [
            'scanned' => $this->scanned,
            'present' => $this->present,
            'missing' => $this->missing,
            'score' => $this->score,
        ];
    }
}
