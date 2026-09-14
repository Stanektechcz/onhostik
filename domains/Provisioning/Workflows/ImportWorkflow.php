<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows;

use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Services\Models\SiteImport;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\Web\ImportService;
use Throwable;

/** Site import: fetch + unpack + analyze → files → databases → finish (WordPress re-pointed, work dir removed). */
final class ImportWorkflow implements Workflow
{
    public static function kind(): string
    {
        return 'service.import';
    }

    public function queue(Operation $operation): string
    {
        $instance = $operation->provider_instance_id ? ProviderInstance::query()->find($operation->provider_instance_id) : null;

        return 'provider-'.($instance?->provider ?? 'default');
    }

    public function steps(Operation $operation): array
    {
        return [$this->fetchStep(), $this->filesStep(), $this->databasesStep(), $this->finishStep()];
    }

    public function compensate(StepContext $context): void
    {
        $import = SiteImport::query()->where('operation_id', $context->operation->id)->first();
        if ($import === null) {
            return;
        }
        $imports = $context->container->make(ImportService::class);
        if ($import->state === 'running') {
            $imports->finish($import, false, (array) $context->get('stats', []), (string) $context->get('log', '')."\nFAILED: ".(string) ($context->operation->error['message'] ?? 'import failed'));
        }
        try {
            $imports->cleanup($import, $context->service);
        } catch (Throwable) {
            // leftovers are pruned by onhost:web-tools:prune
        }
    }

    private function fetchStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Stažení a rozbalení archivu';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $imports = $context->container->make(ImportService::class);
                $import = $imports->open($service, $context->operation, (array) $context->operation->desired);
                $archive = $imports->fetch($import, $service);
                $dir = $imports->workDir($import);
                $entries = $imports->unpack($archive, $dir);
                $analysis = $imports->analyze($dir);
                $log = "archive: {$entries} entries, layout {$analysis['layout']}\n"
                    .'document root: '.($analysis['docroot_rel'] === '' ? '(archive root)' : $analysis['docroot_rel'])." — {$analysis['files']} files, ".number_format($analysis['bytes'] / 1048576, 1)." MB\n"
                    .'databases: '.($analysis['databases'] === [] ? 'none found' : implode(', ', array_map(fn ($d) => $d['name'], $analysis['databases'])))."\n"
                    .($analysis['wordpress'] ? "WordPress detected\n" : '');
                $import->forceFill(['stats' => array_merge((array) $import->stats, ['layout' => $analysis['layout'], 'docroot' => $analysis['docroot_rel'], 'files' => $analysis['files'], 'bytes' => $analysis['bytes'], 'databases_found' => count($analysis['databases']), 'wordpress' => $analysis['wordpress']]), 'log' => $log])->save();

                return StepResult::done(['import_id' => $import->id, 'work_dir' => $dir, 'docroot' => $analysis['docroot'], 'dumps' => $analysis['databases'], 'wordpress' => $analysis['wordpress'], 'log' => $log, 'stats' => ['files' => $analysis['files'], 'bytes' => $analysis['bytes'], 'databases' => 0]]);
            }
        };
    }

    private function filesStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Nahrání souborů webu';
            }

            public function run(StepContext $context): StepResult
            {
                if (! (bool) $context->desired('files', true)) {
                    return StepResult::done(['log' => (string) $context->get('log', '')."files: skipped on request\n"]);
                }
                $docroot = (string) $context->get('docroot');
                if (! is_dir($docroot)) {
                    return StepResult::fail('the unpacked archive is no longer on this worker; start the import again', false);
                }
                $result = $context->container->make(ImportService::class)->pushFiles($this->service($context), $docroot, (string) $context->desired('subdir', ''), (string) $context->get('work_dir'));

                return StepResult::done(['log' => (string) $context->get('log', '')."files: {$result['files']} uploaded (".number_format($result['bytes'] / 1048576, 1)." MB)\n"]);
            }
        };
    }

    private function databasesStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Import databází';
            }

            public function run(StepContext $context): StepResult
            {
                $log = (string) $context->get('log', '');
                $dumps = (array) $context->get('dumps', []);
                if (! (bool) $context->desired('databases', true) || $dumps === []) {
                    return StepResult::done(['log' => $log.'databases: '.($dumps === [] ? 'nothing to import' : 'skipped on request')."\n", 'databases' => []]);
                }
                foreach ($dumps as $d) {
                    if (! is_file((string) ($d['path'] ?? ''))) {
                        return StepResult::fail('the SQL dumps are no longer on this worker; start the import again', false);
                    }
                }
                $imports = $context->container->make(ImportService::class);
                $databases = $imports->importDatabases($this->service($context), $dumps);
                foreach ($databases as $db) {
                    $log .= "database {$db['name']} ← {$db['file']} (".number_format($db['bytes'] / 1048576, 1)." MB)\n";
                }
                $stats = (array) $context->get('stats', []);
                $stats['databases'] = count($databases);

                return StepResult::done(['log' => $log, 'databases' => $databases, 'stats' => $stats]);
            }
        };
    }

    private function finishStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Dokončení importu';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $imports = $context->container->make(ImportService::class);
                $import = SiteImport::query()->findOrFail((string) $context->get('import_id'));
                $log = (string) $context->get('log', '');
                if ((bool) $context->get('wordpress', false) && (bool) $context->desired('files', true)) {
                    try {
                        $log .= $imports->fixWordPress($service, (array) $context->get('databases', []), (string) $context->desired('subdir', ''));
                    } catch (Throwable $e) {
                        $log .= 'wordpress: could not re-point the installation ('.mb_substr($e->getMessage(), 0, 200).")\n";
                    }
                }
                $imports->finish($import, true, (array) $context->get('stats', []), $log);
                $imports->cleanup($import, $service);
                $context->container->make(ServiceFeatures::class)->forget($service);

                return StepResult::done(['log' => $log]);
            }
        };
    }
}
