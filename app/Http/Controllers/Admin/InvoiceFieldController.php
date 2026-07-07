<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\InvoiceCustomFieldValue;
use App\Domains\Billing\Models\InvoiceFieldDefinition;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InvoiceFieldController extends Controller
{
    public function index(): View
    {
        $definitions = InvoiceFieldDefinition::orderBy('sort_order')->orderBy('label')->get();

        return view('admin.invoice-fields.index', compact('definitions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label'           => ['required', 'string', 'max:120'],
            'type'            => ['required', 'in:' . implode(',', array_keys(InvoiceFieldDefinition::TYPES))],
            'is_required'     => ['boolean'],
            'show_on_invoice' => ['boolean'],
            'sort_order'      => ['nullable', 'integer', 'min:0'],
        ]);

        $key = Str::snake(Str::ascii($data['label']));
        $key = preg_replace('/[^a-z0-9_]/', '_', strtolower($key)) ?? $key;

        // Ensure unique key
        $base = $key;
        $n    = 1;
        while (InvoiceFieldDefinition::where('key', $key)->exists()) {
            $key = $base . '_' . $n++;
        }

        InvoiceFieldDefinition::create([
            'key'             => $key,
            'label'           => $data['label'],
            'type'            => $data['type'],
            'is_required'     => !empty($data['is_required']),
            'show_on_invoice' => $data['show_on_invoice'] ?? true,
            'sort_order'      => $data['sort_order'] ?? 0,
            'is_active'       => true,
        ]);

        return redirect()->route('admin.invoice-fields.index')
            ->with('success', 'Vlastní pole bylo vytvořeno.');
    }

    public function update(Request $request, InvoiceFieldDefinition $invoiceField): RedirectResponse
    {
        $data = $request->validate([
            'label'           => ['required', 'string', 'max:120'],
            'type'            => ['required', 'in:' . implode(',', array_keys(InvoiceFieldDefinition::TYPES))],
            'is_required'     => ['boolean'],
            'show_on_invoice' => ['boolean'],
            'sort_order'      => ['nullable', 'integer', 'min:0'],
            'is_active'       => ['boolean'],
        ]);

        $invoiceField->update([
            'label'           => $data['label'],
            'type'            => $data['type'],
            'is_required'     => !empty($data['is_required']),
            'show_on_invoice' => !empty($data['show_on_invoice']),
            'sort_order'      => $data['sort_order'] ?? 0,
            'is_active'       => !empty($data['is_active']),
        ]);

        return redirect()->route('admin.invoice-fields.index')
            ->with('success', 'Vlastní pole bylo aktualizováno.');
    }

    public function destroy(InvoiceFieldDefinition $invoiceField): RedirectResponse
    {
        $invoiceField->delete();

        return redirect()->route('admin.invoice-fields.index')
            ->with('success', 'Vlastní pole bylo smazáno.');
    }

    /**
     * Save custom field values for an invoice.
     * Called from invoice create/edit forms.
     */
    public function saveValues(Request $request, Invoice $invoice): RedirectResponse
    {
        $definitions = InvoiceFieldDefinition::where('is_active', true)->get();

        $rules = [];
        foreach ($definitions as $def) {
            $rules["fields.{$def->id}"] = $def->is_required ? ['required', 'string'] : ['nullable', 'string'];
        }

        $validated = $request->validate($rules);
        $fields    = $validated['fields'] ?? [];

        foreach ($definitions as $def) {
            $value = $fields[$def->id] ?? null;

            InvoiceCustomFieldValue::updateOrCreate(
                ['invoice_id' => $invoice->id, 'invoice_field_definition_id' => $def->id],
                ['value' => $value]
            );
        }

        return back()->with('success', 'Vlastní pole faktury byla uložena.');
    }
}
