@extends('layouts.panel')

@php($breadcrumbTitle = 'Hromadné operace')
@php($breadcrumbItems = ['Hromadné operace' => ''])

@section('title', 'Hromadné operace')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    @if(session('status'))
        <div class="alert alert-light-success py-2 mb-3">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-12 card-gap">

        {{-- Services --}}
        <div class="col-span-6 md:col-span-12">
            <x-panel.card title="Služby">
                <x-slot name="headerRight">
                    <span class="badge badge-light-primary f-11">{{ number_format($serviceCount) }} celkem</span>
                </x-slot>

                <p class="f-light f-12 mb-3">Zadejte ID oddělená čárkami. Max: 200 (prodloužení/auto-obnova), 100 (pozastavit/obnovit/ukončit).</p>

                {{-- Extend due date --}}
                <form action="{{ route('admin.bulk.service-extend') }}" method="POST" class="mb-3 js-bulk-form">
                    @csrf
                    <div class="f-12 f-w-600 mb-1">Prodloužit splatnost</div>
                    <div class="d-flex gap-2">
                        <input type="text" name="_ids" class="form-control form-control-sm flex-1" placeholder="ID: 1,2,3" required>
                        <input type="number" name="days" class="form-control form-control-sm" style="width:80px" placeholder="Dní" min="1" max="365" required>
                        <button class="btn btn-outline-primary btn-sm text-nowrap">Prodloužit</button>
                    </div>
                </form>

                {{-- Suspend --}}
                <form action="{{ route('admin.bulk.service-suspend') }}" method="POST" class="mb-3 js-bulk-form">
                    @csrf
                    <div class="f-12 f-w-600 mb-1">Pozastavit aktivní služby</div>
                    <div class="d-flex gap-2">
                        <input type="text" name="_ids" class="form-control form-control-sm flex-1" placeholder="ID: 1,2,3" required>
                        <input type="text" name="reason" class="form-control form-control-sm flex-1" placeholder="Důvod" required>
                        <button class="btn btn-outline-warning btn-sm text-nowrap">Pozastavit</button>
                    </div>
                </form>

                {{-- Resume --}}
                <form action="{{ route('admin.bulk.service-resume') }}" method="POST" class="mb-3 js-bulk-form">
                    @csrf
                    <div class="f-12 f-w-600 mb-1">Obnovit pozastavené služby</div>
                    <div class="d-flex gap-2">
                        <input type="text" name="_ids" class="form-control form-control-sm flex-1" placeholder="ID: 1,2,3" required>
                        <button class="btn btn-outline-success btn-sm text-nowrap">Obnovit</button>
                    </div>
                </form>

                {{-- Auto-renew enable --}}
                <form action="{{ route('admin.bulk.service-auto-renew') }}" method="POST" class="mb-3 js-bulk-form">
                    @csrf
                    <input type="hidden" name="auto_renew" value="1">
                    <div class="f-12 f-w-600 mb-1">Zapnout automatickou obnovu</div>
                    <div class="d-flex gap-2">
                        <input type="text" name="_ids" class="form-control form-control-sm flex-1" placeholder="ID: 1,2,3" required>
                        <button class="btn btn-outline-success btn-sm text-nowrap">Zapnout</button>
                    </div>
                </form>

                {{-- Auto-renew disable --}}
                <form action="{{ route('admin.bulk.service-auto-renew') }}" method="POST" class="mb-3 js-bulk-form">
                    @csrf
                    <input type="hidden" name="auto_renew" value="0">
                    <div class="f-12 f-w-600 mb-1">Vypnout automatickou obnovu</div>
                    <div class="d-flex gap-2">
                        <input type="text" name="_ids" class="form-control form-control-sm flex-1" placeholder="ID: 1,2,3" required>
                        <button class="btn btn-outline-secondary btn-sm text-nowrap">Vypnout</button>
                    </div>
                </form>

                {{-- Terminate --}}
                <form action="{{ route('admin.bulk.service-terminate') }}" method="POST" class="mb-1 js-bulk-form">
                    @csrf
                    <div class="f-12 f-w-600 mb-1 txt-danger">Ukončit služby (nevratné)</div>
                    <div class="d-flex gap-2">
                        <input type="text" name="_ids" class="form-control form-control-sm flex-1" placeholder="ID: 1,2,3" required>
                        <input type="text" name="reason" class="form-control form-control-sm flex-1" placeholder="Důvod" required>
                        <button class="btn btn-outline-danger btn-sm text-nowrap"
                                onclick="return confirm('Opravdu ukončit vybrané služby?')">Ukončit</button>
                    </div>
                </form>
            </x-panel.card>
        </div>

        {{-- Invoices + Customers --}}
        <div class="col-span-6 md:col-span-12">
            <x-panel.card title="Faktury">
                <x-slot name="headerRight">
                    <span class="badge badge-light-warning f-11">{{ number_format($invoiceCount) }} celkem</span>
                </x-slot>

                <p class="f-light f-12 mb-3">Storno funguje pouze pro faktury ve stavu draft nebo sent.</p>

                <form action="{{ route('admin.bulk.invoice-void') }}" method="POST" class="mb-3 js-bulk-form">
                    @csrf
                    <div class="f-12 f-w-600 mb-1">Stornovat faktury</div>
                    <div class="d-flex gap-2">
                        <input type="text" name="_ids" class="form-control form-control-sm flex-1" placeholder="ID: 1,2,3" required>
                        <button class="btn btn-outline-warning btn-sm text-nowrap">Stornovat</button>
                    </div>
                </form>

                <hr class="my-3">
                <a href="{{ route('admin.invoices.index') }}" class="btn btn-outline-secondary btn-sm">
                    Hromadné platby → seznam faktur
                </a>
            </x-panel.card>

            <x-panel.card title="Zákazníci" class="mt-3">
                <x-slot name="headerRight">
                    <span class="badge badge-light-info f-11">{{ number_format($customerCount) }} celkem</span>
                </x-slot>

                <form action="{{ route('admin.bulk.customer-export') }}" method="POST" class="js-bulk-form">
                    @csrf
                    <div class="f-12 f-w-600 mb-1">Export zákazníků (CSV)</div>
                    <div class="d-flex gap-2">
                        <input type="text" name="_ids" class="form-control form-control-sm flex-1" placeholder="ID: 1,2,3" required>
                        <button class="btn btn-outline-info btn-sm text-nowrap">Stáhnout CSV</button>
                    </div>
                </form>
            </x-panel.card>
        </div>

    </div>

    <div class="f-light f-11 mt-2">
        Hromadné operace jako ukončení nebo storno jsou nevratné. Vždy ověřte IDs před odesláním.
    </div>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
document.querySelectorAll('.js-bulk-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        const idInput = form.querySelector('input[name="_ids"]');
        if (!idInput) return;

        e.preventDefault();
        const ids = idInput.value.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
        idInput.remove();

        ids.forEach(function(id) {
            const h = document.createElement('input');
            h.type = 'hidden';
            h.name = 'ids[]';
            h.value = id;
            form.appendChild(h);
        });

        form.submit();
    });
});
</script>
@endsection
