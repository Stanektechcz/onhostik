<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents;

use Carbon\CarbonImmutable;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Incidents\Models\OnCallShift;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/**
 * The on-call rota (audit §5r-1). Shifts name the staff member who carries the pager; shifts may not overlap, so at
 * any moment there is one person or nobody. An alert records its assignee and the pager payload carries the name,
 * the daily staff digest shows who is on call now and who takes over next.
 */
final class OnCallRota
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function current(?CarbonImmutable $at = null): ?OnCallShift
    {
        $at ??= CarbonImmutable::now();

        return OnCallShift::query()->where('starts_at', '<=', $at)->where('ends_at', '>', $at)->orderBy('starts_at')->first();
    }

    public function next(?CarbonImmutable $at = null): ?OnCallShift
    {
        $at ??= CarbonImmutable::now();

        return OnCallShift::query()->where('starts_at', '>', $at)->orderBy('starts_at')->first();
    }

    /** @return array{user_id:string, name:string, email:?string, until:string}|null */
    public function assignee(?CarbonImmutable $at = null): ?array
    {
        $shift = $this->current($at);

        return $shift !== null ? self::person($shift) + ['until' => $shift->ends_at->toIso8601String()] : null;
    }

    /** @return list<array<string,mixed>> the running shift and those of the next `$days` days */
    public function upcoming(int $days = 14): array
    {
        $now = CarbonImmutable::now();

        return OnCallShift::query()->where('ends_at', '>', $now)->where('starts_at', '<', $now->addDays(max(1, $days)))->orderBy('starts_at')->limit(200)->get()->map(fn (OnCallShift $s) => self::present($s))->values()->all();
    }

    public function add(string $userRef, CarbonImmutable $from, CarbonImmutable $to, ?string $note, CommandContext $context): OnCallShift
    {
        $user = User::query()->where('id', $userRef)->orWhere('email', strtolower(trim($userRef)))->first();
        if ($user === null || ! $user->is_staff) {
            throw new DomainError('oncall_shift_user_invalid', 'The on-call must be a staff account (id or e-mail).', 422, ['field' => 'user']);
        }
        if ($to <= $from || $from->diffInDays($to) > 31) {
            throw new DomainError('oncall_shift_range_invalid', 'A shift ends after it starts and lasts at most 31 days.', 422, ['field' => 'ends_at']);
        }
        $clash = OnCallShift::query()->where('starts_at', '<', $to)->where('ends_at', '>', $from)->first();
        if ($clash !== null) {
            throw new DomainError('oncall_shift_overlap', 'The shift overlaps another one; shorten or remove that one first.', 409, ['shift' => self::present($clash)]);
        }
        $shift = OnCallShift::query()->create(['user_id' => $user->id, 'starts_at' => $from, 'ends_at' => $to, 'note' => $note !== null ? mb_substr($note, 0, 250) : null, 'created_by' => (string) ($context->actorId ?? $context->actorType)]);
        $this->audit->record($context, 'oncall.shift.add', 'succeeded', ['shift' => $shift->id, 'user' => $user->id, 'from' => $from->toIso8601String(), 'to' => $to->toIso8601String()], 'oncall_shift', $shift->id);

        return $shift;
    }

    public function remove(string $shiftId, CommandContext $context): array
    {
        $shift = OnCallShift::query()->find($shiftId) ?? throw DomainError::notFound('oncall_shift');
        $row = self::present($shift);
        $shift->delete();
        $this->audit->record($context, 'oncall.shift.remove', 'succeeded', ['shift' => $shiftId, 'user' => $row['user_id']], 'oncall_shift', $shiftId);

        return $row + ['removed' => true];
    }

    /** The digest line: who carries the pager now and the next hand-over. */
    public function handOverLine(): string
    {
        $now = $this->current();
        $next = $this->next();
        $line = 'On-call: '.($now !== null ? self::person($now)['name'].' do '.$now->ends_at->format('j. n. H:i') : 'nikdo není ve službě');

        return $line.($next !== null ? ' · předává '.self::person($next)['name'].' od '.$next->starts_at->format('j. n. H:i') : ' · další směna není naplánovaná');
    }

    /** @return array<string,mixed> */
    public static function present(OnCallShift $s): array
    {
        return self::person($s) + ['id' => $s->id, 'starts_at' => $s->starts_at->toIso8601String(), 'ends_at' => $s->ends_at->toIso8601String(), 'note' => $s->note, 'active' => $s->starts_at <= now() && $s->ends_at > now()];
    }

    /** @return array{user_id:string, name:string, email:?string} */
    private static function person(OnCallShift $s): array
    {
        $user = User::query()->find($s->user_id);

        return ['user_id' => (string) $s->user_id, 'name' => (string) ($user?->name ?: ($user?->email ?? $s->user_id)), 'email' => $user?->email];
    }
}
