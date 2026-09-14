<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Application;
use Symfony\Component\Yaml\Yaml;

/**
 * Writes `compliance/oss-inventory.yml` (blueprint §26: OSS licence inventory, NIS2/CRA supply-chain evidence)
 * from composer.lock and the vendored front-end runtime declared in docs/ui/template-inventory.md.
 */
final class GenerateOssInventory extends Command
{
    protected $signature = 'onhost:oss-inventory {--out=compliance/oss-inventory.yml}';

    protected $description = 'Generate the open-source component inventory with licences from composer.lock';

    /** Front-end runtime pulled by the prototype surfaces (docs/ui/template-inventory.md §7). */
    private const FRONTEND = [
        ['name' => 'react', 'version' => '18.x (UMD via unpkg — to be vendored, backlog UI-01)', 'license' => 'MIT', 'scope' => 'surfaces'],
        ['name' => 'react-dom', 'version' => '18.x', 'license' => 'MIT', 'scope' => 'surfaces'],
        ['name' => '@babel/standalone', 'version' => '7.x', 'license' => 'MIT', 'scope' => 'surfaces (in-browser compile, backlog UI-02)'],
        ['name' => 'Archivo (Google Fonts)', 'version' => 'variable', 'license' => 'OFL-1.1', 'scope' => 'design system'],
        ['name' => 'Modernist design system (_ds bundle)', 'version' => '31154b91', 'license' => 'proprietary — ONhost', 'scope' => 'surfaces'],
    ];

    public function handle(): int
    {
        $lock = json_decode((string) file_get_contents(base_path('composer.lock')), true);
        if (! is_array($lock)) {
            $this->error('composer.lock not readable');

            return self::FAILURE;
        }
        $components = [];
        foreach (['packages' => 'runtime', 'packages-dev' => 'development'] as $section => $scope) {
            foreach ((array) ($lock[$section] ?? []) as $package) {
                $components[] = [
                    'name' => $package['name'], 'version' => $package['version'], 'license' => implode(' OR ', (array) ($package['license'] ?? ['unknown'])),
                    'scope' => $scope, 'source' => $package['source']['url'] ?? ($package['dist']['url'] ?? null), 'homepage' => $package['homepage'] ?? null,
                ];
            }
        }
        usort($components, fn ($a, $b) => [$a['scope'], $a['name']] <=> [$b['scope'], $b['name']]);
        $licenses = [];
        foreach ($components as $c) {
            $licenses[$c['license']] = ($licenses[$c['license']] ?? 0) + 1;
        }
        arsort($licenses);

        $document = [
            'generated_at' => now()->toIso8601String(),
            'generator' => 'php artisan onhost:oss-inventory',
            'policy' => [
                'allowed' => ['MIT', 'BSD-2-Clause', 'BSD-3-Clause', 'Apache-2.0', 'ISC', 'OFL-1.1', 'MPL-2.0', 'LGPL-2.1-or-later', 'LGPL-3.0-or-later', 'GPL-2.0-or-later (tooling only)', 'AGPL-3.0 (separately deployed services only: Proxmox, ISPConfig)'],
                'review_required' => ['unknown', 'proprietary'],
                'note' => 'Executors (Proxmox VE/PBS AGPL-3.0, ISPConfig BSD-3-Clause, aaPanel proprietary/free tier, Pterodactyl MIT, PowerDNS GPL-2.0, RKE2 Apache-2.0) run as separate services and are not linked into this code base.',
            ],
            'summary' => ['components' => count($components), 'licenses' => $licenses],
            'platform' => ['php' => PHP_VERSION, 'laravel' => Application::VERSION],
            'components' => $components,
            'frontend' => self::FRONTEND,
            'external_services' => [
                ['name' => 'Proxmox VE', 'license' => 'AGPL-3.0', 'role' => 'compute executor'], ['name' => 'Proxmox Backup Server', 'license' => 'AGPL-3.0', 'role' => 'backup executor'],
                ['name' => 'ISPConfig', 'license' => 'BSD-3-Clause', 'role' => 'shared web/mail executor'], ['name' => 'aaPanel', 'license' => 'proprietary (free tier)', 'role' => 'managed web executor'],
                ['name' => 'Pterodactyl', 'license' => 'MIT', 'role' => 'game executor'], ['name' => 'PowerDNS Authoritative', 'license' => 'GPL-2.0', 'role' => 'canonical DNS'],
                ['name' => 'WEDOS WAPI / WEDOS Zone', 'license' => 'commercial API', 'role' => 'registrar, fallback DNS'], ['name' => 'RKE2 + BuildKit', 'license' => 'Apache-2.0', 'role' => 'apps executor'],
                ['name' => 'Comgate', 'license' => 'commercial API', 'role' => 'payments'], ['name' => 'OpenAI-compatible / Anthropic APIs', 'license' => 'commercial API', 'role' => 'support assistant (opt-in)'],
            ],
        ];
        $out = base_path((string) $this->option('out'));
        if (! is_dir(dirname($out))) {
            mkdir(dirname($out), 0777, true);
        }
        file_put_contents($out, Yaml::dump($document, 6, 2));
        $this->info(sprintf('%s: %d components', $out, count($components)));

        return self::SUCCESS;
    }
}
