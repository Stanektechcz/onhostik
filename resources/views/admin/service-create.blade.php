@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Nová služba';
    $breadcrumbItems = ['Služby' => route('admin.services.index'), 'Nová služba' => ''];
@endphp

@section('title', 'Nová služba | OnHost')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">
        <div class="col-span-8 xl:col-span-12">
            <x-panel.card title="Vytvořit službu pro zákazníka">
                <form method="POST" action="{{ route('admin.services.store') }}">
                    @csrf

                    <div class="grid grid-cols-12 gap-3">
                        <div class="col-span-12">
                            <label class="form-label">Zákazník</label>
                            <select name="customer_id" class="form-select" required>
                                <option value="">— vyberte zákazníka —</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}" @selected($preselectedCustomer === $c->id)>
                                        {{ $c->company_name ?? $c->user?->name ?? ('#' . $c->id) }} — {{ $c->email }}
                                    </option>
                                @endforeach
                            </select>
                            @error('customer_id')<div class="text-danger f-12 mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-span-12">
                            <label class="form-label">Tarif</label>
                            <select name="pricing_plan_id" class="form-select" required>
                                <option value="">— vyberte tarif —</option>
                                @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}">
                                        {{ $plan->product?->name }} — {{ $plan->name }} ({{ $plan->billing_cycle->label() }})
                                    </option>
                                @endforeach
                            </select>
                            @error('pricing_plan_id')<div class="text-danger f-12 mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-span-6 sm:col-span-12">
                            <label class="form-label">Server (nepovinné)</label>
                            <select name="server_id" class="form-select">
                                <option value="">— automaticky / nepřiřazeno —</option>
                                @foreach($servers as $server)
                                    <option value="{{ $server->id }}">{{ $server->name }} ({{ $server->driver }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-span-6 sm:col-span-12">
                            <label class="form-label">Stav</label>
                            <select name="status" class="form-select">
                                <option value="pending">Čeká na zřízení</option>
                                <option value="active">Aktivní</option>
                            </select>
                        </div>

                        <div class="col-span-12">
                            <label class="form-label">Název / doména</label>
                            <input type="text" name="label" class="form-control" value="{{ old('label') }}"
                                   placeholder="mujweb.cz nebo hostname" required>
                            @error('label')<div class="text-danger f-12 mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-span-12">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="provision" value="1" class="form-check-input">
                                <span>Ihned zřídit na backendu (aaPanel / Proxmox / Pterodactyl)</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary text-white">
                            <i data-feather="plus-circle" style="width:14px;height:14px;"></i> Vytvořit službu
                        </button>
                        <a href="{{ route('admin.services.index') }}" class="btn btn-outline-secondary">Zrušit</a>
                    </div>
                </form>
            </x-panel.card>
        </div>

        <div class="col-span-4 xl:col-span-12">
            <x-panel.card title="Nápověda">
                <p class="f-light f-12 mb-0">
                    Služba se vytvoří přímo, bez objednávky. Zvolte „Ihned zřídit“ pro okamžité založení na
                    backendu, nebo ponechte ve stavu „Čeká na zřízení“ a zřiďte později z detailu služby.
                </p>
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
