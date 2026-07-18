@extends('layouts.panel')

@php($breadcrumbTitle = $customer->user?->name ?? $customer->email)
@php($breadcrumbItems = [__('panel.nav.admin_customers') => route('admin.customers.index'), ($customer->user?->name ?? $customer->email) => ''])

@section('title', $customer->user?->name ?? $customer->email)

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        {{-- Impersonation banner --}}
        @if($customer->user_id)
        <div class="alert alert-light-warning flex items-center gap-3 mb-3 py-2">
            <i data-feather="eye" style="width:16px;height:16px;"></i>
            <span class="f-14">Přihlásit se jako tento zákazník a zobrazit jeho panel.</span>
            <a href="{{ route('admin.customer-login-history.show', $customer) }}"
               class="btn btn-outline-secondary btn-xs ms-auto">
                <i data-feather="clock" style="width:12px;height:12px;"></i>
                Historie přihlášení
            </a>
            <a href="{{ route('admin.impersonate.start', $customer->user_id) }}"
               class="btn btn-warning btn-xs text-white"
               onclick="return confirm('Přihlásit se za zákazníka {{ addslashes($customer->user?->name) }}?')">
                <i data-feather="log-in" style="width:12px;height:12px;"></i>
                Přihlásit se za zákazníka
            </a>
        </div>
        @endif

        {{-- Newsletter subscription shortcut --}}
        @php($subscriberExists = $customer->user ? \App\Models\Subscriber::where('email', $customer->email)->exists() : false)
        @if($customer->user && !$subscriberExists)
            <div class="alert alert-light-info flex items-center gap-3 mb-3 py-2">
                <i data-feather="mail" style="width:16px;height:16px;"></i>
                <span class="f-14 f-light">Zákazník není přihlášen k odběru newsletteru.</span>
                <form method="POST" action="{{ route('admin.subscribers.store') }}" class="ms-auto">
                    @csrf
                    <input type="hidden" name="email" value="{{ $customer->email }}">
                    <input type="hidden" name="name" value="{{ $customer->user?->name }}">
                    <button type="submit" class="btn btn-outline-info btn-xs">
                        <i data-feather="plus" style="width:11px;height:11px;"></i> Přidat k newsletteru
                    </button>
                </form>
            </div>
        @endif

        {{-- Partner profile shortcut --}}
        @php($customerPartner = $customer->user ? \App\Domains\Partner\Models\PartnerProfile::where('user_id', $customer->user_id)->first() : null)
        @if($customerPartner)
            <div class="alert alert-light-primary flex items-center gap-3 mb-3 py-2">
                <i data-feather="share-2" style="width:16px;height:16px;"></i>
                <span class="f-14">Tento zákazník je partner.</span>
                <a href="{{ route('admin.partners.show', $customerPartner) }}" class="btn btn-outline-primary btn-xs ms-auto">
                    Zobrazit partner profil
                </a>
            </div>
        @elseif($customer->user_id)
            <div class="alert alert-light-secondary flex items-center gap-3 mb-3 py-2">
                <i data-feather="share-2" style="width:16px;height:16px;"></i>
                <span class="f-14 f-light">Zákazník nemá partner profil.</span>
                <a href="{{ route('admin.partners.create') }}?user_id={{ $customer->user_id }}" class="btn btn-outline-secondary btn-xs ms-auto">
                    Vytvořit partner profil
                </a>
            </div>
        @endif

        {{-- KPI grid grid-cols-12 --}}
        <div class="grid grid-cols-12 card-gap">
            <div class="col-span-6 sm:col-span-12 md:col-span-3 lg:col-span-2">
                <x-panel.stat-widget
                    :label="__('panel.billing.balance')"
                    :value="\App\Domains\Shared\Support\MoneyFormatter::format($balance)"
                    icon="credit-card" color="primary" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3 lg:col-span-2">
                <x-panel.stat-widget
                    :label="__('panel.nav.admin_services')"
                    :value="$services->count()"
                    icon="server" color="success" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3 lg:col-span-2">
                <x-panel.stat-widget
                    :label="__('panel.nav.admin_invoices')"
                    :value="$invoices->count()"
                    icon="file-text" color="warning" />
            </div>
            <div class="col-span-6 sm:col-span-12 md:col-span-3 lg:col-span-2">
                <x-panel.stat-widget
                    :label="__('panel.nav.admin_support')"
                    :value="$tickets->count()"
                    icon="life-buoy" color="danger" />
            </div>
            @if($customer->health_score !== null)
            <div class="col-span-6 sm:col-span-12 md:col-span-3 lg:col-span-2">
                <x-panel.stat-widget
                    label="Zdraví zákazníka"
                    :value="$customer->health_score . '/100'"
                    icon="heart"
                    :color="$customer->health_score >= 80 ? 'success' : ($customer->health_score >= 50 ? 'warning' : 'danger')" />
            </div>
            @endif
        </div>

        <div class="grid grid-cols-12 card-gap">
            <div class="col-span-4 xl:col-span-12">
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
                        {{-- Phase 273: live-computed health score (works even before the nightly job stores one) --}}
                        <tr>
                            <td class="f-light ps-0">Zdraví</td>
                            <td>
                                <span class="badge badge-light-{{ $healthScoreColor }}">{{ $healthScoreLabel }}</span>
                                <span class="f-light f-12 ms-1">{{ $healthScore }}/100</span>
                                <div class="progress mt-1" style="height:4px;">
                                    <div class="progress-bar bg-{{ $healthScoreColor }}" style="width:{{ $healthScore }}%"></div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </x-panel.card>

                {{-- Quick actions --}}
                <x-panel.card title="Akce">
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('admin.services.create', ['customer' => $customer->id]) }}" class="btn btn-primary btn-sm text-white">
                            <i data-feather="server" style="width:13px;height:13px;"></i> Vytvořit službu
                        </a>
                        <a href="{{ route('admin.orders.index', ['q' => $customer->email]) }}" class="btn btn-outline-primary btn-sm">
                            <i data-feather="shopping-bag" style="width:13px;height:13px;"></i> Objednávky
                        </a>
                        @if($customer->user)
                            <form method="POST" action="{{ route('admin.customers.toggle-active', $customer) }}"
                                  onsubmit="return confirm('Změnit stav přihlášení zákazníka?');">
                                @csrf
                                <button type="submit" class="btn btn-sm {{ $customer->user->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                    <i data-feather="{{ $customer->user->is_active ? 'user-x' : 'user-check' }}" style="width:13px;height:13px;"></i>
                                    {{ $customer->user->is_active ? 'Deaktivovat účet' : 'Aktivovat účet' }}
                                </button>
                            </form>
                        @endif
                    </div>
                </x-panel.card>

                {{-- Edit profile --}}
                <x-panel.card title="Upravit údaje">
                    <form method="POST" action="{{ route('admin.customers.update', $customer) }}">
                        @csrf
                        @method('PATCH')
                        <div class="grid grid-cols-12 gap-2">
                            <div class="col-span-12">
                                <label class="form-label f-12 f-light">Jméno kontaktu</label>
                                <input type="text" name="name" class="form-control form-control-sm" value="{{ old('name', $customer->user?->name) }}">
                            </div>
                            <div class="col-span-12">
                                <label class="form-label f-12 f-light">E-mail</label>
                                <input type="email" name="email" class="form-control form-control-sm" value="{{ old('email', $customer->email) }}" required>
                                @error('email')<div class="text-danger f-12">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-span-6">
                                <label class="form-label f-12 f-light">Telefon</label>
                                <input type="text" name="phone" class="form-control form-control-sm" value="{{ old('phone', $customer->phone) }}">
                            </div>
                            <div class="col-span-6">
                                <label class="form-label f-12 f-light">Země (ISO2)</label>
                                <input type="text" name="country_code" class="form-control form-control-sm uppercase" maxlength="2" value="{{ old('country_code', $customer->country_code) }}">
                            </div>
                            <div class="col-span-12">
                                <label class="form-label f-12 f-light">Firma</label>
                                <input type="text" name="company_name" class="form-control form-control-sm" value="{{ old('company_name', $customer->company_name) }}">
                            </div>
                            <div class="col-span-6">
                                <label class="form-label f-12 f-light">IČ</label>
                                <input type="text" name="registration_number" class="form-control form-control-sm" value="{{ old('registration_number', $customer->registration_number) }}">
                            </div>
                            <div class="col-span-6">
                                <label class="form-label f-12 f-light">DIČ</label>
                                <input type="text" name="vat_number" class="form-control form-control-sm" value="{{ old('vat_number', $customer->vat_number) }}">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm mt-3">
                            <i data-feather="save" style="width:13px;height:13px;"></i> Uložit údaje
                        </button>
                    </form>
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

                {{-- Admin notes (quick sticky) --}}
                <x-panel.card title="Rychlá poznámka (sticky)">
                    <form method="POST" action="{{ route('admin.customers.notes', $customer) }}">
                        @csrf
                        @method('PUT')
                        <textarea name="admin_notes" rows="3"
                                  class="form-control form-control-sm f-12 font-monospace mb-2"
                                  placeholder="Interní poznámky — nevidí zákazník…"
                                  maxlength="5000">{{ old('admin_notes', $customer->admin_notes) }}</textarea>
                        @error('admin_notes')<div class="text-danger f-12 mb-2">{{ $message }}</div>@enderror
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i data-feather="save" style="width:13px;height:13px"></i>
                            {{ __('panel.common.save') }}
                        </button>
                    </form>
                </x-panel.card>

                {{-- Threaded internal notes --}}
                <x-panel.card title="Interní zápisky">
                    {{-- Add note form --}}
                    <form method="POST" action="{{ route('admin.customers.internal-notes.store', $customer) }}" class="mb-3">
                        @csrf
                        <textarea name="content" rows="2"
                                  class="form-control form-control-sm f-12 mb-2 @error('content') is-invalid @enderror"
                                  placeholder="Přidat zápis…" maxlength="5000"></textarea>
                        @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="flex gap-2 items-center">
                            <button type="submit" class="btn btn-primary btn-sm">Přidat</button>
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" name="is_pinned" value="1" id="pin_note">
                                <label class="form-check-label f-11" for="pin_note">Připnout</label>
                            </div>
                        </div>
                    </form>

                    {{-- Notes timeline --}}
                    @forelse($internalNotes as $note)
                    <div class="border rounded p-2 mb-2 {{ $note->is_pinned ? 'border-warning bg-light-warning' : '' }}">
                        <div class="flex justify-between items-start mb-1">
                            <div>
                                @if($note->is_pinned)
                                    <i data-feather="bookmark" style="width:12px;height:12px;" class="txt-warning me-1"></i>
                                @endif
                                <span class="f-12 f-w-600">{{ $note->admin?->name ?? 'Admin' }}</span>
                                <span class="f-light f-11 ms-2">{{ $note->created_at->diffForHumans() }}</span>
                            </div>
                            <div class="flex gap-1">
                                <form method="POST" action="{{ route('admin.customers.internal-notes.pin', [$customer, $note]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-outline-{{ $note->is_pinned ? 'warning' : 'secondary' }}" title="{{ $note->is_pinned ? 'Odepnout' : 'Připnout' }}">
                                        <i data-feather="{{ $note->is_pinned ? 'bookmark' : 'bookmark' }}" style="width:10px;height:10px;"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.customers.internal-notes.destroy', [$customer, $note]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-outline-danger" onclick="return confirm('Smazat zápis?')">
                                        <i data-feather="trash-2" style="width:10px;height:10px;"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <p class="f-12 mb-0" style="white-space:pre-wrap;">{{ $note->content }}</p>
                    </div>
                    @empty
                        <p class="f-light f-12 mb-0">Žádné zápisky.</p>
                    @endforelse
                </x-panel.card>

                {{-- Domains --}}
                @if($domains->isNotEmpty())
                    <x-panel.card :title="__('panel.nav.admin_domains')">
                        @foreach($domains as $domain)
                            <div class="flex justify-between items-center mb-1">
                                <span class="f-w-600 f-14">{{ $domain->fqdn() }}</span>
                                <span class="f-light f-12">{{ $domain->expires_at?->format('d.m.Y') ?? '—' }}</span>
                            </div>
                        @endforeach
                    </x-panel.card>
                @endif

                {{-- Phase 273: onboarding progress inline --}}
                <x-panel.card title="Onboarding">
                    @if($onboardingSteps->isNotEmpty())
                        <div class="flex justify-between mb-1">
                            <span class="f-light f-12">Postup</span>
                            <span class="f-12 f-w-600">{{ $onboardingCompleted }}/{{ $onboardingSteps->count() }}</span>
                        </div>
                        <div class="progress mb-3" style="height:6px;">
                            <div class="progress-bar bg-{{ $onboardingCompleted === $onboardingSteps->count() ? 'success' : 'primary' }}"
                                 style="width:{{ $onboardingSteps->count() > 0 ? round($onboardingCompleted / $onboardingSteps->count() * 100) : 0 }}%"></div>
                        </div>
                        @foreach($onboardingSteps as $step)
                            <div class="flex items-center gap-2 py-1 border-bottom">
                                <form method="POST" action="{{ route('admin.customer-onboarding-steps.update', $step) }}" class="mb-0">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-link p-0 border-0" title="{{ $step->isCompleted() ? 'Označit jako nesplněné' : 'Označit jako splněné' }}">
                                        <i data-feather="{{ $step->isCompleted() ? 'check-circle' : 'circle' }}"
                                           style="width:15px;height:15px;" class="{{ $step->isCompleted() ? 'txt-success' : 'txt-secondary' }}"></i>
                                    </button>
                                </form>
                                <span class="f-12 {{ $step->isCompleted() ? 'text-decoration-line-through f-light' : 'f-w-500' }}">{{ $step->step }}</span>
                                @if($step->is_required)
                                    <span class="badge badge-light-danger ms-auto f-10">povinný</span>
                                @endif
                            </div>
                        @endforeach
                    @else
                        <p class="f-light f-12 mb-2">Žádné kroky onboardingu.</p>
                    @endif

                    <form method="POST" action="{{ route('admin.customer-onboarding-steps.store') }}" class="flex gap-2 mt-3">
                        @csrf
                        <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                        <input type="text" name="step" class="form-control form-control-sm" placeholder="Nový krok…" required maxlength="100">
                        <button type="submit" class="btn btn-outline-primary btn-sm text-nowrap">Přidat</button>
                    </form>
                </x-panel.card>
            </div>

            <div class="col-span-8 xl:col-span-12">
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

                <div class="grid grid-cols-12 card-gap">
                    <div class="col-span-6 md:col-span-12">
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
                    <div class="col-span-6 md:col-span-12">
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

                <div class="grid grid-cols-12 card-gap">
                    <div class="col-span-6 md:col-span-12">
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
                    <div class="col-span-6 md:col-span-12">
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

                {{-- Phase 273: communication history inline --}}
                <x-panel.card title="Komunikace se zákazníkem">
                    <form method="POST" action="{{ route('admin.customer-communication-logs.store') }}" class="grid grid-cols-12 gap-2 items-end mb-3">
                        @csrf
                        <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                        <div class="col-span-6 col-span-12 md:col-span-2">
                            <label class="form-label f-12 mb-1">Kanál</label>
                            <select name="channel" class="form-select form-select-sm">
                                <option value="email">E-mail</option>
                                <option value="phone">Telefon</option>
                                <option value="chat">Chat</option>
                                <option value="note">Poznámka</option>
                            </select>
                        </div>
                        <div class="col-span-6 col-span-12 md:col-span-2">
                            <label class="form-label f-12 mb-1">Směr</label>
                            <select name="direction" class="form-select form-select-sm">
                                <option value="outbound">Odchozí</option>
                                <option value="inbound">Příchozí</option>
                            </select>
                        </div>
                        <div class="col-span-12 col-span-12 md:col-span-3">
                            <label class="form-label f-12 mb-1">Předmět</label>
                            <input type="text" name="subject" class="form-control form-control-sm" maxlength="255" placeholder="Volitelné">
                        </div>
                        <div class="col-span-12 col-span-12 md:col-span-3">
                            <label class="form-label f-12 mb-1">Obsah</label>
                            <input type="text" name="body" class="form-control form-control-sm" required placeholder="Shrnutí komunikace">
                        </div>
                        <div class="col-span-12 col-span-12 md:col-span-2">
                            <button type="submit" class="btn btn-primary btn-sm w-full text-white">Zaznamenat</button>
                        </div>
                    </form>

                    @if($communicationLogs->isEmpty())
                        <p class="f-light f-12 mb-0">Zatím žádné záznamy komunikace.</p>
                    @else
                        @foreach($communicationLogs as $log)
                            <div class="flex items-start gap-2 py-2 border-bottom">
                                <i data-feather="{{ ['email' => 'mail', 'phone' => 'phone', 'chat' => 'message-circle', 'note' => 'edit-3'][$log->channel] ?? 'message-square' }}"
                                   style="width:15px;height:15px;flex-shrink:0;" class="txt-primary mt-1"></i>
                                <div class="grow">
                                    <div class="flex items-center gap-2">
                                        <span class="f-12 f-w-600">{{ $log->subject ?: ucfirst($log->channel) }}</span>
                                        <span class="badge {{ $log->direction === 'inbound' ? 'badge-light-info' : 'badge-light-success' }} f-10">
                                            {{ $log->direction === 'inbound' ? 'Příchozí' : 'Odchozí' }}
                                        </span>
                                    </div>
                                    <p class="f-12 f-light mb-0">{{ \Illuminate\Support\Str::limit($log->body, 160) }}</p>
                                    <p class="f-11 f-light mb-0">{{ $log->adminUser?->name ?? 'systém' }} · {{ $log->created_at?->format('d.m.Y H:i') }}</p>
                                </div>
                            </div>
                        @endforeach
                        <p class="f-light f-11 mt-2 mb-0">
                            <a href="{{ route('admin.customer-communication-logs.index', ['customer_id' => $customer->id]) }}">Celá historie komunikace →</a>
                        </p>
                    @endif
                </x-panel.card>
            </div>
        </div>
    </div>
@endsection
