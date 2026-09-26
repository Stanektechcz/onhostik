<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * One answer about one VAT number (TASK-0031, D31.1). `unknown` is not a verdict: the register could not be asked or could
 * not answer, and the caller keeps whatever it knew before — a customer is never told that a number is wrong because a
 * member state's database was down. `retryable` says whether asking again later can help (an outage, a busy register) or
 * not (our own requester details refused, our address blocked, the check switched off).
 */
final class VatCheckResult
{
    public const VALID = 'valid';

    public const INVALID = 'invalid';

    public const UNKNOWN = 'unknown';

    private function __construct(
        public readonly string $status,
        public readonly ?string $consultationNumber = null,
        public readonly ?string $name = null,
        public readonly ?string $address = null,
        public readonly ?string $requestDate = null,
        public readonly ?string $errorCode = null,
        public readonly bool $retryable = false,
    ) {}

    /** The register knows the number; the consultation number is the evidence for the reverse-charge decision. */
    public static function valid(?string $consultationNumber, ?string $name, ?string $address, ?string $requestDate): self
    {
        return new self(self::VALID, $consultationNumber, $name, $address, $requestDate);
    }

    /** The register answered that the number is not registered (or cannot be a number at all: `invalid_input`). */
    public static function invalid(?string $errorCode = null, ?string $requestDate = null): self
    {
        return new self(self::INVALID, requestDate: $requestDate, errorCode: $errorCode);
    }

    public static function unknown(string $errorCode, bool $retryable): self
    {
        return new self(self::UNKNOWN, errorCode: $errorCode, retryable: $retryable);
    }

    public function isKnown(): bool
    {
        return $this->status !== self::UNKNOWN;
    }
}
