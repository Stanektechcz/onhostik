<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PriceChangeNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PriceChangeNotificationController extends Controller
{
    public function index(): View
    {
        $notifications = PriceChangeNotification::orderByDesc('created_at')->paginate(20);
        return view('admin.price-change-notifications.index', compact('notifications'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'          => ['required', 'max:150'],
            'body'           => ['required', 'string'],
            'effective_from' => ['required', 'date'],
            'status'         => ['required', 'in:draft,scheduled,sent'],
        ]);

        PriceChangeNotification::create([...$validated, 'created_by' => $request->user()->id]);

        return back()->with('status', 'Oznámení vytvořeno.');
    }

    public function destroy(PriceChangeNotification $priceChangeNotification): RedirectResponse
    {
        $priceChangeNotification->delete();
        return back()->with('status', 'Oznámení smazáno.');
    }
}
