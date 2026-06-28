@extends('layouts.panel')

@php($breadcrumbTitle = $customer->user?->name ?? $customer->email)
@php($breadcrumbItems = [__('panel.nav.admin_customers') => route('admin.customers.index'), ($customer->user?->name ?? $customer->email) => ''])

@section('title', $customer->user?->name ?? $customer->email)

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        {{-- Impersonation banner --}}
        @if($customer->user_id)
        <div class="alert alert-light-warning d-flex align-items-center gap-3 mb-3 py-2">
            <i data-feather="eye" style="width:16px;height:16px;"></i>
            <span class="f-14">Přihlásit se jako tento zákazník a zobrazit jeho panel.</span>
            <a href="{{ route('admin.impersonate.start', $customer->user_id) }}"
               class="btn btn-warning btn-xs ms-auto text-white"
               onclick="return confirm('Přihlásit se za zákazníka {{ addslashes($customer->user?->name) }}?')">
                <i data-feather="log-in" style="width:12px;height:12px;"></i>
                Přihlásit se za zákazníka
            </a>
        </div>
        @endif

        {{-- Partner profile shortcut --}}
        @php($customerPartner = $customer->user ? \App\Domains\Partner\Models\PartnerProfile::where('user_id', $customer->user_id)->first() : null)
        @if($customerPartner)
            <div class="alert alert-light-primary d-flex align-items-center gap-3 mb-3 py-2">
                <i data-feather="share-2" style="width:16px;height:16px;"></i>
                <span class="f-14">Tento zákazník je partner.</span>
                <a href="{{ route('admin.partners.show', $customerPartner) }}" class="btn btn-outline-primary btn-xs ms-auto">
                    Zobrazit partner profil
                </a>
            </div>
        @elseif($customer->user_id)
            <div class="alert alert-light-secondary d-flex align-items-center gap-3 mb-3 py-2">
                <i data-feather="share-2" style="width:16px;height:16px;"></i>
                <span class="f-14 f-light">Zákazník nemá partner profil.</span>
                <a href="{{ route('admin.partners.create') }}?user_id={{ $customer->user_id }}" class="btn btn-outline-secondary btn-xs ms-auto">
                    Vytvořit partner profil
                </a>
            </div>
        @endif

        {{-- KPI row --}}
        <div class="row">
            <div class="col-sm-6 col-xl-3">
                <x-panel.stat-widget
                    :label="__('panel.billing.balance')"
                    :value="\App\Domains\Shared\Support\MoneyFormatter::format($balance)"
                    icon="credit-card" color="primary" />
            </div>
            <div class="col-sm-6 col-xl-3">
                <x-panel.stat-widget
                    :label="__('panel.nav.admin_services')"
                    :value="$services->count()"
                    icon="server" color="success" />
            </div>
            <div class="col-sm-6 col-xl-3">
                <x-panel.stat-widget
                    :label="__('panel.nav.admin_invoices')"
                    :value="$invoices->count()"
                    icon="file-text" color="warning" />
            </div>
            <div class="col-sm-6 col-xl-3">
                <x-panel.stat-widget
                    :label="__('panel.nav.admin_support')"
                    :value="$tickets->count()"
                    icon="life-buoy" color="danger" />
            </div>
        </div>

        <div class="row">
            <div class="col-xl-4">
                {{-- Customer info --}}
                <x-panel.card :title="$customer->user?->name ?? $customer->email" :subtitle="$customer->email">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td class="f-light ps-0">Typ</td>
                            <td class="f-w-600">{{ $customer->type }}</td>
                        </tr>
                        @if($customer->company_name)
                            <tr>
                                <td class="f-light ps-0">Firma</td>
                                <td>{{ $customer->company_name }}</td>
                            </tr>
                        @endif
                        @if($customer->registration_number)
                            <tr>
                                <td class="f-light ps-0">IČ</td>
                                <td>{{ $customer->registration_number }}</td>
                            </tr>
                        @endif
                        @if($customer->vat_number)
                            <tr>
                                <td class="f-light ps-0">DIČ</td>
                                <td>{{ $customer->vat_number }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td class="f-light ps-0">Země / měna</td>
                            <td>{{ $customer->country_code }} · {{ $customer->preferred_currency->value }}</td>
                        </tr>
                        @if($customer->user)
                            <tr>
                                <td class="f-light ps-0">Uživatel</td>
                                <td>{{ $customer->user->name }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td class="f-light ps-0">Registrace</td>
                            <td>{{ $customer->created_at?->format('d.m.Y') }}</td>
                        </tr>
                    </table>
                </x-panel.card>

                {{-- Credit adjustment --}}
                <x-panel.card :title="__('panel.admin.adjust_credit')">
                    <form method="POST" action="{{ route('admin.customers.credit', $customer) }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label f-12 f-light" for="adj-amount">{{ __('panel.admin.adjust_amount') }} (CZK)</label>
                            <input id="adj-amount" type="number" name="amount" step="0.01" class="form-control @error('amount') is-invalid @enderror" required>
                            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label f-12 f-light" for="adj-reason">{{ __('panel.admin.adjust_reason') }}</label>
                            <input id="adj-reason" type="text" name="reason" class="form-control @error('reason') is-invalid @enderror" required minlength="5" maxlength="255">
                            @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('panel.admin.save') }}</button>
                    </form>
                </x-panel.card>

                {{-- Domains --}}
                @if($domains->isNotEmpty())
                    <x-panel.card :title="__('panel.nav.admin_domains')">
                        @foreach($domains as $domain)
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="f-w-600 f-14">{{ $domain->fqdn() }}</span>
                                <span class="f-light f-12">{{ $domain->expires_at?->format('d.m.Y') ?? '—' }}</span>
                            </div>
                        @endforeach
                    </x-panel.card>
                @endif
            </div>

            <div class="col-xl-8">
                {{-- Credit ledger --}}
                <x-panel.card :title="__('panel.billing.history')">
                    <x-panel.data-table :headers="[__('panel.common.date'), __('panel.billing.type'), __('panel.billing.description'), __('panel.billing.amount'), __('panel.billing.balance_after')]">
                        @forelse($ledger as $transaction)
                            <tr>
                                <td class="f-12">{{ $transaction->created_at?->format('d.m.Y H:i') }}</td>
                                <td>{{ $transaction->type->label() }}</td>
                                <td class="f-light">{{ $transaction->description }}</td>
                                <td><x-panel.money :money="$transaction->amount" /></td>
                                <td><x-panel.money :money="$transaction->balance_after" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="f-light">{{ __('panel.common.empty') }}</td></tr>
                        @endforelse
                    </x-panel.data-table>
                </x-panel.card>

                <div class="row">
                    <div class="col-md-6">
                        {{-- Services --}}
                        <x-panel.card :title="__('panel.nav.admin_services')">
                            <x-panel.data-table :headers="[__('panel.services.label'), __('panel.common.status')]">
                                @forelse($services as $service)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.services.show', $service) }}" class="f-w-500">
                                                {{ $service->label ?? ($service->product?->name ?? '—') }}
                                            </a>
                                        </td>
                                        <td><x-panel.status-badge :status="$service->status" /></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="f-light">{{ __('panel.common.empty') }}</td></tr>
                                @endforelse
                            </x-panel.data-table>
                        </x-panel.card>
                    </div>
                    <div class="col-md-6">
                        {{-- Orders --}}
                        <x-panel.card :title="__('panel.nav.admin_orders')">
                            <x-panel.data-table :headers="['#', __('panel.common.status'), __('panel.common.total')]">
                                @forelse($orders as $order)
                                    <tr>
                                        <td><a href="{{ route('admin.orders.show', $order) }}">#{{ $order->id }}</a></td>
                                        <td><x-panel.status-badge :status="$order->status" /></td>
                                        <td><x-panel.money :money="$order->total" /></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="f-light">{{ __('panel.common.empty') }}</td></tr>
                                @endforelse
                            </x-panel.data-table>
                        </x-panel.card>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        {{-- Invoices --}}
                        <x-panel.card :title="__('panel.nav.admin_invoices')">
                            <x-panel.data-table :headers="[__('panel.billing.number'), __('panel.common.status'), __('panel.common.total')]">
                                @forelse($invoices as $invoice)
                                    <tr>
                                        <td><a href="{{ route('admin.invoices.show', $invoice) }}">{{ $invoice->number }}</a></td>
                                        <td><x-panel.status-badge :status="$invoice->status" /></td>
                                        <td><x-panel.money :money="$invoice->total" /></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="f-light">{{ __('panel.common.empty') }}</td></tr>
                                @endforelse
                            </x-panel.data-table>
                        </x-panel.card>
                    </div>
                    <div class="col-md-6">
                        {{-- Support tickets --}}
                        <x-panel.card :title="__('panel.nav.admin_support')">
                            <x-panel.data-table :headers="[__('panel.support.subject'), __('panel.common.status')]">
                                @forelse($tickets as $ticket)
                                    <tr>
                                        <td><a href="{{ route('admin.support.show', $ticket) }}">{{ $ticket->subject }}</a></td>
                                        <td><x-panel.status-badge :status="$ticket->status" /></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="f-light">{{ __('panel.common.empty') }}</td></tr>
                                @endforelse
                            </x-panel.data-table>
                        </x-panel.card>
                    </div>
                </div>

                {{-- Payments --}}
                @if($payments->isNotEmpty())
                    <x-panel.card :title="__('panel.nav.admin_payments')">
                        <x-panel.data-table :headers="[__('panel.billing.method'), __('panel.orders.invoice'), __('panel.common.date'), __('panel.common.status'), __('panel.billing.amount')]">
                            @foreach($payments as $payment)
                                <tr>
                                    <td>{{ $payment->method->label() }}</td>
                                    <td>
                                        @if($payment->invoice)
                                            <a href="{{ route('admin.invoices.show', $payment->invoice) }}">{{ $payment->invoice->number }}</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="f-12">{{ $payment->processed_at?->format('d.m.Y H:i') ?? $payment->created_at?->format('d.m.Y H:i') }}</td>
                                    <td><x-panel.status-badge :status="$payment->status" /></td>
                                    <td><x-panel.money :money="$payment->amount" /></td>
                                </tr>
                            @endforeach
                        </x-panel.data-table>
                    </x-panel.card>
                @endif
            </div>
        </div>
    </div>
@endsection
