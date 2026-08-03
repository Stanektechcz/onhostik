<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Models\ServiceGitRepository;
use App\Domains\Provisioning\Services\GitDeployService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Customer-facing git deployment: connect a repository to a hosting service,
 * deploy on demand, or redeploy automatically on push.
 */
final class ServiceGitController extends Controller
{
    public function __construct(private readonly GitDeployService $git) {}

    public function store(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('view', $service);

        $validated = $request->validate([
            'repository_url'       => ['required', 'string', 'max:500'],
            'branch'               => ['required', 'string', 'max:100'],
            'deploy_path'          => ['nullable', 'string', 'max:200'],
            'provider'             => ['nullable', 'in:github,gitlab,bitbucket,custom'],
            'post_deploy_commands' => ['nullable', 'string', 'max:1000'],
            'auto_deploy'          => ['boolean'],
        ]);

        // Validate before persisting: a rejected repository must never end up
        // stored, or a later deploy would run against it.
        try {
            $this->git->assertValidRepositoryUrl($validated['repository_url']);
            $this->git->assertValidBranch($validated['branch']);
            $this->git->assertValidDeployPath($validated['deploy_path'] ?? null);
            $this->git->assertValidPostDeployCommands(
                $this->commandLines($validated['post_deploy_commands'] ?? null),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['repository_url' => $e->getMessage()])->withInput();
        }

        ServiceGitRepository::updateOrCreate(
            ['service_id' => $service->id],
            [
                'provider'             => $validated['provider'] ?? $this->guessProvider($validated['repository_url']),
                'repository_url'       => trim($validated['repository_url']),
                'branch'               => trim($validated['branch']),
                'deploy_path'          => $validated['deploy_path'] ?? null,
                'post_deploy_commands' => $validated['post_deploy_commands'] ?? null,
                'auto_deploy'          => $request->boolean('auto_deploy'),
                'webhook_token'        => ServiceGitRepository::where('service_id', $service->id)->value('webhook_token')
                    ?? Str::random(48),
                'status'               => 'idle',
            ],
        );

        return back()->with('status', 'Repozitář byl připojen ke službě.');
    }

    public function deploy(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('view', $service);

        $repo = ServiceGitRepository::where('service_id', $service->id)->first();

        if ($repo === null) {
            return back()->withErrors(['git' => 'Ke službě není připojen žádný repozitář.']);
        }

        $result = $this->git->deploy($repo);

        if (! $result['ok']) {
            return back()->withErrors(['git' => $result['error'] ?? 'Nasazení selhalo.']);
        }

        return back()->with('status', $result['dry_run']
            ? 'Nasazení proběhlo v simulovaném režimu (provider je v mock/dry-run).'
            : 'Nasazení proběhlo úspěšně.');
    }

    public function destroy(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('view', $service);

        ServiceGitRepository::where('service_id', $service->id)->delete();

        return back()->with('status', 'Repozitář byl odpojen. Soubory na webu zůstávají beze změny.');
    }

    /** Rotate the webhook token — used when a secret may have leaked. */
    public function rotateToken(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('view', $service);

        $repo = ServiceGitRepository::where('service_id', $service->id)->firstOrFail();
        $repo->forceFill(['webhook_token' => Str::random(48)])->save();

        return back()->with('status', 'Webhook URL byla obnovena — aktualizujte ji u poskytovatele.');
    }

    /** @return list<string> */
    private function commandLines(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $l): string => trim($l),
            preg_split('/\R/', $raw) ?: [],
        )));
    }

    private function guessProvider(string $url): string
    {
        return match (true) {
            str_contains($url, 'github.com')    => 'github',
            str_contains($url, 'gitlab')        => 'gitlab',
            str_contains($url, 'bitbucket.org') => 'bitbucket',
            default                             => 'custom',
        };
    }
}
