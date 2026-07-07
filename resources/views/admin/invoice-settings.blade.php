@extends('layouts.panel')

@section('title', 'Nastavení faktur')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="Nastavení textu faktur">
        <form method="POST" action="{{ route('admin.invoice-settings.update') }}">
            @csrf
            @method('PATCH')

            <div class="mb-3">
                <label class="form-label fw-semibold">Patičkový text faktury</label>
                <textarea name="footer_text" class="form-control" rows="4">{{ old('footer_text', $footer) }}</textarea>
                <div class="form-text">Text zobrazený v patičce každé faktury (bankovní spojení, IBAN, apod.).</div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Obchodní podmínky</label>
                <textarea name="terms_text" class="form-control" rows="6">{{ old('terms_text', $terms) }}</textarea>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">Bankovní informace</label>
                <textarea name="bank_info" class="form-control" rows="3">{{ old('bank_info', $bankInfo) }}</textarea>
            </div>

            <button type="submit" class="btn btn-primary">Uložit nastavení</button>
        </form>
    </x-panel.card>
</div>
@endsection
