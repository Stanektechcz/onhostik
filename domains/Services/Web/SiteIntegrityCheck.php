<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\WebToolsProvider;
use Onhost\Providers\Shell\Q;
use Throwable;

/**
 * Whether a site looks like somebody else is running it. The platform could quarantine a site for abuse and let it
 * out again, but nothing ever **noticed**: a hacked WordPress sending spam or serving malware was found by the people
 * it was sent to — a blocklist, another host's abuse desk, the customer's own visitors — and by then the node's
 * address is on a list and every other customer on it pays for it.
 *
 * This looks for the two marks a compromise leaves that can be read without a scanner nobody has deployed yet:
 * executable code where a site only ever keeps what visitors upload, and the fingerprints of the ready-made web
 * shells (`eval(base64_decode(`, `assert($_POST[`, the old `preg_replace` /e trick, a system call taking its
 * argument straight from the request). Both are cheap: one `find` and one `grep` on the node, bounded and capped.
 *
 * It **reports and never acts**. A finding is a reason for somebody to look, not a reason to switch a customer's
 * business off: a plugin that legitimately keeps a PHP file under `uploads/` exists, and a site taken down by a rule
 * nobody checked is worse than the thing the rule was looking for. Acting stays where it belongs — an abuse case,
 * with its statement of reasons, its quarantine and its way back (`ComplianceService`).
 */
final class SiteIntegrityCheck
{
    /** Ready-made web shells give themselves away with these; kept conservative on purpose. */
    public const SHELL_MARKS = 'eval\(base64_decode|eval\(gzinflate|eval\(gzuncompress|eval\(str_rot13|assert\(\$_(POST|GET|REQUEST|COOKIE)|preg_replace\([^)]*/e[\x27"]|(system|shell_exec|passthru|popen|proc_open)\(\$_(POST|GET|REQUEST|COOKIE)';

    /** Directories that hold what visitors upload — and never code that runs. */
    public const UPLOAD_DIRS = ['uploads', 'upload', 'files', 'media', 'attachments'];

    /** At most this many names per finding: the point is to start a look, not to list a whole site. */
    public const MAX_FILES = 20;

    public function __construct(
        private readonly ServiceFeatures $features,
        private readonly OutboxPublisher $outbox,
        private readonly AuditRecorder $audit,
    ) {}

    /** @return array{checked:int, suspicious:int, skipped:int, errors:int} */
    public function run(int $limit = 200): array
    {
        $stats = ['checked' => 0, 'suspicious' => 0, 'skipped' => 0, 'errors' => 0];
        $services = Service::query()->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])
            ->whereIn('family', ['web', 'managed'])->orderBy('id')->limit(max(1, $limit))->get();
        foreach ($services as $service) {
            try {
                $findings = $this->scan($service);
            } catch (Throwable) {
                $stats['errors']++;

                continue;
            }
            if ($findings === null) {
                $stats['skipped']++; // no shell on this site yet: nothing to read it with

                continue;
            }
            $stats['checked']++;
            $tags = (array) ($service->tags ?? []);
            $before = (array) ($tags['integrity'] ?? []);
            $tags['integrity'] = ['findings' => $findings, 'checked_at' => now()->toIso8601String(), 'told_on' => $before['told_on'] ?? null];
            if ($findings !== []) {
                $stats['suspicious']++;
                if ((string) ($before['told_on'] ?? '') !== now()->toDateString()) { // once a day per site, however many files
                    $tags['integrity']['told_on'] = now()->toDateString();
                    $this->tell($service, $findings);
                }
            }
            $service->forceFill(['tags' => $tags])->save();
        }

        return $stats;
    }

    /**
     * What this site looks like now; null when the platform has no shell on it.
     *
     * @return list<array{kind:string, files:list<string>, more:bool}>|null
     */
    public function scan(Service $service): ?array
    {
        [$tools, $ref] = $this->features->toolsFor($service);
        if (! $tools instanceof WebToolsProvider || ! $tools->shellAvailable($ref)) {
            return null;
        }
        $root = rtrim($tools->transport($ref)->root(), '/');
        if ($root === '' || $root === '/') {
            return null; // a root nobody can name is not a root to search
        }
        $findings = [];
        $shell = $tools->shell($ref);
        $uploads = $shell->run(self::uploadsCommand($root), ['timeout' => 120]);
        $inUploads = self::names($uploads->stdout, $root);
        if ($inUploads !== []) {
            $findings[] = ['kind' => 'php_in_uploads', 'files' => array_slice($inUploads, 0, self::MAX_FILES), 'more' => count($inUploads) > self::MAX_FILES];
        }
        $shells = $shell->run(self::shellsCommand($root), ['timeout' => 180]);
        $marked = self::names($shells->stdout, $root);
        if ($marked !== []) {
            $findings[] = ['kind' => 'web_shell_marks', 'files' => array_slice($marked, 0, self::MAX_FILES), 'more' => count($marked) > self::MAX_FILES];
        }

        return $findings;
    }

    /** Code that runs, in a directory that only ever holds what visitors uploaded. */
    public static function uploadsCommand(string $root): string
    {
        $paths = [];
        foreach (self::UPLOAD_DIRS as $dir) {
            $paths[] = '-path '.Q::arg('*/'.$dir.'/*');
        }

        return 'find '.Q::arg($root).' -type f \( '.implode(' -o ', $paths).' \) \( -name '.Q::arg('*.php').' -o -name '.Q::arg('*.phtml').' \) -not -path '.Q::arg('*/vendor/*').' 2>/dev/null | head -'.(self::MAX_FILES + 1);
    }

    /** The fingerprints the ready-made shells carry. */
    public static function shellsCommand(string $root): string
    {
        return 'grep -REl --include='.Q::arg('*.php').' --include='.Q::arg('*.phtml')
            .' --exclude-dir='.Q::arg('node_modules').' --exclude-dir='.Q::arg('vendor').' --exclude-dir='.Q::arg('.git')
            .' '.Q::arg(self::SHELL_MARKS).' '.Q::arg($root).' 2>/dev/null | head -'.(self::MAX_FILES + 1);
    }

    /**
     * File names as the customer knows them: relative to the site root, without anything that is not a path.
     *
     * @return list<string>
     */
    private static function names(string $output, string $root): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', trim($output)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || ! str_starts_with($line, $root)) {
                continue; // a shell that answered something else (an error, a banner) says nothing about the site
            }
            $out[] = ltrim(mb_substr($line, mb_strlen($root)), '/');
        }

        return array_values(array_unique(array_filter($out)));
    }

    /**
     * @param  list<array{kind:string, files:list<string>, more:bool}>  $findings
     */
    private function tell(Service $service, array $findings): void
    {
        $context = CommandContext::system('sites.integrity')->withScope($service->organization_id);
        $this->audit->record($context, 'service.integrity.suspicious', 'succeeded', ['findings' => $findings], 'service', $service->id);
        $this->outbox->publish(GenericEvent::of('service.integrity.suspicious', 'service', $service->id, [
            'hostname' => $service->hostname, 'label' => $service->label, 'findings' => $findings,
            'kinds' => array_column($findings, 'kind'), 'files' => array_sum(array_map(fn (array $f) => count($f['files']), $findings)),
        ], $service->organization_id));
    }
}
