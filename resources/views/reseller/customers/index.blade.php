@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Moji zákazníci';
    $breadcrumbItems = ['Reseller' => route('reseller.dashboard'), 'Zákazníci' => ''];
@endphp

@section('title', 'Moji zákazníci')

@section('content')
<div class="container-fluid">

    @if(!$profile)
        <div class="card">
            <div class="card-body text-center py-5">
                <i data-feather="users" style="width:48px;height:48px;" class="text-muted mb-3"></i>
                <h5>Reseller profil není aktivní</h5>
                <a href="{{ route('reseller.dashboard') }}" class="btn btn-outline-primary btn-sm mt-2">
                    Zpět na dashboard
                </a>
            </div>
        </div>
        @php return; @endphp
    @endif

    {{-- Stats bar --}}
    <div class="grid grid-cols-12 card-gap">
        <div class="col-span-6 sm:col-span-12 md:col-span-4">
            <div class="card small-widget">
                <div class="card-body primary">
                    <span class="f-light">Celkem zákazníků</span>
                    <div class="d-flex align-items-end gap-1">
                        <h4>{{ $customers instanceof \Illuminate\Pagination\LengthAwarePaginator ? $customers->total() : $customers->count() }}</h4>
                    </div>
                    <div class="bg-gradient"><i data-feather="users"></i></div>
                </div>
            </div>
        </div>
        <div class="col-span-6 sm:col-span-12 md:col-span-4">
            <div class="card small-widget">
                <div class="card-body success">
                    <span class="f-light">Markup</span>
                    <div class="d-flex align-items-end gap-1">
                        <h4>{{ number_format($profile->markup_percent, 1) }} %</h4>
                    </div>
                    <div class="bg-gradient"><i data-feather="percent"></i></div>
                </div>
            </div>
        </div>
        <div class="col-span-6 sm:col-span-12 md:col-span-4">
            <div class="card small-widget">
                <div class="card-body secondary">
                    <span class="f-light">Obchodní jméno</span>
                    <div class="d-flex align-items-end gap-1">
                        <h4 class="text-truncate" style="max-width:160px;font-size:1.1rem;">{{ $profile->business_name }}</h4>
                    </div>
                    <div class="bg-gradient"><i data-feather="briefcase"></i></div>
                </div>
            </div>
        </div>
    </div>

    <x-panel.card title="Zákazníci">
        @if($customers->isEmpty())
            <div class="text-center py-5">
                <i data-feather="user-plus" style="width:48px;height:48px;" class="text-muted mb-3"></i>
                <h6>Zatím žádní zákazníci</h6>
                <p class="f-light f-13 mb-0">
                    Zákazníci se přiřadí automaticky, když se zaregistrují přes váš referral odkaz nebo vlastní doménu.
                </p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-borderless">
                    <thead>
                        <tr>
                            <th>Zákazník</th>
                            <th>Registrace</th>
                            <th class="text-center">Služby</th>
                            <th class="text-center">Objednávky</th>
                            <th class="text-center">Faktury</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($customers as $customer)
                        <tr>
                            <td>
                                <div class="f-w-500">{{ $customer->email }}</div>
                                @if($customer->company_name)
                                    <div class="f-12 f-light">{{ $customer->company_name }}</div>
                                @endif
                                @if($customer->user)
                                    <div class="f-12 f-light">
                                        {{ $customer->user->name }}
                                        @if($customer->user->email_verified_at)
                                            <i data-feather="check-circle" style="width:11px;height:11px;color:var(--theme-green);" title="Ověřený email"></i>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="f-12 f-light">{{ $customer->created_at?->format('d. m. Y') }}</td>
                            <td class="text-center">
                                <span class="badge badge-light-primary">{{ $customer->services_count }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-light-secondary">{{ $customer->orders_count }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-light-secondary">{{ $customer->invoices_count }}</span>
                            </td>
                            <td>
                                <a href="{{ route('reseller.customers.show', $customer) }}" class="btn btn-sm btn-outline-primary">
                                    <i data-feather="eye" style="width:13px;height:13px;"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $customers->links() }}
        @endif
    </x-panel.card>

</div>
@endsection
