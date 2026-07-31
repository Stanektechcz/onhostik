<?php

declare(strict_types=1);

namespace App\Domains\Developer\Services;

use RuntimeException;

/**
 * An OAuth2 error (RFC 6749 §5.2) — carries the machine error code and the HTTP
 * status the token endpoint should return.
 */
final class OAuthException extends RuntimeException
{
    public function __construct(
        public readonly string $error,
        public readonly string $description,
        public readonly int $status = 400,
    ) {
        parent::__construct($description);
    }
}
