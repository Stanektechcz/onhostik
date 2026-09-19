<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Onhost\Domain\Notifications\Mail\TemplatedMail;
use Onhost\Domain\Notifications\MailHealth;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\NotificationService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Throwable;

/**
 * Sends one test mail through the configured transactional mailer right now (audit §5z) and reports the delivery or the
 * SMTP error; also shows the mail queue (queued, failed and the latest error) so a stuck delivery is visible.
 */
final class MailTest extends Command
{
    protected $signature = 'onhost:mail:test {to : recipient e-mail} {--retry-failed : after a successful test, send again the queued and failed mails (up to 100)}';

    protected $description = 'Send a test e-mail through the transactional mailer and show the state of the mail queue';

    public function handle(AuditRecorder $audit): int
    {
        $to = strtolower(trim((string) $this->argument('to')));
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Invalid e-mail address.');

            return self::FAILURE;
        }
        $this->line('mailer '.config('mail.default').' · host '.(string) config('mail.mailers.'.config('mail.default').'.host', '—').' · from '.(string) config('mail.from.address'));
        $exit = self::SUCCESS;
        try {
            Mail::to($to)->send(new TemplatedMail('ONhost · test e-mailu', 'Tento e-mail ověřuje odesílání z ONhost ('.config('app.url').").\n\nOdesláno ".now()->format('j. n. Y H:i:s').'.', 'mail.test', null));
            $this->info("Sent to {$to}. Check the inbox and the spam folder (SPF/DKIM of the sender domain).");
            $audit->record(CommandContext::system('cli:mail:test'), 'mail.test', 'succeeded', ['to' => $to], 'mail', null);
            $requeued = app(MailHealth::class)->confirmDelivery();
            if ($requeued > 0) {
                $this->info("Mail was reported as failing: the alarm is closed and {$requeued} mails that had given up are queued again.");
            }
        } catch (Throwable $e) {
            $this->error('Delivery failed: '.mb_substr($e->getMessage(), 0, 300));
            $exit = self::FAILURE;
        }
        if ($exit === self::SUCCESS && $this->option('retry-failed')) {
            $sent = $failed = 0;
            foreach (MailOutbox::query()->whereIn('state', ['queued', 'failed'])->orderBy('created_at')->limit(100)->get() as $mail) {
                $mail->forceFill(['state' => 'queued', 'attempts' => 0, 'scheduled_at' => null])->save();
                app(NotificationService::class)->deliver($mail) ? $sent++ : $failed++;
            }
            $this->info("Queue retried: {$sent} sent, {$failed} still failing.");
        }
        $queue = DB::table('mail_outbox')->selectRaw('state, count(*) as c')->groupBy('state')->pluck('c', 'state')->all();
        $this->line('mail queue: '.($queue === [] ? 'empty' : collect($queue)->map(fn ($c, $s) => "{$s} {$c}")->implode(' · ')));
        $last = DB::table('mail_outbox')->whereNotNull('last_error')->orderByDesc('updated_at')->value('last_error');
        if ($last !== null) {
            $this->warn('latest queue error: '.$last);
        }

        return $exit;
    }
}
