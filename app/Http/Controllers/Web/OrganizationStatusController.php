<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Onhost\Domain\Incidents\OrganizationStatusService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\GreenService;
use Onhost\Platform\Errors\DomainError;

/** `/stav/<slug>` — the organization's own status page and its badge (audit §5j-5); `/green/badge.svg` — the green badge (§5j-10). */
final class OrganizationStatusController extends Controller
{
    public function page(OrganizationStatusService $status, string $slug): View
    {
        return view('status-page', ['status' => $this->build($status, $slug)]);
    }

    public function badge(OrganizationStatusService $status, string $slug): Response
    {
        $data = $this->build($status, $slug);

        return response($status->badgeSvg($data['overall'], $data['title']), 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=120']);
    }

    public function greenBadge(GreenService $green): Response
    {
        $pct = max(0, min(100, (int) request()->query('pct', (string) $green->platform()['renewable_pct'])));

        return response($green->badgeSvg($pct, (string) request()->query('lang', 'cs') === 'en' ? 'en' : 'cs'), 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=3600']);
    }

    /** @return array<string,mixed> a page that is off or unknown is a plain 404 on the web */
    private function build(OrganizationStatusService $status, string $slug): array
    {
        try {
            return $status->build(Organization::query()->where('slug', $slug)->first() ?? throw DomainError::notFound('status_page'));
        } catch (DomainError $e) {
            abort($e->status === 404 ? 404 : 403);
        }
    }
}
