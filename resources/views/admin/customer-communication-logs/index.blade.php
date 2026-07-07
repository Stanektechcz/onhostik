@extends('layouts.panel')

@section('title', 'Záznamy komunikace se zákazníky')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- Filter --}}
    <x-panel.card title="Filtr">
        <form method="GET" action="{{ route('admin.customer-communication-logs.index') }}" class="d-flex gap-2 align-items-end">
            <div>
                <label class="form-label mb-1 small">Customer ID</label>
                <input type="number" name="customer_id" class="form-control form-control-sm" value="{{ $customerId }}" placeholder="všichni" style="width:140px">
            </div>
            <button type="submit" class="btn btn-sm btn-primary">Filtrovat</button>
            @if($customerId)
                <a href="{{ route('admin.customer-communication-logs.index') }}" class="btn btn-sm btn-outline-secondary">Zrušit filtr</a>
            @endif
        </form>
    </x-panel.card>

    {{-- Table --}}
    <x-panel.card title="Záznamy komunikace{{ $customerId ? ' – Customer #'.$customerId : '' }}">
        @if($logs->isEmpty())
            <p class="text-muted">Žádné záznamy.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer ID</th>
                        <th>Kanál</th>
                        <th>Směr</th>
                        <th>Předmět</th>
                        <th>Datum</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                    <tr>
                        <td>{{ $log->id }}</td>
                        <td>{{ $log->customer_id }}</td>
                        <td>
                            @php
                                $channelColor = match($log->channel) {
                                    'email' => 'primary',
                                    'phone' => 'success',
                                    'chat'  => 'info',
                                    default => 'secondary',
                                };
                            @endphp
                            <span class="badge bg-{{ $channelColor }}">{{ $log->channel }}</span>
                        </td>
                        <td>
                            <span class="badge bg-{{ $log->direction === 'inbound' ? 'warning' : 'dark' }}">
                                {{ $log->direction === 'inbound' ? 'Příchozí' : 'Odchozí' }}
                            </span>
                        </td>
                        <td class="text-truncate" style="max-width:260px">{{ $log->subject ?? '—' }}</td>
                        <td class="text-nowrap">{{ $log->created_at->format('d.m.Y H:i') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $logs->withQueryString()->links() }}</div>
        @endif
    </x-panel.card>

    {{-- Create form --}}
    <x-panel.card title="Nový záznam komunikace">
        <form method="POST" action="{{ route('admin.customer-communication-logs.store') }}" class="row g-3">
            @csrf

            <div class="col-md-2">
                <label class="form-label">Customer ID <span class="text-danger">*</span></label>
                <input type="number" name="customer_id" class="form-control @error('customer_id') is-invalid @enderror"
                       value="{{ old('customer_id', $customerId) }}" required>
                @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2">
                <label class="form-label">Kanál <span class="text-danger">*</span></label>
                <select name="channel" class="form-select @error('channel') is-invalid @enderror" required>
                    <option value="">— vyberte —</option>
                    @foreach(['email' => 'Email', 'phone' => 'Telefon', 'chat' => 'Chat', 'note' => 'Poznámka'] as $val => $label)
                        <option value="{{ $val }}" @selected(old('channel') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('channel')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2">
                <label class="form-label">Směr <span class="text-danger">*</span></label>
                <select name="direction" class="form-select @error('direction') is-invalid @enderror" required>
                    <option value="">— vyberte —</option>
                    <option value="inbound"  @selected(old('direction') === 'inbound')>Příchozí (inbound)</option>
                    <option value="outbound" @selected(old('direction') === 'outbound')>Odchozí (outbound)</option>
                </select>
                @error('direction')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label class="form-label">Předmět</label>
                <input type="text" name="subject" class="form-control @error('subject') is-invalid @enderror"
                       value="{{ old('subject') }}" maxlength="255">
                @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label class="form-label">Zpráva / tělo <span class="text-danger">*</span></label>
                <textarea name="body" rows="5" class="form-control @error('body') is-invalid @enderror" required>{{ old('body') }}</textarea>
                @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary">Uložit záznam</button>
            </div>
        </form>
    </x-panel.card>
</div>
@endsection
