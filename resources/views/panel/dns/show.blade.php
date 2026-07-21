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
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($records as $rec)
                                    @php
                                        $color = $typeColors[$rec['type']] ?? 'secondary';
                                        $rowId = $rec['row_id'] ?? null;
                                    @endphp
                                    {{-- Read row --}}
                                    <tr>
                                        <td>
                                            <span class="badge badge-light-{{ $color }} f-11 px-2">{{ $rec['type'] }}</span>
                                        </td>
                                        <td class="font-monospace f-12">{{ $rec['name'] }}</td>
                                        <td class="font-monospace f-12 text-break" style="max-width:320px">{{ $rec['rdata'] }}</td>
                                        <td class="f-light f-12">{{ number_format($rec['ttl'] ?? 3600) }}</td>
                                        <td class="f-light f-12">{{ ($rec['prio'] ?? 0) > 0 ? $rec['prio'] : '—' }}</td>
                                        <td class="text-right">
                                            @if($rowId !== null)
                                                <div class="flex gap-1 justify-end">
                                                    <button type="button" class="btn btn-outline-primary btn-xs dns-edit-toggle"
                                                            data-row="dns-edit-{{ $rowId }}">Upravit</button>
                                                    <form method="POST" action="{{ route('panel.domains.dns.destroy', $domain) }}"
                                                          data-confirm="Smazat DNS záznam {{ $rec['type'] }} {{ $rec['name'] }}?">
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="hidden" name="row_id" value="{{ $rowId }}">
                                                        <button type="submit" class="btn btn-outline-danger btn-xs">Smazat</button>
                                                    </form>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                    {{-- Inline edit row --}}
                                    @if($rowId !== null)
                                        <tr id="dns-edit-{{ $rowId }}" class="hidden">
                                            <td colspan="6">
                                                <form method="POST" action="{{ route('panel.domains.dns.update', $domain) }}"
                                                      class="flex gap-2 items-end flex-wrap">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="row_id" value="{{ $rowId }}">
                                                    <div>
                                                        <label class="form-label f-11 mb-1">Typ</label>
                                                        <select name="type" class="form-select form-select-sm">
                                                            @foreach(['A','AAAA','CNAME','MX','TXT','NS','CAA'] as $t)
                                                                <option value="{{ $t }}" @selected($rec['type'] === $t)>{{ $t }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="form-label f-11 mb-1">Název</label>
                                                        <input type="text" name="name" value="{{ $rec['name'] }}" class="form-control form-control-sm" required>
                                                    </div>
                                                    <div class="grow" style="min-width:200px">
                                                        <label class="form-label f-11 mb-1">Hodnota</label>
                                                        <input type="text" name="rdata" value="{{ $rec['rdata'] }}" class="form-control form-control-sm" required>
                                                    </div>
                                                    <div>
                                                        <label class="form-label f-11 mb-1">TTL</label>
                                                        <input type="number" name="ttl" value="{{ $rec['ttl'] ?? 3600 }}" min="60" max="86400" class="form-control form-control-sm" style="width:100px">
                                                    </div>
                                                    <div>
                                                        <label class="form-label f-11 mb-1">Priorita</label>
                                                        <input type="number" name="prio" value="{{ $rec['prio'] ?? 0 }}" min="0" max="65535" class="form-control form-control-sm" style="width:90px">
                                                    </div>
                                                    <button type="submit" class="btn btn-primary btn-sm text-white">Uložit</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-panel.card>

                {{-- F91: one-click provider record sets --}}
                <x-panel.card title="DNS šablony">
                    <p class="f-light f-12 mb-3">Nastaví potřebné MX/TXT záznamy pro vybraného poskytovatele e-mailu jedním kliknutím.</p>
                    <div class="flex gap-2 flex-wrap">
                        @foreach($templates as $key => $tpl)
                            <form method="POST" action="{{ route('panel.domains.dns.template', $domain) }}"
                                  data-confirm="Aplikovat šablonu {{ $tpl['label'] }}? Přepíše odpovídající záznamy.">
                                @csrf
                                <input type="hidden" name="template" value="{{ $key }}">
                                <button type="submit" class="btn btn-outline-primary btn-sm">{{ $tpl['label'] }}</button>
                            </form>
                        @endforeach
                    </div>
                </x-panel.card>

                {{-- 64: DNSSEC — publish/remove DS records at the registry --}}
                <x-panel.card title="DNSSEC">
                    <p class="f-light f-12 mb-3">
                        DNSSEC podepisuje odpovědi DNS a chrání doménu před podvržením.
                        Zde spravujete DS záznamy publikované u registru.
                    </p>

                    @if(count($dnssecKeys) === 0)
                        <div class="alert alert-light-secondary f-12 mb-3">
                            <i data-feather="unlock" style="width:14px;height:14px"></i>
                            DNSSEC není pro tuto doménu aktivní.
                        </div>
                    @else
                        <div class="table-responsive mb-3">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Key Tag</th>
                                        <th>Alg.</th>
                                        <th>Digest</th>
                                        <th class="text-right"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($dnssecKeys as $key)
                                        <tr>
                                            <td class="font-monospace">{{ $key['key_tag'] ?? '—' }}</td>
                                            <td class="font-monospace">{{ $key['algorithm'] ?? '—' }}</td>
                                            <td class="font-monospace f-11 truncate" style="max-width:180px">{{ $key['digest'] ?? '—' }}</td>
                                            <td class="text-right">
                                                @if(isset($key['key_tag']))
                                                    <form method="POST" action="{{ route('panel.domains.dnssec.destroy', $domain) }}"
                                                          data-confirm="Odebrat DNSSEC klíč {{ $key['key_tag'] }}? Doména přestane být podepsaná.">
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="hidden" name="key_tag" value="{{ $key['key_tag'] }}">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                                            <i data-feather="trash-2" style="width:12px;height:12px"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('panel.domains.dnssec.store', $domain) }}">
                        @csrf
                        <div class="grid grid-cols-12 gap-2 mb-2">
                            <div class="col-span-3">
                                <label class="form-label f-11 f-light">Key Tag</label>
                                <input type="number" name="key_tag" value="{{ old('key_tag') }}" min="0" max="65535"
                                       class="form-control form-control-sm @error('key_tag') is-invalid @enderror">
                            </div>
                            <div class="col-span-4">
                                <label class="form-label f-11 f-light">Algoritmus</label>
                                <select name="algorithm" class="form-select form-select-sm @error('algorithm') is-invalid @enderror">
                                    @foreach(['13' => '13 (ECDSA P-256)', '8' => '8 (RSA/SHA-256)', '14' => '14 (ECDSA P-384)', '15' => '15 (Ed25519)', '16' => '16 (Ed448)', '10' => '10 (RSA/SHA-512)'] as $val => $label)
                                        <option value="{{ $val }}" {{ old('algorithm') == $val ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-5">
                                <label class="form-label f-11 f-light">Typ digestu</label>
                                <select name="digest_type" class="form-select form-select-sm @error('digest_type') is-invalid @enderror">
                                    @foreach(['2' => '2 (SHA-256)', '4' => '4 (SHA-384)', '1' => '1 (SHA-1)'] as $val => $label)
                                        <option value="{{ $val }}" {{ old('digest_type') == $val ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label f-11 f-light">Digest (hex)</label>
                            <input type="text" name="digest" value="{{ old('digest') }}"
                                   class="form-control form-control-sm font-monospace @error('digest') is-invalid @enderror"
                                   placeholder="E.g. 2BB183AF5F22588179A53B0A...">
                            @error('digest')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i data-feather="shield" style="width:13px;height:13px"></i>
                            Přidat DNSSEC klíč
                        </button>
                    </form>
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

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function () {
    // Reveal the inline edit row for a single record.
    document.querySelectorAll('.dns-edit-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = document.getElementById(btn.dataset.row);
            if (row) row.classList.toggle('hidden');
        });
    });
});
</script>
@endpush
