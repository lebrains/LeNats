<?php

namespace LeNats\Services;

use LeNats\Contracts\BufferInterface;

class StringBuffer implements BufferInterface
{
    /** @var string */
    private $buffer = '';

    /** @var string */
    private $eol;

    /** @var int */
    private $position = 0;

    public function __construct(string $eol = "\r\n")
    {
        $this->eol = $eol;
    }

    public function get(?int $length = null, ?int $start = null): string
    {
        $start = $start ?? 0;

        return $length ? mb_substr($this->buffer, $start, $length) : $this->buffer;
    }

    public function isEmpty(): bool
    {
        return $this->buffer === '';
    }

    public function append(string $data): void
    {
        $this->buffer .= $data;
        $this->resetPosition();
    }

    public function resetPosition(): void
    {
        $this->position = 0;
    }

    public function getLine(): ?string
    {
        if (!$this->position) {
            $this->position = 0;

            $line = strtok($this->buffer, $this->eol);
        } else {
            $line = strtok($this->eol);
        }

        ++$this->position;

        if (!$line) {
            $this->resetPosition();
            $line = null;
        }

        return $line;
    }

    public function acknowledge(string $line): void
    {
        $this->buffer = ltrim(mb_substr($this->buffer, mb_strlen($line)), $this->eol);
    }

    public function clear(): void
    {
        $this->buffer = '';
        $this->resetPosition();
    }
}
