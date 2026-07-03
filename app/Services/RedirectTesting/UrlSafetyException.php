<?php

namespace App\Services\RedirectTesting;

use RuntimeException;

final class UrlSafetyException extends RuntimeException
{
    public function __construct(
        public readonly string $codeName,
        string $message,
    ) {
        parent::__construct($message);
    }
}
