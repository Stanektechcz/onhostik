<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Limits;

/**
 * The proof that a raise is given at no charge: the approval of a second person that names this service, this number and
 * this price (or the single-operator waiver the server's configuration allows). Built only by `LimitRaiseService::grantFree`
 * after it has checked that proof; the quote takes it as a PHP argument, so nothing a cart or a request sends can become one.
 */
final readonly class LimitRaiseWaiver
{
    /** @param list<string> $approvalIds */
    public function __construct(
        public array $approvalIds,
        public string $by,
        public string $reason,
    ) {}

    /** @return array{approval_ids: list<string>, by: string, reason: string} */
    public function toArray(): array
    {
        return ['approval_ids' => $this->approvalIds, 'by' => $this->by, 'reason' => mb_substr($this->reason, 0, 250)];
    }
}
