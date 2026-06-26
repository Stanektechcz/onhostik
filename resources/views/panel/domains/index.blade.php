@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.domains');
    $breadcrumbItems = [__('panel.nav.domains') => ''];
@endphp

@section('title', __('panel.nav.domains'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <x-panel.card :title="__('panel.nav.domains')">
            @if($domains->isEmpty())
                <div class="text-center py-5">
                    <i data-feather="globe" style="width:48px;height:48px;" class="text-muted mb-3"></i>
                    <h6 class="f-light mt-2">{{ __('panel.domains.none') }}</h6>
                    <p class="f-light f-12 mb-3">Domény se zobrazí po registraci nebo převodu.</p>
                    <a href="{{ route('panel.orders.create') }}" class="btn btn-primary btn-sm">
                        <i data-feather="plus" style="width:13px;height:13px;"></i>
                        {{ __('panel.nav.new_order') }}
                    </a>
                </div>
            @else
                <x-panel.data-table :headers="[
                    __('panel.domains.domain'),
                    __('panel.common.status'),
                    __('panel.domains.registered_at'),
                    __('panel.domains.expires_at'),
                    'Auto-renew',
                    '',
                ]">
                    @foreach($domains as $domain)
                        @php
                            $expiresDays = $domain->expires_at ? now()->diffInDays($domain->expires_at, false) : null;
                            $expiryClass = '';
                            if ($expiresDays !== null) {
                                if ($expiresDays < 0) $expiryClass = 'text-danger f-w-600';
                                elseif ($expiresDays <= 30) $expiryClass = 'text-warning';
                            }
                        @endphp
                        <tr>
                            <td class="f-w-600">{{ $domain->fqdn() }}</td>
                            <td>
                                @if($domain->wedos_domain_id !== null)
                                    <span class="badge badge-light-success">{{ __('panel.domains.registered') }}</span>
                                @else
                                    <span class="badge badge-light-warning">{{ __('panel.domains.pending') }}</span>
                                @endif
                            </td>
                            <td class="f-12">{{ $domain->registered_at?->format('d.m.Y') ?? '—' }}</td>
                            <td class="f-12 {{ $expiryClass }}">
                                {{ $domain->expires_at?->format('d.m.Y') ?? '—' }}
                                @if($expiresDays !== null && $expiresDays <= 30 && $expiresDays >= 0)
                                    <span class="f-12 f-light">({{ $expiresDays }}d)</span>
                                @endif
                            </td>
                            <td>
                                @if($domain->auto_renew)
                                    <span class="badge badge-light-success">ON</span>
                                @else
                                    <span class="badge badge-light-secondary">OFF</span>
                                @endif
                            </td>
                            <td>
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('panel.domains.show', $domain) }}">
                                    {{ __('panel.common.detail') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
                {{ $domains->links() }}
            @endif
        </x-panel.card>
    </div>
@endsection
