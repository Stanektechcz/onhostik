<?php

declare(strict_types=1);

namespace Onhost\Platform\Audit;

/**
 * Walks the audit chain in the order AuditRecorder writes it (created_at, id) and reports the first broken link: the first
 * row must continue the genesis hash, every other row must name the hash of the row before it (a deleted or inserted row
 * breaks that), and the stored hash must equal the recomputed one (an edited field breaks that). Rows are read in
 * keyset pages, so memory stays flat however long the chain is. A removed newest row leaves no trace in the chain itself.
 */
final class AuditChainVerifier
{
    public const PAGE = 500;

    /** @return array{ok:bool, checked:int, broken_id:?string, broken_at:?int, reason:?string} */
    public function verify(?int $maxRows = null): array
    {
        $prev = HashChain::GENESIS;
        $checked = 0;
        $cursor = null;
        while (true) {
            $query = AuditEvent::query()->orderBy('created_at')->orderBy('id')->limit(self::PAGE);
            if ($cursor !== null) {
                $query->where(fn ($q) => $q->where('created_at', '>', $cursor['at'])->orWhere(fn ($same) => $same->where('created_at', $cursor['at'])->where('id', '>', $cursor['id'])));
            }
            $page = $query->get();
            if ($page->isEmpty()) {
                break;
            }
            foreach ($page as $event) {
                $checked++;
                if ($event->prev_hash !== $prev) {
                    return $this->broken($event, $checked, $checked === 1 ? 'first row does not continue the genesis hash' : 'prev_hash does not match the hash of the row before it (row removed, inserted or reordered)');
                }
                if (! HashChain::verifyEvent($event, $prev)) {
                    return $this->broken($event, $checked, 'stored hash differs from the recomputed one (row was modified)');
                }
                $prev = $event->hash;
                if ($maxRows !== null && $checked >= $maxRows) {
                    return ['ok' => true, 'checked' => $checked, 'broken_id' => null, 'broken_at' => null, 'reason' => null];
                }
            }
            $last = $page->last();
            $cursor = ['at' => $last->getRawOriginal('created_at'), 'id' => $last->id];
        }

        return ['ok' => true, 'checked' => $checked, 'broken_id' => null, 'broken_at' => null, 'reason' => null];
    }

    /** @return array{ok:bool, checked:int, broken_id:?string, broken_at:?int, reason:?string} */
    private function broken(AuditEvent $event, int $position, string $reason): array
    {
        return ['ok' => false, 'checked' => $position, 'broken_id' => $event->id, 'broken_at' => $position, 'reason' => $reason];
    }
}
