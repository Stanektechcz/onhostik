@extends('layouts.panel')

@php
    $breadcrumbTitle = $customer->email;
    $breadcrumbItems = [
        'Reseller'    => route('reseller.dashboard'),
        'Zákazníci'   => route('reseller.customers.index'),
        $customer->email => '',
    ];
@endphp

@section('title', 'Zákazník — ' . $customer->email)

@section('content')
<div class="container-fluid">
    <div class="grid grid-cols-12 card-gap">

        {{-- Left: customer info --}}
        <div class="col-span-4 xl:col-span-12">
            <x-panel.card title="Zákazník">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item flex justify-between px-0">
                        <span class="f-light">Email</span>
                        <strong>{{ $customer->email }}</strong>
                    </li>
                    @if($customer->company_name)
                    <li class="list-group-item flex justify-between px-0">
                        <span class="f-light">Firma</span>
                        <strong>{{ $customer->company_name }}</strong>
                    </li>
                    @endif
                    @if($customer->phone)
                    <li class="list-group-item flex justify-between px-0">
                        <span class="f-light">Telefon</span>
                        <strong>{{ $customer->phone }}</strong>
                    </li>
                    @endif
                    <li class="list-group-item flex justify-between px-0">
                        <span class="f-light">Typ</span>
                        <span class="badge badge-light-secondary">{{ $customer->type }}</span>
                    </li>
                    <li class="list-group-item flex justify-between px-0">
                        <span class="f-light">Země</span>
                        <strong>{{ $customer->country_code }}</strong>
                    </li>
                    <li class="list-group-item flex justify-between px-0">
                        <span class="f-light">Registrace</span>
                        <strong>{{ $customer->created_at?->format('d. m. Y') }}</strong>
                    </li>
                </ul>
            </x-panel.card>
        </div>

        {{-- Right: services --}}
        <div class="col-span-8 xl:col-span-12">
            <x-panel.card title="Služby">
                @if($customer->services->isEmpty())
                    <p class="f-light mb-0">Zákazník nemá žádné aktivní služby.</p>
                @else
                <div class="table-responsive">
                    <table class="table table-borderless">
                        <thead>
                            <tr>
                                <th>Služba</th>
                                <th>{{ __('panel.common.status') }}</th>
                                <th>Příští platba</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($customer->services as $service)
                            <tr>
                                <td>
                                    <div class="f-w-500">{{ $service->label }}</div>
                                    <div class="f-12 f-light">{{ $service->provisioning_driver->value ?? '' }}</div>
                                </td>
                                <td>
                                    <span class="badge badge-light-{{ match($service->status->value ?? '') {
                                        'active' => 'success',
                                        'suspended' => 'warning',
                                        'terminated' => 'danger',
                                        default => 'secondary'
                                    } }}">
                                        {{ $service->status->label() ?? $service->status->value }}
                                    </span>
                                </td>
                                <td class="f-12 f-light">
                                    {{ $service->next_due_date?->format('d. m. Y') ?? '—' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </x-panel.card>

            {{-- Orders --}}
            <x-panel.card title="Objednávky" class="mt-3">
                @if($customer->orders->isEmpty())
                    <p class="f-light mb-0">Žádné objednávky.</p>
                @else
                <div class="table-responsive">
                    <table class="table table-borderless">
                        <thead>
                            <tr>
                                <th>Číslo</th>
                                <th>{{ __('panel.common.status') }}</th>
                                <th>Datum</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($customer->orders->take(10) as $order)
                            <tr>
                                <td class="f-w-500">#{{ $order->id }}</td>
                                <td>
                                    <span class="badge badge-light-secondary">{{ $order->status }}</span>
                                </td>
                                <td class="f-12 f-light">{{ $order->created_at?->format('d. m. Y') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
