<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Onhost\Domain\Notifications\Mail\TemplatedMail;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Notifications\Models\NotificationPreference;
use Onhost\Domain\Notifications\Models\NotificationTemplate;
use Onhost\Platform\Errors\DomainError;
use Throwable;

/**
 * Communication Center (blueprint §72): in-app feed per audience, transactional mail
 * outbox with versioned/localized templates, user preferences that can never switch
 * off mandatory legal/security notices.
 */
final class NotificationService
{
    /** template key → notification kind (preference bucket) */
    public const TEMPLATE_KINDS = [
        'welcome' => 'account', 'invitation' => 'account', 'order-received' => 'order', 'order-refunded' => 'order', 'service-activated' => 'service', 'service-suspended' => 'service',
        'ticket-ack' => 'ticket', 'ticket-reply' => 'ticket', 'invoice' => 'invoice.issued', 'invoice-overdue' => 'invoice.issued', 'dunning-notice' => 'dunning', 'dunning-suspended' => 'dunning', 'dunning-termination' => 'dunning', 'renewal-failed' => 'dunning',
        'service-plan-changed' => 'service', 'service-period-changed' => 'service', 'service-usage-high' => 'service', 'service-migrated' => 'service', 'service-migration-scheduled' => 'service', 'chargeback-approved' => 'service', 'chargeback-rejected' => 'service', 'chargeback-refunded' => 'wallet', 'loyalty-level-up' => 'account', 'marketplace-assigned' => 'order', 'marketplace-delivered' => 'order', 'referral-rewarded' => 'account', 'loyalty-streak' => 'account', 'loyalty-campaign' => 'account', 'renewal-underfunded' => 'wallet', 'digest-weekly' => 'digest', 'digest-staff' => 'digest', 'registrar-credit-low' => 'domain.expiry', 'domain-external-expiry' => 'domain.expiry',
        'domain-registered' => 'domain', 'domain-renewal' => 'domain.expiry', 'domain-renewed' => 'domain', 'domain-renewal-failed' => 'domain.expiry', 'domain-expired' => 'domain.expiry', 'domain-auth-info' => 'security.mfa',
        'security-login' => 'security.login', 'security-mfa' => 'security.mfa', 'security-password' => 'security.mfa', 'security-locked' => 'security.login', 'api-token' => 'api_token.created',
        'incident' => 'incident.affecting', 'incident-resolved' => 'incident.affecting', 'maintenance' => 'incident.affecting', 'payout' => 'partner', 'sla-credit' => 'invoice.issued', 'data-export' => 'legal.notice', 'legal-notice' => 'legal.notice',
        'site-down' => 'service', 'site-up' => 'service', 'service-stopped' => 'service', 'service-running' => 'service', 'deploy-failed' => 'service', 'import-finished' => 'service', 'certificate-failed' => 'service',
        'wallet-topup' => 'wallet', 'payment-received' => 'invoice.issued', 'wallet-runway' => 'wallet',
    ];

    public function notify(string $audience, string $kind, string $title, ?string $body = null, ?string $surface = null, ?string $organizationId = null, ?string $userId = null, ?string $refType = null, ?string $refId = null, ?string $event = null, string $severity = 'info', string $locale = 'cs'): ?Notification
    {
        if ($userId !== null && ! $this->allowed($userId, $kind, 'inapp')) {
            return null;
        }
        $locale = in_array($locale, (array) config('onhost.locales', ['cs']), true) ? $locale : 'cs';

        return Notification::query()->create([
            'organization_id' => $organizationId, 'user_id' => $userId, 'audience' => $audience, 'kind' => $kind, 'event' => $event, 'ref_type' => $refType, 'ref_id' => $refId,
            'title' => mb_substr((string) Lexicon::translate($title, $locale), 0, 250), 'body' => Lexicon::translate($body, $locale), 'surface' => $surface, 'severity' => in_array($severity, ['info', 'warn', 'hot'], true) ? $severity : 'info', 'locale' => $locale, // §5q-7: the organization's language
        ]);
    }

    /** @param array<string,mixed> $vars */
    public function queueMail(string $templateKey, string $to, array $vars, ?string $refType = null, ?string $refId = null, ?string $organizationId = null, string $locale = 'cs', ?string $userId = null, ?\DateTimeInterface $scheduledAt = null): ?MailOutbox
    {
        $template = NotificationTemplate::current($templateKey, 'mail', $locale);
        if ($template === null) {
            throw new DomainError('template_missing', "Mail template {$templateKey} is not defined.", 500);
        }
        $to = strtolower(trim($to));
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return null; // nothing to deliver to; the in-app notification still exists
        }
        $kind = self::TEMPLATE_KINDS[$templateKey] ?? $templateKey;
        $skipped = $userId !== null && ! $template->mandatory && ! $this->allowed($userId, $kind, 'mail');
        $rendered = $template->render($vars);

        $mail = MailOutbox::query()->create([
            'organization_id' => $organizationId, 'template_key' => $templateKey, 'locale' => $template->locale, 'to' => $to, 'subject' => mb_substr($rendered['subject'], 0, 250), 'vars' => $vars,
            'ref_type' => $refType, 'ref_id' => $refId, 'state' => $skipped ? 'skipped' : 'queued', 'scheduled_at' => $scheduledAt, 'last_error' => $rendered['missing'] !== [] ? 'missing placeholders: '.implode(',', $rendered['missing']) : null,
        ]);
        if ($mail->state === 'queued' && $scheduledAt === null && (bool) config('onhost.outbox.eager', true)) {
            try {
                SendMailOutboxJob::dispatch($mail->id)->afterCommit(); // out within seconds; onhost:mail:send remains the safety net
            } catch (Throwable $e) {
                Log::warning('mail eager send not scheduled', ['mail' => $mail->id, 'error' => $e->getMessage()]);
            }
        }

        return $mail;
    }

    /** Deliver queued mails (scheduler every minute). @return array{sent:int, failed:int} */
    public function sendQueued(int $limit = 100): array
    {
        $stats = ['sent' => 0, 'failed' => 0];
        $due = MailOutbox::query()->where('state', 'queued')->where(fn ($q) => $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))->where('attempts', '<', 5)->orderBy('created_at')->limit($limit)->get();
        foreach ($due as $mail) {
            $stats[$this->deliver($mail) ? 'sent' : 'failed']++;
        }

        return $stats;
    }

    /** Send one queued mail now; a failure schedules the next attempt (five in all) and reports false. */
    public function deliver(MailOutbox $mail): bool
    {
        $template = NotificationTemplate::current($mail->template_key, 'mail', $mail->locale);
        if ($template === null) {
            $mail->forceFill(['state' => 'failed', 'last_error' => 'template missing'])->save();

            return false;
        }
        $rendered = $template->render((array) $mail->vars);
        try {
            Mail::to($mail->to)->send(new TemplatedMail($rendered['subject'], $rendered['body'], $mail->template_key, $mail->ref_id));
            $mail->forceFill(['state' => 'sent', 'sent_at' => now(), 'attempts' => $mail->attempts + 1, 'subject' => mb_substr($rendered['subject'], 0, 250), 'last_error' => null])->save();

            return true;
        } catch (Throwable $e) {
            $attempts = $mail->attempts + 1;
            $mail->forceFill(['attempts' => $attempts, 'state' => $attempts >= 5 ? 'failed' : 'queued', 'last_error' => mb_substr($e->getMessage(), 0, 250), 'scheduled_at' => now()->addMinutes(5 * $attempts)])->save();

            return false;
        }
    }

    /** Force one mail out now (staff "Odeslat" button); returns the row in its new state. */
    public function sendNow(MailOutbox $mail): MailOutbox
    {
        $mail->forceFill(['state' => 'queued', 'scheduled_at' => null, 'attempts' => min($mail->attempts, 4)])->save();
        $template = NotificationTemplate::current($mail->template_key, 'mail', $mail->locale);
        if ($template === null) {
            throw new DomainError('template_missing', "Mail template {$mail->template_key} is not defined.", 500);
        }
        $rendered = $template->render((array) $mail->vars);
        Mail::to($mail->to)->send(new TemplatedMail($rendered['subject'], $rendered['body'], $mail->template_key, $mail->ref_id));
        $mail->forceFill(['state' => 'sent', 'sent_at' => now(), 'attempts' => $mail->attempts + 1, 'last_error' => null])->save();

        return $mail;
    }

    /** @param list<string> $ids */
    public function markRead(string $audience, array $ids, ?string $organizationId, ?string $userId): int
    {
        $query = Notification::query()->where('audience', $audience)->whereIn('id', $ids)->whereNull('read_at');
        if ($audience === 'customer') {
            $query->where(fn ($q) => $q->where('organization_id', $organizationId)->orWhere('user_id', $userId));
        }

        return $query->update(['read_at' => now()]);
    }

    public function setPreference(string $userId, string $kind, string $channel, bool $enabled): NotificationPreference
    {
        if (! $enabled && in_array($kind, (array) config('onhost.notifications.mandatory_kinds', []), true)) {
            throw new DomainError('notification_mandatory', "Notifications of kind {$kind} are mandatory and cannot be disabled.", 422, ['field' => 'kind']);
        }

        return NotificationPreference::query()->updateOrCreate(['user_id' => $userId, 'kind' => $kind, 'channel' => $channel], ['enabled' => $enabled]);
    }

    public function allowed(string $userId, string $kind, string $channel): bool
    {
        if (in_array($kind, (array) config('onhost.notifications.mandatory_kinds', []), true)) {
            return true;
        }
        $preference = NotificationPreference::query()->where('user_id', $userId)->where('kind', $kind)->where('channel', $channel)->first();

        return $preference === null || $preference->enabled;
    }

    /** Test render of a template with sample variables — used by the template editor and the seeder self-check. @return array{subject:string, body:string, missing:list<string>, html:string} */
    public function testRender(string $key, string $channel, string $locale, array $vars): array
    {
        $template = NotificationTemplate::current($key, $channel, $locale);
        if ($template === null) {
            throw new DomainError('template_missing', "Template {$key}/{$channel}/{$locale} is not defined.", 404);
        }
        $rendered = $template->render($vars);

        return $rendered + ['html' => $channel === 'mail' ? TemplatedMail::toHtml($rendered['body']) : ''];
    }
}
