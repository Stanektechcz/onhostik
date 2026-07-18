@extends('layouts.panel')

@section('title', 'Nová email kampaň')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="mb-3">
        <a href="{{ route('admin.bulk-email-campaigns.index') }}" class="btn btn-sm btn-outline-secondary">&larr; Zpět</a>
    </div>

    <x-panel.card title="Vytvořit email kampaň">
        <form method="POST" action="{{ route('admin.bulk-email-campaigns.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label font-semibold">Předmět *</label>
                <input type="text" name="subject" class="form-control @error('subject') is-invalid @enderror" value="{{ old('subject') }}" maxlength="255" required>
                @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label font-semibold">Obsah (HTML) *</label>
                <textarea name="body_html" class="form-control font-monospace @error('body_html') is-invalid @enderror" rows="12">{{ old('body_html') }}</textarea>
                @error('body_html')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="grid grid-cols-12 gap-3 mb-3">
                <div class="col-span-12 md:col-span-4">
                    <label class="form-label">Segment zákazníků</label>
                    <select name="target_segment" class="form-select">
                        <option value="">Všichni</option>
                        <option value="starter">Starter</option>
                        <option value="growth">Growth</option>
                        <option value="enterprise">Enterprise</option>
                        <option value="at_risk">At Risk</option>
                        <option value="churned">Churned</option>
                    </select>
                </div>
                <div class="col-span-12 md:col-span-4">
                    <label class="form-label">Kód země (ISO 2)</label>
                    <input type="text" name="target_country" class="form-control" maxlength="2" placeholder="CZ">
                </div>
                <div class="col-span-12 md:col-span-4">
                    <label class="form-label">Naplánovat odeslání</label>
                    <input type="datetime-local" name="scheduled_at" class="form-control">
                </div>
            </div>

            <div class="alert alert-warning">
                <strong>Pozor:</strong> Kampaň bude vytvořena jako koncept. Odeslání se provede ručně nebo dle plánu.
            </div>

            <button type="submit" class="btn btn-primary">Vytvořit kampaň</button>
        </form>
    </x-panel.card>
</div>
@endsection
