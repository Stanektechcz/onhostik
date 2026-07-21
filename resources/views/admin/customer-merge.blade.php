@extends('layouts.panel')

@section('title', 'Sloučit zákazníka')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="Sloučit zákazníky do: {{ $customer->display_name }}">
        <div class="alert alert-warning">
            <strong>Pozor:</strong> Sloučení přesune faktury, služby, tickety a objednávky ze zdrojového zákazníka do tohoto zákazníka a zdrojový zákazník bude <strong>smazán</strong>. Tuto akci nelze vrátit.
        </div>

        <form method="POST" action="{{ route('admin.customers.merge', $customer) }}"
              data-confirm="Skutečně sloučit zákazníky? Tato akce je nevratná.">
            @csrf
            <div class="mb-3">
                <label class="form-label">ID zdrojového zákazníka (bude smazán)</label>
                <input type="number" name="source_customer_id" class="form-control" min="1" required>
                @error('source_customer_id')<div class="text-danger f-12">{{ $message }}</div>@enderror
                <div class="form-text">Zákazník s tímto ID bude smazán, jeho data přejdou na <strong>{{ $customer->display_name }}</strong>.</div>
            </div>
            <button type="submit" class="btn btn-danger">Sloučit zákazníky</button>
            <a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-outline-secondary ms-2">Zrušit</a>
        </form>
    </x-panel.card>
</div>
@endsection
