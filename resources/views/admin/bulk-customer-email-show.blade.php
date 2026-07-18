@extends('layouts.panel')

@php($breadcrumbTitle = $bulkEmail->subject)
@php($breadcrumbItems = ['Hromadný e-mail' => route('admin.bulk-email.index'), $bulkEmail->subject => ''])

@section('title', $bulkEmail->subject)

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">
        {{-- Main card --}}
        <div class="col-span-8 xl:col-span-12">
            <x-panel.card :title="$bulkEmail->subject">
                <div class="flex flex-wrap gap-2 mb-3 items-center">
                    <span class="badge {{ $bulkEmail->status === 'sent' ? 'badge-light-success' : ($bulkEmail->status === 'sending' ? 'badge-light-warning' : ($bulkEmail->status === 'failed' ? 'badge-light-danger' : 'badge-light-secondary')) }}">
                        {{ $bulkEmail->status === 'sent' ? 'Odesláno' : ($bulkEmail->status === 'sending' ? 'Odesílání' : ($bulkEmail->status === 'failed' ? 'Chyba' : 'Koncept')) }}
                    </span>
                    @if($bulkEmail->sent_at)
                        <span class="f-12 f-light">Odesláno: {{ $bulkEmail->sent_at->format('d.m.Y H:i') }}</span>
                    @endif
                    <span class="f-12 f-light">Vytvořil: {{ $bulkEmail->creator?->name }}</span>
                </div>

                {{-- Body preview --}}
                <div class="border rounded p-3 mb-3" style="max-height:300px;overflow-y:auto;background:rgba(var(--light-semi-gray),1);">
                    {!! $bulkEmail->body_html !!}
                </div>

                {{-- Actions --}}
                @if($bulkEmail->isDraft())
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('admin.bulk-email.send', $bulkEmail) }}"
                              onsubmit="return confirm('Odeslat e-mail {{ $recipientCount }} zákazníkům?')">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i data-feather="send" style="width:13px;height:13px;"></i>
                                Odeslat ({{ $recipientCount }} příjemců)
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.bulk-email.destroy', $bulkEmail) }}"
                              onsubmit="return confirm('Smazat kampaň?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i data-feather="trash-2" style="width:13px;height:13px;"></i>
                                Smazat
                            </button>
                        </form>
                    </div>
                    @error('send')
                        <div class="text-danger f-12 mt-2">{{ $message }}</div>
                    @enderror
                @elseif($bulkEmail->isSending())
                    <div class="alert alert-info f-12">
                        Odesílání probíhá: {{ $bulkEmail->sent_count }} / {{ $bulkEmail->recipients_count }}
                    </div>
                @endif
            </x-panel.card>
        </div>

        {{-- Sidebar: filters --}}
        <div class="col-span-4 xl:col-span-12">
            <x-panel.card title="Filtr příjemců">
                @php $filters = $bulkEmail->filters ?? []; @endphp
                @if(empty($filters))
                    <p class="f-12 f-light mb-0">Žádné filtry — všichni zákazníci s e-mailem.</p>
                @else
                    <ul class="list-unstyled f-12 mb-0">
                        @if(!empty($filters['segment']))
                            <li><span class="f-w-500">Segment:</span> {{ $filters['segment'] }}</li>
                        @endif
                        @if(!empty($filters['country_code']))
                            <li><span class="f-w-500">Země:</span> {{ $filters['country_code'] }}</li>
                        @endif
                        @if(!empty($filters['tag_id']))
                            <li>
                                <span class="f-w-500">Štítek:</span>
                                {{ \App\Domains\Customer\Models\CustomerTag::find($filters['tag_id'])?->name ?? '#' . $filters['tag_id'] }}
                            </li>
                        @endif
                        @if(!empty($filters['has_overdue']))
                            <li><span class="badge badge-light-danger f-10">Po-splatnostní faktura</span></li>
                        @endif
                    </ul>
                @endif
                <hr class="my-2">
                <div class="f-12">
                    <strong>Příjemců:</strong>
                    <span class="{{ $recipientCount > 0 ? 'txt-success' : 'text-muted' }}">{{ $recipientCount }}</span>
                </div>
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
