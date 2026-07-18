@extends('layouts.panel')

@section('title', 'Plánované údržby')

@section('content')
<div class="grid grid-cols-12 justify-center">
    <div class="col-span-12 lg:col-span-10">
        <x-panel.flash />

        <x-panel.card title="Plánované údržby">
            @if($windows->isEmpty())
                <div class="text-center py-4">
                    <p class="text-muted mb-0">Žádné plánované údržby.</p>
                    <small class="text-muted">V tuto chvíli nejsou naplánované žádné servisní okna.</small>
                </div>
            @else
            <div class="grid grid-cols-12 gap-3">
                @foreach($windows as $window)
                <div class="col-span-12 md:col-span-6">
                    <div class="border rounded p-3">
                        <div class="flex justify-between items-start mb-2">
                            <h6 class="mb-0 f-w-600">{{ $window->title }}</h6>
                            @php
                                $statusColors = [
                                    'scheduled'   => 'warning',
                                    'in_progress' => 'primary',
                                ];
                                $stc = $statusColors[$window->status] ?? 'secondary';
                                $statusLabels = [
                                    'scheduled'   => 'Naplánováno',
                                    'in_progress' => 'Probíhá',
                                ];
                                $stl = $statusLabels[$window->status] ?? ucfirst($window->status);
                            @endphp
                            <span class="badge bg-{{ $stc }}">{{ $stl }}</span>
                        </div>

                        @if($window->server_id)
                        <div class="f-12 text-muted mb-2">
                            Server ID: <strong>{{ $window->server_id }}</strong>
                        </div>
                        @endif

                        <div class="grid grid-cols-12 gap-1 f-12">
                            <div class="col-span-6">
                                <span class="text-muted">Začátek:</span><br>
                                <strong>{{ $window->starts_at?->format('d.m.Y H:i') ?? '—' }}</strong>
                            </div>
                            <div class="col-span-6">
                                <span class="text-muted">Konec:</span><br>
                                <strong>{{ $window->ends_at?->format('d.m.Y H:i') ?? '—' }}</strong>
                            </div>
                        </div>

                        @if($window->description ?? false)
                        <div class="mt-2 f-12 text-muted">
                            {{ Str::limit($window->description, 120) }}
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            <div class="mt-3">{{ $windows->links() }}</div>
            @endif
        </x-panel.card>
    </div>
</div>
@endsection
