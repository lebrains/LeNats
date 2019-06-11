<?php

namespace LeNats\Services;

use LeNats\Contracts\BufferInterface;

class StringBuffer implements BufferInterface
{
    /** @var string */
    private static $buffer = '';

    /** @var string */
    private $eol;

    public function __construct(string $eol = "\r\n")
    {
        $this->eol = $eol;
    }

    public function get(?int $length = null, ?int $start = null): ?string
    {
        $start = $start ?? 0;
        $result = self::$buffer;

        if ($length) {
            $result = substr(self::$buffer, $start, $length);

            if (strlen($result) !== $length) {
                return null;
            }
        }

        return $result;
    }

    public function isEmpty(): bool
    {
        return self::$buffer === '';
    }

    public function append(string $data): void
    {
        self::$buffer .= $data;
    }

    public function getLine(): ?string
    {
        $endPosition = strpos(self::$buffer, $this->eol);

        if ($endPosition === false) {
            return null;
        }

        return $this->get($endPosition);
    }

    public function acknowledge(string $line): void
    {
        self::$buffer = ltrim(substr(self::$buffer, strlen($line)), $this->eol);
    }

    public function clear(): void
    {
        self::$buffer = '';
    }
}
