<?php

namespace LeNats\Contracts;

interface BufferInterface
{
    public function get(?int $length = null, ?int $start = null): string;

    public function isEmpty(): bool;

    public function append(string $data): void;

    public function resetPosition(): void;

    public function getLine(): ?string;

    public function acknowledge(string $line): void;

    public function clear(): void;
}
