@extends('layouts.panel')

@section('title', 'SLA Incident')

@section('content')
<div class="container-fluid py-4">

    @if(session('status'))
        <div class="alert alert-success py-2">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-12">

        {{-- Incident detail --}}
        <div class="col-span-12 lg:col-span-8">
            <div class="card mb-4">
                <div class="card-header flex justify-between items-center">
                    <h5 class="mb-0">{{ $incident->title }}</h5>
                    <div>
                        <span class="{{ $incident->severityBadgeClass() }} me-1">{{ $incident->severityLabel() }}</span>
                        <span class="{{ $incident->statusBadgeClass() }}">{{ $incident->statusLabel() }}</span>
                        @if($incident->sla_breached)
                            <span class="badge bg-danger ms-1">SLA porušeno</span>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    @if($incident->description)
                        <p class="text-muted">{{ $incident->description }}</p>
                    @endif

                    <dl class="grid grid-cols-12 small mb-0">
                        <dt class="col-span-12 sm:col-span-4">Služba</dt>
                        <dd class="col-span-12 sm:col-span-8">{{ $incident->service?->name ?? '—' }}</dd>

                        <dt class="col-span-12 sm:col-span-4">Zákazník</dt>
                        <dd class="col-span-12 sm:col-span-8">{{ $incident->service?->customer?->company ?? '—' }}</dd>

                        <dt class="col-span-12 sm:col-span-4">Začátek</dt>
                        <dd class="col-span-12 sm:col-span-8">{{ $incident->started_at->format('d.m.Y H:i') }}</dd>

                        @if($incident->resolved_at)
                        <dt class="col-span-12 sm:col-span-4">Vyřešeno</dt>
                        <dd class="col-span-12 sm:col-span-8">{{ $incident->resolved_at->format('d.m.Y H:i') }}</dd>

                        <dt class="col-span-12 sm:col-span-4">Trvání výpadku</dt>
                        <dd class="col-span-12 sm:col-span-8">{{ $incident->durationLabel() }}</dd>
                        @endif

                        @if($incident->credit_haler > 0)
                        <dt class="col-span-12 sm:col-span-4">Kredit zákazníkovi</dt>
                        <dd class="col-span-12 sm:col-span-8 text-danger">{{ $incident->credit_haler }} % z měsíčního poplatku</dd>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Timeline --}}
            <div class="card mb-4">
                <div class="card-header"><strong>Průběh incidentu</strong></div>
                <div class="card-body p-0">
                    @forelse($incident->updates->sortBy('created_at') as $update)
                    <div class="border-bottom p-3">
                        <div class="flex justify-between mb-1">
                            <span class="badge bg-info text-dark">{{ $update->statusLabel() }}</span>
                            <span class="text-muted small">{{ $update->created_at->format('d.m.Y H:i') }}
                                {{ $update->creator ? '· ' . $update->creator->name : '' }}
                            </span>
                        </div>
                        <p class="mb-0 small">{{ $update->message }}</p>
                    </div>
                    @empty
                    <div class="text-muted text-center py-3 small">Žádné aktualizace.</div>
                    @endforelse
                </div>
            </div>

            {{-- Add update (only if open) --}}
            @if($incident->status !== 'resolved')
            <div class="card">
                <div class="card-header"><strong>Přidat aktualizaci</strong></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.sla-incidents.update', $incident) }}">
                        @csrf
                        <div class="mb-3">
                            <select name="status" class="form-select form-select-sm mb-2">
                                <option value="investigating">Šetření</option>
                                <option value="identified">Identifikováno</option>
                                <option value="monitoring">Monitoring</option>
                                <option value="resolved">Vyřešeno</option>
                            </select>
                            <textarea name="message" class="form-control" rows="3" placeholder="Zpráva..." required></textarea>
                        </div>
                        <button class="btn btn-sm btn-primary">Přidat aktualizaci</button>
                    </form>
                </div>
            </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="col-span-12 lg:col-span-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Informace</h6>
                    <dl class="grid grid-cols-12 small mb-0">
                        <dt class="col-span-12 sm:col-span-5">Vytvořil</dt>
                        <dd class="col-span-12 sm:col-span-7">{{ $incident->creator?->name ?? '—' }}</dd>
                        <dt class="col-span-12 sm:col-span-5">Vytvořeno</dt>
                        <dd class="col-span-12 sm:col-span-7">{{ $incident->created_at->format('d.m.Y H:i') }}</dd>
                    </dl>
                </div>
            </div>
            <div class="mt-3">
                <a href="{{ route('admin.sla-incidents.index') }}" class="btn btn-sm btn-outline-secondary w-full">← Zpět na seznam</a>
            </div>
        </div>

    </div>

</div>
@endsection
