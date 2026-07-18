@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.dns.title');
    $breadcrumbItems = [
        __('panel.nav.domains') => route('panel.domains.index'),
        $domain->fqdn()         => route('panel.domains.show', $domain),
        __('panel.dns.title')   => '',
    ];
    $typeColors = [
        'A'     => 'success',
        'AAAA'  => 'success',
        'CNAME' => 'info',
        'MX'    => 'warning',
        'TXT'   => 'secondary',
        'NS'    => 'primary',
        'CAA'   => 'danger',
    ];
@endphp

@section('title', $domain->fqdn() . ' — DNS')

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        @if($isMock)
            <div class="alert alert-light-warning flex gap-2 items-center py-2 px-3 mb-3 f-12">
                <i data-feather="alert-triangle" style="width:14px;height:14px;flex-shrink:0" class="font-warning"></i>
                <span>{{ __('panel.dns.mock_notice') }}</span>
            </div>
        @endif

        <div class="grid grid-cols-12 card-gap">

            {{-- DNS Records table --}}
            <div class="col-span-8 xl:col-span-12">
                <x-panel.card :title="__('panel.dns.records')">
                    <div class="table-responsive">
                        <table class="table table-hover f-13 mb-0">
                            <thead>
                                <tr>
                                    <th class="f-light f-12">{{ __('panel.dns.col_type') }}</th>
                                    <th class="f-light f-12">{{ __('panel.dns.col_name') }}</th>
                                    <th class="f-light f-12">{{ __('panel.dns.col_data') }}</th>
                                    <th class="f-light f-12">TTL</th>
                                    <th class="f-light f-12">{{ __('panel.dns.col_prio') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($records as $rec)
                                    @php
                                        $color = $typeColors[$rec['type']] ?? 'secondary';
                                    @endphp
                                    <tr>
                                        <td>
                                            <span class="badge badge-light-{{ $color }} f-11 px-2">{{ $rec['type'] }}</span>
                                        </td>
                                        <td class="font-monospace f-12">{{ $rec['name'] }}</td>
                                        <td class="font-monospace f-12 text-break" style="max-width:320px">{{ $rec['rdata'] }}</td>
                                        <td class="f-light f-12">{{ number_format($rec['ttl'] ?? 3600) }}</td>
                                        <td class="f-light f-12">{{ $rec['prio'] > 0 ? $rec['prio'] : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-panel.card>
            </div>

            {{-- Add DNS Record --}}
            <div class="col-span-4 xl:col-span-12">
                <x-panel.card :title="__('panel.dns.add_record')">
                    <form method="POST" action="{{ route('panel.domains.dns.store', $domain) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label f-12 f-light">{{ __('panel.dns.col_type') }}</label>
                            <select name="type" class="form-select form-select-sm @error('type') is-invalid @enderror">
                                @foreach(['A','AAAA','CNAME','MX','TXT','NS','CAA'] as $t)
                                    <option value="{{ $t }}" {{ old('type') === $t ? 'selected' : '' }}>{{ $t }}</option>
                                @endforeach
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label f-12 f-light">{{ __('panel.dns.col_name') }}</label>
                            <input type="text" name="name" value="{{ old('name', '@') }}"
                                   class="form-control form-control-sm font-monospace @error('name') is-invalid @enderror"
                                   placeholder="@ nebo subdoména">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label f-12 f-light">{{ __('panel.dns.col_data') }}</label>
                            <input type="text" name="rdata" value="{{ old('rdata') }}"
                                   class="form-control form-control-sm font-monospace @error('rdata') is-invalid @enderror"
                                   placeholder="IP nebo hodnota záznamu">
                            @error('rdata')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="grid grid-cols-12 gap-2 mb-3">
                            <div class="col-span-6">
                                <label class="form-label f-12 f-light">TTL</label>
                                <input type="number" name="ttl" value="{{ old('ttl', 3600) }}"
                                       class="form-control form-control-sm @error('ttl') is-invalid @enderror"
                                       min="60" max="86400">
                            </div>
                            <div class="col-span-6">
                                <label class="form-label f-12 f-light">{{ __('panel.dns.col_prio') }}</label>
                                <input type="number" name="prio" value="{{ old('prio', 0) }}"
                                       class="form-control form-control-sm @error('prio') is-invalid @enderror"
                                       min="0" max="65535">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm w-full">
                            <i data-feather="plus" style="width:13px;height:13px"></i>
                            {{ __('panel.dns.save') }}
                        </button>
                    </form>
                </x-panel.card>

                <div class="mt-1">
                    <a href="{{ route('panel.domains.show', $domain) }}" class="btn btn-outline-secondary btn-sm">
                        <i data-feather="arrow-left" style="width:13px;height:13px"></i>
                        {{ $domain->fqdn() }}
                    </a>
                </div>
            </div>

        </div>
    </div>
@endsection
