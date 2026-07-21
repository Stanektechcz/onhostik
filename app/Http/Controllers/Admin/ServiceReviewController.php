<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Moderation queue for customer service reviews (the "Recenze" module).
 *
 * Nothing a customer writes is visible until an admin approves it here — an
 * auto-publishing review page is a spam and abuse magnet.
 */
final class ServiceReviewController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', ServiceReview::STATUS_PENDING);

        $reviews = ServiceReview::with(['customer.user', 'product', 'service', 'moderator'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'pending'  => ServiceReview::where('status', ServiceReview::STATUS_PENDING)->count(),
            'approved' => ServiceReview::where('status', ServiceReview::STATUS_APPROVED)->count(),
            'rejected' => ServiceReview::where('status', ServiceReview::STATUS_REJECTED)->count(),
        ];

        return view('admin.reviews', compact('reviews', 'status', 'counts'));
    }

    public function approve(Request $request, ServiceReview $review): RedirectResponse
    {
        return $this->moderate($request, $review, ServiceReview::STATUS_APPROVED, 'Recenze byla schválena.');
    }

    public function reject(Request $request, ServiceReview $review): RedirectResponse
    {
        return $this->moderate($request, $review, ServiceReview::STATUS_REJECTED, 'Recenze byla zamítnuta.');
    }

    private function moderate(Request $request, ServiceReview $review, string $status, string $message): RedirectResponse
    {
        $review->update([
            'status'       => $status,
            'moderated_by' => $request->user()?->id,
            'moderated_at' => now(),
        ]);

        activity('review')
            ->performedOn($review)
            ->causedBy($request->user())
            ->withProperties(['status' => $status, 'rating' => $review->rating])
            ->log('review.moderated');

        return back()->with('status', $message);
    }
}
