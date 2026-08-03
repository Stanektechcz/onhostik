<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhook;

use App\Domains\Provisioning\Models\ServiceGitRepository;
use App\Domains\Provisioning\Services\GitDeployService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Auto-deploy endpoint: GitHub/GitLab/Bitbucket call this on push.
 *
 * The URL's token IS the credential — it is compared in constant time and an
 * unknown token gets the same 404 as a missing repository, so this endpoint
 * cannot be used to probe which services exist. Only pushes to the configured
 * branch redeploy; anything else is acknowledged and ignored, otherwise every
 * feature branch would overwrite production.
 */
final class GitPushWebhookController extends Controller
{
    public function __invoke(Request $request, string $token, GitDeployService $git): JsonResponse
    {
        $repo = $this->resolve($token);

        if ($repo === null || ! $repo->auto_deploy) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $pushedRef = $this->pushedRef($request);

        if ($pushedRef !== null && $pushedRef !== $repo->branch) {
            return response()->json([
                'message' => 'Ignored — different branch.',
                'branch'  => $pushedRef,
            ]);
        }

        $result = $git->deploy($repo);

        return response()->json([
            'message' => $result['ok'] ? 'Deployed.' : 'Deploy failed.',
            'dry_run' => $result['dry_run'],
        ], $result['ok'] ? 200 : 500);
    }

    private function resolve(string $token): ?ServiceGitRepository
    {
        // Constant-time compare against candidates rather than a direct lookup
        // on user input, so timing can't be used to recover a valid token.
        foreach (ServiceGitRepository::query()->whereNotNull('webhook_token')->cursor() as $repo) {
            if (hash_equals((string) $repo->webhook_token, $token)) {
                return $repo;
            }
        }

        return null;
    }

    /** Branch name from the provider's payload, if it carries one. */
    private function pushedRef(Request $request): ?string
    {
        $ref = $request->input('ref');

        if (is_string($ref) && str_starts_with($ref, 'refs/heads/')) {
            return substr($ref, strlen('refs/heads/'));
        }

        // Bitbucket nests it differently.
        $bitbucket = $request->input('push.changes.0.new.name');

        return is_string($bitbucket) ? $bitbucket : null;
    }
}
