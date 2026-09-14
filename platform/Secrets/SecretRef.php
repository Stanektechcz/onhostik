<?php

declare(strict_types=1);

namespace Onhost\Platform\Secrets;

use InvalidArgumentException;
use Stringable;

/** `bao://kv/onhost/providers/proxmox-cz1` or `env://PROXMOX_CZ1` — never a value. */
final class SecretRef implements Stringable
{
    private function __construct(public readonly string $scheme, public readonly string $path) {}

    public static function parse(string $ref): self
    {
        if (! preg_match('/^(bao|env|file|db):\/\/([A-Za-z0-9_\-\/\.]+)$/', $ref, $m)) {
            throw new InvalidArgumentException("Invalid secret reference: {$ref}");
        }

        return new self($m[1], $m[2]);
    }

    public static function bao(string $path): self
    {
        return new self('bao', ltrim($path, '/'));
    }

    public static function env(string $prefix): self
    {
        return new self('env', $prefix);
    }

    public function __toString(): string
    {
        return "{$this->scheme}://{$this->path}";
    }
}
