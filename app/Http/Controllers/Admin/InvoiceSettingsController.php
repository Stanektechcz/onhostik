<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvoiceSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceSettingsController extends Controller
{
    public function show(): View
    {
        $footer    = InvoiceSetting::get('footer_text');
        $terms     = InvoiceSetting::get('terms_text');
        $bankInfo  = InvoiceSetting::get('bank_info');

        return view('admin.invoice-settings', compact('footer', 'terms', 'bankInfo'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'footer_text' => ['nullable', 'string', 'max:500'],
            'terms_text'  => ['nullable', 'string', 'max:2000'],
            'bank_info'   => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($validated as $key => $value) {
            InvoiceSetting::set($key, (string) $value);
        }

        return back()->with('status', 'Nastavení faktur uloženo.');
    }
}
