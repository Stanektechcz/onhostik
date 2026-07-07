@extends('layouts.panel')

@php($breadcrumbTitle = 'Hromadný e-mail zákazníkům')
@php($breadcrumbItems = ['Marketing' => '#', 'Hromadný e-mail' => ''])

@section('title', 'Hromadný e-mail zákazníkům')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">
        {{-- Create form --}}
        <div class="col-span-5 xl:col-span-12">
            <x-panel.card title="Nový e-mail">
                <form method="POST" action="{{ route('admin.bulk-email.store') }}" id="bulkEmailForm">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">Předmět</label>
                        <input type="text" name="subject" value="{{ old('subject') }}"
                               class="form-control form-control-sm @error('subject') is-invalid @enderror"
                               required maxlength="255">
                        @error('subject')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Obsah (HTML)</label>
                        <textarea name="body_html" rows="8"
                                  class="form-control form-control-sm @error('body_html') is-invalid @enderror"
                                  required>{{ old('body_html') }}</textarea>
                        <div class="f-11 text-muted mt-1">Použijte <code>{{ '{' }}{{ '{' }}recipientName{{ '}' }}{{ '}' }}</code> pro jméno příjemce.</div>
                        @error('body_html')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <hr class="my-3">
                    <h6 class="f-12 f-w-600 mb-3">Filtr příjemců</h6>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label f-12">Segment</label>
                            <select name="filter_segment" class="form-select form-select-sm filter-input">
                                <option value="">— Všechny segmenty —</option>
                                <option value="vip"     {{ old('filter_segment') === 'vip'      ? 'selected' : '' }}>VIP</option>
                                <option value="healthy" {{ old('filter_segment') === 'healthy'  ? 'selected' : '' }}>Zdraví</option>
                                <option value="at_risk" {{ old('filter_segment') === 'at_risk'  ? 'selected' : '' }}>At Risk</option>
                                <option value="churned" {{ old('filter_segment') === 'churned'  ? 'selected' : '' }}>Churned</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-12">Země</label>
                            <select name="filter_country_code" class="form-select form-select-sm filter-input">
                                <option value="">— Všechny země —</option>
                                @foreach($countries as $cc)
                                    <option value="{{ $cc }}" {{ old('filter_country_code') === $cc ? 'selected' : '' }}>{{ $cc }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label f-12">Štítek zákazníka</label>
                            <select name="filter_tag_id" class="form-select form-select-sm filter-input">
                                <option value="">— Všechny štítky —</option>
                                @foreach($tags as $tag)
                                    <option value="{{ $tag->id }}" {{ old('filter_tag_id') == $tag->id ? 'selected' : '' }}>{{ $tag->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check mb-1">
                                <input class="form-check-input filter-input" type="checkbox" name="filter_has_overdue"
                                       value="1" id="filterOverdue"
                                       {{ old('filter_has_overdue') ? 'checked' : '' }}>
                                <label class="form-check-label f-12" for="filterOverdue">
                                    Pouze s po-splatností fakturou
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 d-flex gap-2 align-items-center">
                        <button type="button" id="previewCountBtn" class="btn btn-outline-secondary btn-sm">
                            <i data-feather="users" style="width:12px;height:12px;"></i>
                            Náhled počtu příjemců
                        </button>
                        <span id="previewCountResult" class="f-12 f-light"></span>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm">
                        <i data-feather="save" style="width:13px;height:13px;"></i>
                        Uložit jako koncept
                    </button>
                </form>
            </x-panel.card>
        </div>

        {{-- History --}}
        <div class="col-span-7 xl:col-span-12">
            <x-panel.card title="Historie kampaní">
                @if($campaigns->isEmpty())
                    <p class="f-light f-12 mb-0">Zatím žádné kampaně.</p>
                @else
                    <x-panel.data-table :headers="['Předmět', 'Stav', 'Příjemci', 'Odesláno', 'Vytvořeno', '']">
                        @foreach($campaigns as $campaign)
                            <tr>
                                <td class="f-w-500">
                                    <a href="{{ route('admin.bulk-email.show', $campaign) }}">{{ $campaign->subject }}</a>
                                </td>
                                <td>
                                    <span class="badge f-10 {{ $campaign->status === 'sent' ? 'badge-light-success' : ($campaign->status === 'sending' ? 'badge-light-warning' : ($campaign->status === 'failed' ? 'badge-light-danger' : 'badge-light-secondary')) }}">
                                        {{ $campaign->status === 'sent' ? 'Odesláno' : ($campaign->status === 'sending' ? 'Odesílání' : ($campaign->status === 'failed' ? 'Chyba' : 'Koncept')) }}
                                    </span>
                                </td>
                                <td class="f-12">{{ $campaign->recipients_count ?: '—' }}</td>
                                <td class="f-12">
                                    @if($campaign->sent_count > 0)
                                        {{ $campaign->sent_count }} / {{ $campaign->recipients_count }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="f-light f-12">{{ $campaign->created_at?->format('d.m.Y H:i') }}</td>
                                <td>
                                    <a href="{{ route('admin.bulk-email.show', $campaign) }}"
                                       class="btn btn-xs btn-outline-primary">Detail</a>
                                </td>
                            </tr>
                        @endforeach
                    </x-panel.data-table>
                    <div class="mt-3">{{ $campaigns->links() }}</div>
                @endif
            </x-panel.card>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('previewCountBtn');
    var result = document.getElementById('previewCountResult');
    if (!btn) { return; }

    btn.addEventListener('click', function () {
        var params = new URLSearchParams();
        var segment = document.querySelector('[name="filter_segment"]');
        var country = document.querySelector('[name="filter_country_code"]');
        var tag     = document.querySelector('[name="filter_tag_id"]');
        var overdue = document.getElementById('filterOverdue');

        if (segment && segment.value)  { params.set('filter_segment', segment.value); }
        if (country && country.value)  { params.set('filter_country_code', country.value); }
        if (tag     && tag.value)      { params.set('filter_tag_id', tag.value); }
        if (overdue && overdue.checked) { params.set('filter_has_overdue', '1'); }

        result.textContent = 'Načítám…';

        fetch('{{ route('admin.bulk-email.preview-count') }}?' + params.toString())
            .then(function (r) { return r.json(); })
            .then(function (data) {
                result.textContent = 'Příjemců: ' + data.count;
            })
            .catch(function () {
                result.textContent = 'Chyba načítání.';
            });
    });
});
</script>
@endsection
