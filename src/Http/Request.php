<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    /** @param array<string,mixed> $query */
    public function __construct(private readonly array $query)
    {
    }

    public static function fromGlobals(): self
    {
        return new self($_GET);
    }

    public function string(string $key, ?string $default = null): ?string
    {
        if (!isset($this->query[$key])) {
            return $default;
        }

        $value = trim((string) $this->query[$key]);
        return $value === '' ? $default : $value;
    }

    public function int(string $key, int $default = 0): int
    {
        if (!isset($this->query[$key]) || !is_numeric($this->query[$key])) {
            return $default;
        }

        return (int) $this->query[$key];
    }

    public function date(string $key): ?string
    {
        $value = $this->string($key);
        if ($value === null) {
            return null;
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($dt === false || $dt->format('Y-m-d') !== $value) {
            return null;
        }

        return $value;
    }
}
