@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.domains'))

@section('title', __('panel.nav.domains'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <x-panel.card :title="__('panel.nav.domains')">
            @if($domains->isEmpty())
                <p class="f-light mb-0">{{ __('panel.domains.none') }}</p>
            @else
                <x-panel.data-table :headers="[
                    __('panel.domains.domain'),
                    __('panel.common.status'),
                    __('panel.domains.registered_at'),
                    __('panel.domains.expires_at'),
                    '',
                ]">
                    @foreach($domains as $domain)
                        <tr>
                            <td>{{ $domain->fqdn() }}</td>
                            <td>
                                @if($domain->wedos_domain_id !== null)
                                    <span class="badge badge-light-success">{{ __('panel.domains.registered') }}</span>
                                @else
                                    <span class="badge badge-light-warning">{{ __('panel.domains.pending') }}</span>
                                @endif
                            </td>
                            <td>{{ $domain->registered_at?->format('d.m.Y') ?? '—' }}</td>
                            <td>{{ $domain->expires_at?->format('d.m.Y') ?? '—' }}</td>
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
