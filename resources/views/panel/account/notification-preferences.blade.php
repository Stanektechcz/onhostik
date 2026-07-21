@extends('layouts.panel')

@php
    use App\Domains\Communication\Support\NotificationCatalog;

    $breadcrumbTitle = 'Notifikace';
    $breadcrumbItems = [__('panel.nav.account') => '#', 'Notifikace' => ''];

    $typeIcons = [
        'security'         => 'shield',
        'new_ip_login'     => 'map-pin',
        'account'          => 'user',
        'invoice'          => 'file-text',
        'payment'          => 'credit-card',
        'credit'           => 'dollar-sign',
        'service'          => 'server',
        'service_critical' => 'alert-octagon',
        'renewal'          => 'refresh-cw',
        'monitor'          => 'activity',
        'backup'           => 'archive',
        'maintenance'      => 'tool',
        'support'          => 'message-circle',
        'digest'           => 'inbox',
        'marketing'        => 'star',
    ];
    $channelLabels = ['mail' => 'E-mail', 'database' => 'V aplikaci'];
    $channelIcons  = ['mail' => 'mail', 'database' => 'bell'];
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
                            Zvolte, co chcete dostávat a jakým kanálem. Několik typů je
                            povinných — ty vypnout nelze a jsou označené zámkem.
                        </p>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:50%">Typ notifikace</th>
                                    @foreach ($channels as $channel)
                                        <th class="text-center">
                                            <i data-feather="{{ $channelIcons[$channel] ?? 'bell' }}" class="me-1" style="width:14px;height:14px;"></i>
                                            {{ $channelLabels[$channel] ?? $channel }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($grouped as $groupName => $groupTypes)
                                    <tr class="table-light">
                                        <td colspan="{{ count($channels) + 1 }}" class="f-w-600 f-12 uppercase f-light">
                                            {{ $groupName }}
                                        </td>
                                    </tr>

                                    @foreach ($groupTypes as $type => $meta)
                                        <tr>
                                            <td>
                                                <div class="flex items-start gap-2">
                                                    <i data-feather="{{ $typeIcons[$type] ?? 'bell' }}" style="width:16px;height:16px;flex-shrink:0;" class="text-primary mt-1"></i>
                                                    <div>
                                                        <div class="f-w-500 flex items-center gap-2">
                                                            {{ $meta['label'] }}
                                                            @if ($meta['mandatory'])
                                                                <span class="badge badge-light-secondary f-10">
                                                                    <i data-feather="lock" style="width:10px;height:10px;"></i>
                                                                    Povinné
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <div class="f-12 f-light">{{ $meta['description'] }}</div>
                                                    </div>
                                                </div>
                                            </td>

                                            @foreach ($channels as $channel)
                                                @php
                                                    $isOptIn = NotificationCatalog::isOptIn($type, $channel);

                                                    if ($isOptIn) {
                                                        // Off unless explicitly asked for.
                                                        $checked = in_array($type, (array) ($prefs['opt_in'][$channel] ?? []), true);
                                                        $locked  = false;
                                                    } elseif ($meta['mandatory']) {
                                                        $checked = true;
                                                        $locked  = true;
                                                    } else {
                                                        $checked = ! in_array($type, (array) ($prefs[$channel] ?? []), true);
                                                        $locked  = false;
                                                    }
                                                @endphp
                                                <td class="text-center">
                                                    <div class="form-check flex justify-center m-0">
                                                        <input class="form-check-input"
                                                               type="checkbox"
                                                               name="{{ $channel }}[]"
                                                               value="{{ $type }}"
                                                               aria-label="{{ $meta['label'] }} — {{ $channelLabels[$channel] ?? $channel }}"
                                                               @checked($checked)
                                                               @disabled($locked)>
                                                    </div>
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 flex gap-2 items-center">
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
                <div class="flex gap-3 items-start">
                    <i data-feather="info" class="text-info mt-1" style="width:18px;height:18px;flex-shrink:0;"></i>
                    <div>
                        <strong>Jak notifikace fungují</strong>
                        <ul class="mt-2 mb-0 ps-3 f-m-light">
                            <li><strong>E-mail</strong> — zprávy přicházejí na vaši registrovanou e-mailovou adresu.</li>
                            <li><strong>V aplikaci</strong> — notifikace se zobrazují v ikoně zvonku v záhlaví panelu.</li>
                            <li><strong>Povinné typy</strong> (zámek) nelze vypnout — jde o zabezpečení účtu
                                a kritické stavy služeb, kde by mlčení samo o sobě bylo škodou.</li>
                            <li><strong>Zabezpečení účtu e-mailem</strong> je naopak ve výchozím stavu vypnuté;
                                zapněte si ho, pokud chcete o přihlášení z neznámé adresy vědět i mimo aplikaci.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
