<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use App\Models\ServiceReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A customer reviews a service they own (admin "Recenze" module).
 *
 * The service is what proves the reviewer is a real customer — you can only
 * review something you actually have. A resubmission edits the existing review
 * and drops it back to pending, so an approved review cannot be silently
 * swapped for different text after moderation.
 */
final class ServiceReviewController extends Controller
{
    public function store(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('view', $service);

        $customer = $request->user()?->customer;
        abort_if($customer === null, 403);

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title'  => ['nullable', 'string', 'max:150'],
            'body'   => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $review = ServiceReview::firstOrNew([
            'customer_id' => $customer->id,
            'service_id'  => $service->id,
        ]);

        $review->fill([
            'product_id'   => $service->product_id,
            'rating'       => $validated['rating'],
            'title'        => $validated['title'] ?? null,
            'body'         => $validated['body'],
            // Any (re)submission goes back through moderation.
            'status'       => ServiceReview::STATUS_PENDING,
            'moderated_by' => null,
            'moderated_at' => null,
        ])->save();

        return back()->with('status', 'Děkujeme za recenzi — po schválení bude zveřejněna.');
    }
}
