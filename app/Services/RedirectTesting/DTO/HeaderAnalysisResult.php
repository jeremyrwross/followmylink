<?php

namespace App\Services\RedirectTesting\DTO;

final readonly class HeaderAnalysisResult
{
    /**
     * @param  list<string>  $foundValues
     */
    public function __construct(
        public string $header,
        public string $status,
        public array $foundValues,
        public ?string $recommendedValue,
        public string $explanation,
        public string $suggestedFix,
    ) {}

    /**
     * @return array{header: string, status: string, found_values: list<string>, recommended_value: string|null, explanation: string, suggested_fix: string}
     */
    public function toArray(): array
    {
        return [
            'header' => $this->header,
            'status' => $this->status,
            'found_values' => $this->foundValues,
            'recommended_value' => $this->recommendedValue,
            'explanation' => $this->explanation,
            'suggested_fix' => $this->suggestedFix,
        ];
    }
}
