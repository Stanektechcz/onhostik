<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LicenseKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LicenseKeyController extends Controller
{
    public function index(): View
    {
        $keys = LicenseKey::with(['customer'])->orderByDesc('created_at')->paginate(25);
        $statuses = ['available', 'assigned', 'expired', 'revoked'];
        return view('admin.license-keys.index', compact('keys', 'statuses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_name' => ['required', 'max:100'],
            'license_key'  => ['required', 'max:255', 'unique:license_keys,license_key'],
            'customer_id'  => ['nullable', 'integer'],
            'status'       => ['required', 'in:available,assigned,expired,revoked'],
            'expires_at'   => ['nullable', 'date'],
            'notes'        => ['nullable', 'max:500'],
        ]);

        LicenseKey::create($validated);

        return back()->with('status', 'Licenční klíč přidán.');
    }

    public function update(Request $request, LicenseKey $licenseKey): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:available,assigned,expired,revoked'],
        ]);

        $licenseKey->update($validated);

        return back()->with('status', 'Stav klíče aktualizován.');
    }
}
