@extends('layouts.panel')

@section('title', 'Onboarding')

@section('content')
<x-panel.flash />

<x-panel.card title="Průvodce nastavením">
    @if($total > 0)
        <div class="mb-3">
            <div class="progress" style="height: 8px;">
                <div class="progress-bar bg-success" role="progressbar"
                     style="width: {{ $total > 0 ? round($completed / $total * 100) : 0 }}%"></div>
            </div>
            <small class="text-muted mt-1 block">Dokončeno {{ $completed }} z {{ $total }} kroků</small>
        </div>
    @endif

    @if($steps->isEmpty())
        <p class="text-muted">Žádné onboarding kroky nejsou přiřazeny.</p>
    @else
        <div class="list-group">
            @foreach($steps as $step)
                <div class="list-group-item flex justify-between items-start">
                    <div>
                        <div class="flex items-center gap-2">
                            @if($step->completed_at)
                                <i class="bi bi-check-circle-fill text-success"></i>
                            @elseif($step->is_required)
                                <i class="bi bi-circle text-danger"></i>
                            @else
                                <i class="bi bi-circle text-secondary"></i>
                            @endif
                            <strong>{{ $step->step }}</strong>
                            @if($step->is_required)
                                <span class="badge bg-danger">Povinné</span>
                            @endif
                        </div>
                        @if($step->description)
                            <small class="text-muted ms-4">{{ $step->description }}</small>
                        @endif
                        @if($step->completed_at)
                            <br><small class="text-success ms-4">Dokončeno {{ $step->completed_at->format('d.m.Y H:i') }}</small>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-panel.card>
@endsection
