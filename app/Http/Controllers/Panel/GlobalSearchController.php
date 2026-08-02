<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Provisioning\Models\DomainRegistration;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Support\Models\SupportTicket;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer-facing global search (audit 500 #346): one box that finds the user's
 * services, invoices, domains and tickets.
 *
 * Every query is scoped to the caller's own customer — this endpoint can never
 * surface another account's records. Results are capped per section so a broad
 * term stays fast.
 */
final class GlobalSearchController extends Controller
{
    private const PER_SECTION = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $customer = $request->user()?->customer;
        $term     = trim((string) $request->query('q', ''));

        if ($customer === null || mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $like = '%' . $term . '%';

        $services = Service::query()
            ->where('customer_id', $customer->id)
            ->where('label', 'like', $like)
            ->limit(self::PER_SECTION)
            ->get()
            ->map(fn (Service $s): array => [
                'type'  => 'Služba',
                'icon'  => 'server',
                'title' => $s->label,
                'meta'  => $s->status->value,
                'url'   => route('panel.services.show', $s),
            ]);

        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->where('number', 'like', $like)
            ->limit(self::PER_SECTION)
            ->get()
            ->map(fn (Invoice $i): array => [
                'type'  => 'Faktura',
                'icon'  => 'file-text',
                'title' => $i->number,
                'meta'  => $i->status->value,
                'url'   => route('panel.billing.invoices.show', $i),
            ]);

        $domains = DomainRegistration::query()
            ->whereHas('service', fn ($q) => $q->where('customer_id', $customer->id))
            ->where(fn ($q) => $q->where('domain', 'like', $like)->orWhere('tld', 'like', $like))
            ->limit(self::PER_SECTION)
            ->get()
            ->map(fn (DomainRegistration $d): array => [
                'type'  => 'Doména',
                'icon'  => 'globe',
                'title' => $d->fqdn(),
                'meta'  => $d->expires_at?->toDateString() ?? '',
                'url'   => route('panel.domains.show', $d),
            ]);

        $tickets = SupportTicket::query()
            ->where('customer_id', $customer->id)
            ->where('subject', 'like', $like)
            ->limit(self::PER_SECTION)
            ->get()
            ->map(fn (SupportTicket $t): array => [
                'type'  => 'Tiket',
                'icon'  => 'message-square',
                'title' => $t->subject,
                'meta'  => $t->status->value,
                'url'   => route('panel.support.show', $t),
            ]);

        return response()->json([
            'results' => $services
                ->concat($invoices)
                ->concat($domains)
                ->concat($tickets)
                ->values()
                ->all(),
        ]);
    }
}
