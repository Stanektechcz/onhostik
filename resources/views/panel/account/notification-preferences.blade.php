@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Notifikace';
    $breadcrumbItems = [__('panel.nav.account') => '#', 'Notifikace' => ''];

    $typeLabels = [
        'renewal' => 'Obnovy služeb',
        'invoice' => 'Faktury',
        'payment' => 'Platby',
        'support' => 'Podpora',
        'backup'  => 'Zálohy',
        'monitor' => 'Monitoring',
    ];
    $typeIcons = [
        'renewal' => 'refresh-cw',
        'invoice' => 'file-text',
        'payment' => 'credit-card',
        'support' => 'message-circle',
        'backup'  => 'archive',
        'monitor' => 'activity',
    ];
    $channelLabels = [
        'mail'     => 'E-mail',
        'database' => 'V aplikaci',
    ];
    $channelIcons = [
        'mail'     => 'mail',
        'database' => 'bell',
    ];
@endphp

@section('title', 'Předvolby notifikací')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="container">
        <form method="POST" action="{{ route('panel.account.notification-preferences.update') }}">
            @csrf @method('PUT')

            <div class="card">
                <div class="card-header card-no-border">
                    <div class="header-top">
                        <h5>Předvolby notifikací</h5>
                        <p class="f-m-light mt-1">
                            Zvolte, jaké typy notifikací chcete dostávat a jakým kanálem.
                            Odezaškrtnuté položky vás nebudou obtěžovat.
                        </p>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40%">Typ notifikace</th>
                                    @foreach ($channels as $channel)
                                        <th class="text-center">
                                            <i data-feather="{{ $channelIcons[$channel] }}" class="me-1" style="width:14px;height:14px;"></i>
                                            {{ $channelLabels[$channel] }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($types as $type)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <i data-feather="{{ $typeIcons[$type] }}" style="width:16px;height:16px;" class="text-primary"></i>
                                                {{ $typeLabels[$type] }}
                                            </div>
                                        </td>
                                        @foreach ($channels as $channel)
                                            @php
                                                $optOut  = $prefs[$channel] ?? [];
                                                $checked = ! in_array($type, (array) $optOut, true);
                                            @endphp
                                            <td class="text-center">
                                                <div class="form-check d-flex justify-content-center m-0">
                                                    <input class="form-check-input"
                                                           type="checkbox"
                                                           name="{{ $channel }}[]"
                                                           value="{{ $type }}"
                                                           @checked($checked)>
                                                </div>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 d-flex gap-2 align-items-center">
                        <button type="submit" class="btn btn-primary text-white">
                            <i data-feather="save" class="me-1" style="width:14px;height:14px;"></i>
                            Uložit předvolby
                        </button>
                        <a href="{{ route('panel.account.security') }}" class="btn btn-light">
                            Zrušit
                        </a>
                    </div>
                </div>
            </div>

        </form>

        {{-- Info card --}}
        <div class="card mt-4">
            <div class="card-body">
                <div class="d-flex gap-3 align-items-start">
                    <i data-feather="info" class="text-info mt-1" style="width:18px;height:18px;flex-shrink:0;"></i>
                    <div>
                        <strong>Jak notifikace fungují</strong>
                        <ul class="mt-2 mb-0 ps-3 f-m-light">
                            <li><strong>E-mail</strong> — zprávy přicházejí na vaši registrovanou e-mailovou adresu.</li>
                            <li><strong>V aplikaci</strong> — notifikace se zobrazují v ikoně zvonku v záhlaví panelu.</li>
                            <li>Odezaškrtnuté typy <strong>nevypínají</strong> kritická upozornění (např. platby po splatnosti).</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
