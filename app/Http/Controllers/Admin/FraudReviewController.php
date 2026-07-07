<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FraudReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FraudReviewController extends Controller
{
    public function index(): View
    {
        $pending = FraudReview::with('customer', 'order')
            ->where('status', 'pending')
            ->orderByDesc('score')
            ->orderByDesc('created_at')
            ->paginate(25);

        $stats = [
            'pending' => FraudReview::where('status', 'pending')->count(),
            'cleared' => FraudReview::where('status', 'cleared')->count(),
            'blocked' => FraudReview::where('status', 'blocked')->count(),
        ];

        return view('admin.fraud-reviews.index', compact('pending', 'stats'));
    }

    public function update(Request $request, FraudReview $review): RedirectResponse
    {
        $validated = $request->validate([
            'status'        => ['required', 'in:cleared,blocked'],
            'reviewer_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $review->update([
            'status'        => $validated['status'],
            'reviewer_note' => $validated['reviewer_note'] ?? null,
            'reviewed_by'   => $request->user()?->id,
            'reviewed_at'   => now(),
        ]);

        return back()->with('status', $validated['status'] === 'cleared' ? 'Objednávka schválena.' : 'Objednávka zablokována.');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'order_id'    => ['nullable', 'integer', 'exists:orders,id'],
            'score'       => ['required', 'integer', 'min:0', 'max:100'],
            'signals'     => ['required', 'array', 'min:1'],
            'signals.*'   => ['string', 'max:50'],
        ]);

        FraudReview::create($validated);

        return back()->with('status', 'Podezření zaznamenáno.');
    }
}
