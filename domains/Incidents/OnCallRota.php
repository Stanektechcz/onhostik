<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents;

use Carbon\CarbonImmutable;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Incidents\Models\OnCallShift;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

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

    /** The rota as an iCalendar feed (audit §5t-4): one VEVENT per shift, the UID stable per shift. */
    public function ical(int $days = 60): string
    {
        $esc = fn (string $s) => addcslashes(str_replace(["\r\n", "\n"], '\\n', $s), ',;');
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//ONhost//on-call rota//CS', 'CALSCALE:GREGORIAN', 'X-WR-CALNAME:ONhost on-call'];
        $shifts = OnCallShift::query()->where('ends_at', '>', now()->subDays(7))->where('starts_at', '<', now()->addDays(max(1, $days)))->orderBy('starts_at')->get();
        foreach ($shifts as $s) {
            $p = self::person($s);
            $lines = array_merge($lines, ['BEGIN:VEVENT', 'UID:'.($s->ical_uid ?: $s->id.'@onhost'), 'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'), 'DTSTART:'.$s->starts_at->copy()->utc()->format('Ymd\THis\Z'), 'DTEND:'.$s->ends_at->copy()->utc()->format('Ymd\THis\Z'),
                'SUMMARY:'.$esc('On-call: '.$p['name']), 'DESCRIPTION:'.$esc((string) ($s->note ?? '')), 'ATTENDEE;CN='.$esc($p['name']).':mailto:'.($p['email'] ?? ''), 'END:VEVENT']);
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * Imports shifts from an iCalendar text (a shared on-call calendar): each VEVENT with an ATTENDEE (or ORGANIZER)
     * e-mail of a staff account becomes a shift; a UID seen before updates that shift; an overlap or an unknown person is
     * reported and skipped.
     *
     * @return array{created:int, updated:int, skipped:list<array{uid:string, reason:string}>}
     */
    public function importIcal(string $text, CommandContext $context): array
    {
        $out = ['created' => 0, 'updated' => 0, 'skipped' => []];
        $text = preg_replace("/\r?\n[ \t]/", '', $text) ?? $text; // unfold continuation lines
        preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $text, $events);
        foreach (array_slice($events[1], 0, 500) as $body) {
            $field = function (string $name) use ($body): ?array {
                return preg_match('/^'.$name.'((?:;[^:\r\n]*)?):(.*)$/mi', $body, $m) === 1 ? [trim($m[1]), trim($m[2])] : null;
            };
            $uid = (string) ($field('UID')[1] ?? '');
            $email = null;
            foreach (['ATTENDEE', 'ORGANIZER'] as $who) {
                if (($f = $field($who)) !== null && preg_match('/mailto:(.+)$/i', $f[1], $mm) === 1) {
                    $email = strtolower(trim($mm[1]));
                    break;
                }
            }
            $parse = function (?array $f): ?CarbonImmutable {
                if ($f === null) {
                    return null;
                }
                $tz = preg_match('/TZID=([^;:]+)/', $f[0], $t) === 1 ? $t[1] : 'UTC';
                try {
                    return str_ends_with($f[1], 'Z') ? CarbonImmutable::createFromFormat('Ymd\THis\Z', $f[1], 'UTC') : CarbonImmutable::parse($f[1], $tz);
                } catch (\Throwable) {
                    return null;
                }
            };
            $from = $parse($field('DTSTART'));
            $to = $parse($field('DTEND'));
            if ($email === null || $from === null || $to === null) {
                $out['skipped'][] = ['uid' => $uid, 'reason' => 'missing attendee or times'];

                continue;
            }
            $existing = $uid !== '' ? OnCallShift::query()->where('ical_uid', $uid)->first() : null;
            try {
                if ($existing !== null) {
                    $clash = OnCallShift::query()->where('id', '!=', $existing->id)->where('starts_at', '<', $to)->where('ends_at', '>', $from)->exists();
                    $user = User::query()->where('email', $email)->first();
                    if ($clash || $user === null || ! $user->is_staff) {
                        throw new DomainError($clash ? 'oncall_shift_overlap' : 'oncall_shift_user_invalid', 'skipped', 422);
                    }
                    $existing->forceFill(['user_id' => $user->id, 'starts_at' => $from, 'ends_at' => $to, 'reminded_at' => null])->save();
                    $out['updated']++;
                } else {
                    $this->add($email, $from, $to, 'iCal', $context)->forceFill(['ical_uid' => $uid !== '' ? mb_substr($uid, 0, 190) : null])->save();
                    $out['created']++;
                }
            } catch (DomainError $e) {
                $out['skipped'][] = ['uid' => $uid, 'reason' => $e->error];
            }
        }
        $this->audit->record($context, 'oncall.shift.import', 'succeeded', ['created' => $out['created'], 'updated' => $out['updated'], 'skipped' => count($out['skipped'])], 'oncall_shift', 'import');

        return $out;
    }

    /** An hour before a shift its person is reminded once (rule `oncall.remind`). @return int reminders sent */
    public function remindUpcoming(): int
    {
        $sent = 0;
        foreach (OnCallShift::query()->whereNull('reminded_at')->where('starts_at', '>', now())->where('starts_at', '<=', now()->addHour())->get() as $shift) {
            $shift->forceFill(['reminded_at' => now()])->save();
            app(OutboxPublisher::class)->publish(GenericEvent::of('oncall.shift.starting', 'user', (string) $shift->user_id, ['shift' => $shift->id, 'starts' => $shift->starts_at->format('j. n. H:i'), 'ends' => $shift->ends_at->format('j. n. H:i')]));
            $sent++;
        }

        return $sent;
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
