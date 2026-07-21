<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use App\Models\CustomerSegmentTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Bulk actions over selected customers (audit G99) — activating, deactivating
 * and tagging had to be done one customer at a time.
 *
 * Deliberately NOT bulk-delete: deleting customers wholesale is not something
 * that should be one mis-click away, and soft-deleting an account with live
 * services would strand those services.
 */
class CustomerBulkActionController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action'         => ['required', 'in:activate,deactivate,tag,untag'],
            'customer_ids'   => ['required', 'array', 'min:1', 'max:500'],
            'customer_ids.*' => ['integer'],
            'tag_id'         => ['nullable', 'integer', 'exists:customer_segment_tags,id'],
        ]);

        $action    = (string) $validated['action'];
        $customers = Customer::with('user')->whereIn('id', $validated['customer_ids'])->get();

        if ($customers->isEmpty()) {
            return back()->withErrors(['customer_ids' => 'Nebyl vybrán žádný zákazník.']);
        }

        if (in_array($action, ['tag', 'untag'], true) && ($validated['tag_id'] ?? null) === null) {
            return back()->withErrors(['tag_id' => 'Pro označení vyberte štítek.']);
        }

        $affected = match ($action) {
            'activate', 'deactivate' => $this->setActive($customers, $action === 'activate'),
            'tag', 'untag'           => $this->syncTag($customers, (int) $validated['tag_id'], $action === 'tag'),

            // Validation already restricts the set; this keeps a future action
            // from silently doing nothing.
            default => throw new \InvalidArgumentException("Unhandled bulk action [{$action}]."),
        };

        activity('customer')
            ->causedBy($request->user())
            ->withProperties([
                'action'   => $action,
                'affected' => $affected,
                'tag_id'   => $validated['tag_id'] ?? null,
            ])
            ->log('customer.bulk_action');

        return back()->with('status', match ($action) {
            'activate'   => "Aktivováno {$affected} zákazníků.",
            'deactivate' => "Deaktivováno {$affected} zákazníků.",
            'tag'        => "Štítek přidán {$affected} zákazníkům.",
            'untag'      => "Štítek odebrán {$affected} zákazníkům.",
        });
    }

    /**
     * Enable/disable the linked user accounts.
     *
     * @param  \Illuminate\Support\Collection<int, Customer>  $customers
     */
    private function setActive(\Illuminate\Support\Collection $customers, bool $active): int
    {
        $affected = 0;

        foreach ($customers as $customer) {
            $user = $customer->user;

            if ($user === null || (bool) $user->is_active === $active) {
                continue;
            }

            // Never let a bulk sweep lock out an administrator.
            if (! $active && $user->hasRole('admin')) {
                continue;
            }

            $user->update(['is_active' => $active]);
            $affected++;
        }

        return $affected;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Customer>  $customers
     */
    private function syncTag(\Illuminate\Support\Collection $customers, int $tagId, bool $attach): int
    {
        $tag = CustomerSegmentTag::find($tagId);

        if ($tag === null) {
            return 0;
        }

        $affected = 0;

        DB::transaction(function () use ($customers, $tag, $attach, &$affected): void {
            foreach ($customers as $customer) {
                $attach
                    ? $customer->segmentTags()->syncWithoutDetaching([$tag->id])
                    : $customer->segmentTags()->detach($tag->id);

                $affected++;
            }
        });

        return $affected;
    }
}
