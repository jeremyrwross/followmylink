<?php

namespace App\Services\RedirectTesting;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;

final class CappedResponseBody implements StreamInterface
{
    use StreamDecoratorTrait;

    private bool $truncated = false;

    public function __construct(StreamInterface $stream, private readonly int $limit)
    {
        $this->stream = $stream;
    }

    public function write($string): int
    {
        $remaining = max(0, $this->limit - $this->stream->tell());

        if (strlen($string) <= $remaining) {
            return $this->stream->write($string);
        }

        if ($remaining > 0) {
            $this->stream->write(substr($string, 0, $remaining));
        }

        $this->truncated = true;

        return 0;
    }

    public function wasTruncated(): bool
    {
        return $this->truncated;
    }
}
