<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SslCertificateCheck;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SslCertificateCheckController extends Controller
{
    public function index(Request $request): View
    {
        $checks = SslCertificateCheck::with(['service'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('checked_at')
            ->paginate(25);

        $statuses = ['valid', 'expiring_soon', 'expired', 'invalid', 'unknown'];

        return view('admin.ssl-certificate-checks.index', compact('checks', 'statuses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
            'domain'     => ['required', 'max:255'],
            'status'     => ['required', 'in:valid,expiring_soon,expired,invalid,unknown'],
            'expires_at' => ['nullable', 'date'],
            'issuer'     => ['nullable', 'max:255'],
            'checked_at' => ['required', 'date'],
            'error'      => ['nullable', 'max:500'],
        ]);

        SslCertificateCheck::create($validated);

        return back()->with('status', 'Záznam SSL kontroly přidán.');
    }
}
