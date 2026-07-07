@extends('layouts.front')

@section('title', 'Platební metody')

@section('content')
<div class="container py-5">
    <h2 class="mb-4">Moje platební metody</h2>

    @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-md-8">
            @forelse($methods as $method)
            <div class="card mb-3 @if($method->is_default) border-primary @endif">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">{{ $method->label }}</div>
                        <small class="text-muted">
                            {{ $method->provider }}
                            @if($method->last4) · **** {{ $method->last4 }} @endif
                            @if($method->card_brand) · {{ $method->card_brand }} @endif
                            @if($method->expires_at) · exp. {{ $method->expires_at }} @endif
                        </small>
                        @if($method->is_default)
                        <span class="badge bg-primary ms-2">Výchozí</span>
                        @endif
                    </div>
                    <div class="d-flex gap-2">
                        @if(!$method->is_default)
                        <form method="POST" action="{{ route('panel.payment-methods.default', $method) }}">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm btn-outline-primary">Nastavit jako výchozí</button>
                        </form>
                        @endif
                        <form method="POST" action="{{ route('panel.payment-methods.destroy', $method) }}">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Odstranit</button>
                        </form>
                    </div>
                </div>
            </div>
            @empty
            <div class="alert alert-info">Zatím nemáte uložené žádné platební metody.</div>
            @endforelse
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Přidat platební metodu</h5>
                    <form method="POST" action="{{ route('panel.payment-methods.store') }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label">Popis</label>
                            <input type="text" name="label" class="form-control @error('label') is-invalid @enderror" maxlength="100" required>
                            @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Poskytovatel</label>
                            <select name="provider" class="form-select">
                                <option value="comgate">Comgate</option>
                                <option value="bank_transfer">Bankovní převod</option>
                                <option value="other">Jiné</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Poslední 4 číslice</label>
                            <input type="text" name="last4" class="form-control" maxlength="4" pattern="\d{4}">
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_default" class="form-check-input" id="is_default" value="1">
                            <label class="form-check-label" for="is_default">Nastavit jako výchozí</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Přidat</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
