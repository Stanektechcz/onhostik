@extends('layouts.panel')

@section('title', 'Webhook retry politika')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="Konfigurace retry politiky webhooků">
        @if($endpoints->isEmpty())
            <p class="text-muted">Žádné webhook endpointy.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Endpoint</th>
                        <th class="text-right">Max retries</th>
                        <th class="text-right">Zpoždění (s)</th>
                        <th class="text-right">Timeout (s)</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($endpoints as $ep)
                    <tr>
                        <td class="truncate" style="max-width:300px"><strong>{{ $ep->name }}</strong> <small class="text-muted">({{ $ep->source }})</small></td>
                        <td class="text-right">{{ $ep->max_retries ?? 3 }}</td>
                        <td class="text-right">{{ $ep->retry_delay_seconds ?? 60 }}</td>
                        <td class="text-right">{{ $ep->timeout_seconds ?? 10 }}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#edit-{{ $ep->id }}">Upravit</button>
                        </td>
                    </tr>
                    <tr class="collapse" id="edit-{{ $ep->id }}">
                        <td colspan="5" class="bg-light">
                            <form method="POST" action="{{ route('admin.webhook-retry-policy.update', $ep->id) }}" class="flex gap-3 items-end p-2">
                                @csrf @method('PATCH')
                                <div>
                                    <label class="form-label mb-1 small">Max retries</label>
                                    <input type="number" name="max_retries" class="form-control form-control-sm" value="{{ $ep->max_retries ?? 3 }}" min="0" max="10" style="width:80px">
                                </div>
                                <div>
                                    <label class="form-label mb-1 small">Zpoždění (s)</label>
                                    <input type="number" name="retry_delay_seconds" class="form-control form-control-sm" value="{{ $ep->retry_delay_seconds ?? 60 }}" min="10" max="3600" style="width:100px">
                                </div>
                                <div>
                                    <label class="form-label mb-1 small">Timeout (s)</label>
                                    <input type="number" name="timeout_seconds" class="form-control form-control-sm" value="{{ $ep->timeout_seconds ?? 10 }}" min="3" max="60" style="width:80px">
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary">Uložit</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </x-panel.card>
</div>
@endsection
