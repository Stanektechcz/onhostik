@extends('layouts.panel')

@section('title', 'Převod kreditu')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="row g-4">
        <div class="col-md-5">
            <x-panel.card title="Převod kreditu mezi zákazníky">
                <form method="POST" action="{{ route('admin.credit-transfer.transfer') }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Od zákazníka *</label>
                        <select name="from_customer_id" class="form-select @error('from_customer_id') is-invalid @enderror" required>
                            <option value="">— Vyberte zákazníka —</option>
                            @foreach($customers as $c)
                            <option value="{{ $c->id }}" @selected(old('from_customer_id') == $c->id)>
                                {{ $c->company_name ?? ('Zákazník #' . $c->id) }}
                                ({{ number_format($c->credit_balance / 100, 2) }} Kč)
                            </option>
                            @endforeach
                        </select>
                        @error('from_customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pro zákazníka *</label>
                        <select name="to_customer_id" class="form-select @error('to_customer_id') is-invalid @enderror" required>
                            <option value="">— Vyberte zákazníka —</option>
                            @foreach($customers as $c)
                            <option value="{{ $c->id }}" @selected(old('to_customer_id') == $c->id)>
                                {{ $c->company_name ?? ('Zákazník #' . $c->id) }}
                            </option>
                            @endforeach
                        </select>
                        @error('to_customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Částka (haléře) *</label>
                        <input type="number" name="amount_haler" class="form-control @error('amount_haler') is-invalid @enderror" min="100" value="{{ old('amount_haler') }}" required>
                        <div class="form-text">100 haléřů = 1 Kč</div>
                        @error('amount_haler')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Poznámka</label>
                        <input type="text" name="note" class="form-control" maxlength="500" value="{{ old('note') }}">
                    </div>

                    <button type="submit" class="btn btn-warning w-100">Převést kredit</button>
                </form>
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
