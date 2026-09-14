<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandScope;

/**
 * The staff-side console of a game or cloud server (audit §5p-2): one page next to the console, no prototype seam —
 * the log tail, a command line, the power buttons and the live console token, all through the customer service
 * endpoints with the organization header (staff holding `staff.service.manage`).
 */
final class StaffConsoleController extends Controller
{
    public function __construct(private readonly Authorizer $authorizer) {}

    public function show(Request $request, string $service): View
    {
        $user = $request->user();
        abort_unless($user !== null && $this->authorizer->can($user, 'staff.service.manage', CommandScope::global()), 403);
        $model = Service::query()->find($service);
        abort_if($model === null, 404);
        $organization = Organization::query()->find($model->organization_id);
        $node = $model->node_id ? Node::query()->find($model->node_id) : null;

        return view('staff-console', [
            'service' => $model, 'organization' => $organization, 'node' => $node,
            'canCommand' => in_array($model->family, ['game'], true), 'canPower' => in_array($model->family, ['game', 'cloud'], true),
            'csrf' => csrf_token(),
        ]);
    }
}
