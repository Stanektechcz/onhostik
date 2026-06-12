@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.admin_settings'))

@section('title', __('panel.nav.admin_settings'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf

            {{-- ── Firemní údaje ──────────────────────────────────────────── --}}
            <x-panel.card title="Firemní a fakturační údaje">
                <p class="f-light f-12 mb-3">
                    Tyto hodnoty přepíší konfiguraci z <code>.env</code> na úrovni DB — zobrazí se na fakturách, v e-mailech a na webu.
                    Hodnoty načtené z prostředí jsou předvyplněné jako výchozí.
                </p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label f-w-500">Název společnosti</label>
                        <input type="text" name="company_name" class="form-control"
                               value="{{ $stored['company_name'] ?? config('billing.supplier.name') }}"
                               placeholder="Onhost.cz s.r.o.">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label f-w-500">IČ</label>
                        <input type="text" name="company_ic" class="form-control"
                               value="{{ $stored['company_ic'] ?? config('billing.supplier.ic') }}"
                               placeholder="12345678">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label f-w-500">DIČ</label>
                        <input type="text" name="company_dic" class="form-control"
                               value="{{ $stored['company_dic'] ?? config('billing.supplier.dic') }}"
                               placeholder="CZ12345678">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label f-w-500">Ulice a č.p.</label>
                        <input type="text" name="company_street" class="form-control"
                               value="{{ $stored['company_street'] ?? config('billing.supplier.street') }}"
                               placeholder="Příkladná 1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label f-w-500">Město</label>
                        <input type="text" name="company_city" class="form-control"
                               value="{{ $stored['company_city'] ?? config('billing.supplier.city') }}"
                               placeholder="Praha">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label f-w-500">PSČ</label>
                        <input type="text" name="company_zip" class="form-control"
                               value="{{ $stored['company_zip'] ?? config('billing.supplier.zip') }}"
                               placeholder="110 00">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label f-w-500">Bankovní účet CZK (IBAN / číslo)</label>
                        <input type="text" name="bank_czk" class="form-control"
                               value="{{ $stored['bank_czk'] ?? config('billing.supplier.bank_account_czk') }}"
                               placeholder="CZ65 0800 0000 0012 3456 7890">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label f-w-500">Bankovní účet EUR (IBAN)</label>
                        <input type="text" name="bank_eur" class="form-control"
                               value="{{ $stored['bank_eur'] ?? config('billing.supplier.bank_account_eur') }}"
                               placeholder="CZ65 0800 0000 0012 3456 7891">
                    </div>
                </div>
            </x-panel.card>

            {{-- ── Kontaktní e-maily ─────────────────────────────────────── --}}
            <x-panel.card title="Kontaktní e-maily">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label f-w-500">E-mail podpory</label>
                        <input type="email" name="support_email" class="form-control"
                               value="{{ $stored['support_email'] ?? 'podpora@onhost.cz' }}"
                               placeholder="podpora@onhost.cz">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label f-w-500">E-mail fakturace</label>
                        <input type="email" name="billing_email" class="form-control"
                               value="{{ $stored['billing_email'] ?? 'fakturace@onhost.cz' }}"
                               placeholder="fakturace@onhost.cz">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label f-w-500">Obchodní e-mail</label>
                        <input type="email" name="sales_email" class="form-control"
                               value="{{ $stored['sales_email'] ?? 'obchod@onhost.cz' }}"
                               placeholder="obchod@onhost.cz">
                    </div>
                </div>
            </x-panel.card>

            {{-- ── Web nastavení ─────────────────────────────────────────── --}}
            <x-panel.card title="Nastavení webu">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label f-w-500">Novinový ticker (horní lišta)</label>
                        <input type="text" name="news_ticker" class="form-control"
                               value="{{ $stored['news_ticker'] ?? config('front.news_ticker', '') }}"
                               placeholder="Spouštíme nové NVMe servery — akce pro nové zákazníky.">
                    </div>
                </div>
            </x-panel.card>

            {{-- ── Akce ──────────────────────────────────────────────────── --}}
            <x-panel.card title="Správa aplikace">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="form-check">
                        <input type="checkbox" name="clear_cache" value="1" id="clear_cache" class="form-check-input">
                        <label for="clear_cache" class="form-check-label">Smazat cache config/route/view po uložení</label>
                    </div>
                </div>
                <p class="f-light f-12 mt-3 mb-0">
                    PHP verze: <strong>{{ PHP_VERSION }}</strong> ·
                    Laravel verze: <strong>{{ app()->version() }}</strong> ·
                    Prostředí: <strong>{{ app()->environment() }}</strong> ·
                    Debug: <strong>{{ config('app.debug') ? 'zapnuto' : 'vypnuto' }}</strong>
                </p>
            </x-panel.card>

            <div class="d-flex justify-content-end pb-4">
                <button type="submit" class="btn btn-primary">Uložit nastavení</button>
            </div>
        </form>

        {{-- ── Zálohy konfigurace ────────────────────────────────────────── --}}
        <x-panel.card title="Env konfigurace (read-only)">
            <p class="f-light f-12 mb-3">Tato sekce zobrazuje aktuální hodnoty z <code>.env</code> souboru (pouze pro čtení). Upravte .env přímo na serveru nebo použijte formulář výše.</p>
            <x-panel.data-table :headers="['Klíč', 'Hodnota']">
                @foreach([
                    'APP_ENV'                 => config('app.env'),
                    'APP_URL'                 => config('app.url'),
                    'APP_DEBUG'               => config('app.debug') ? 'true' : 'false',
                    'DB_CONNECTION'           => config('database.default'),
                    'QUEUE_CONNECTION'        => config('queue.default'),
                    'PROVISIONING_MOCK_MODE'  => config('provisioning.mock_mode') ? 'true' : 'false',
                    'COMGATE_TEST_MODE'       => config('comgate.test_mode') ? 'true' : 'false',
                    'WAPI_TEST_MODE'          => config('app.env') !== 'production' ? 'true' : 'false',
                    'MAIL_MAILER'             => config('mail.default'),
                ] as $key => $value)
                    <tr>
                        <td><code>{{ $key }}</code></td>
                        <td>
                            @if(in_array($value, ['true', 'false'], true))
                                <span class="badge {{ $value === 'true' ? 'badge-light-warning' : 'badge-light-success' }}">{{ $value }}</span>
                            @else
                                {{ $value }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-panel.data-table>
        </x-panel.card>
    </div>
@endsection
