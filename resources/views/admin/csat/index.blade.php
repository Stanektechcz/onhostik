@extends('layouts.panel')

@section('title', 'CSAT Dashboard')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 gap-3 mb-4">
        <div class="col-span-12 md:col-span-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="display-6 font-bold text-primary">{{ $overall?->avg_rating ? number_format($overall->avg_rating, 2) : '—' }}</div>
                    <small class="text-muted">Průměrné hodnocení (z 5)</small>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="display-6 font-bold text-success">{{ number_format($overall?->rated_count ?? 0) }}</div>
                    <small class="text-muted">Celkem hodnocení</small>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-3">
            <div class="card text-center">
                <div class="card-body">
                    @php $positive = $overall?->rated_count > 0 ? round($overall->positive_count / $overall->rated_count * 100) : 0 @endphp
                    <div class="display-6 font-bold text-{{ $positive >= 80 ? 'success' : ($positive >= 60 ? 'warning' : 'danger') }}">{{ $positive }}%</div>
                    <small class="text-muted">CSAT skóre (≥4 hvězdy)</small>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-4 mb-4">
        <div class="col-span-12 md:col-span-4">
            <x-panel.card title="Rozdělení hodnocení">
                @foreach($byRating as $r)
                @php $pct = $overall?->rated_count > 0 ? round($r->cnt / $overall->rated_count * 100) : 0 @endphp
                <div class="flex items-center gap-2 mb-2">
                    <div style="width:50px" class="text-right font-semibold">{{ $r->rating }}★</div>
                    <div class="grow">
                        <div class="progress" style="height:16px">
                            <div class="progress-bar {{ $r->rating >= 4 ? 'bg-success' : ($r->rating === 3 ? 'bg-warning' : 'bg-danger') }}" style="width:{{ $pct }}%"></div>
                        </div>
                    </div>
                    <div style="width:50px" class="text-muted small">{{ $r->cnt }}×</div>
                </div>
                @endforeach
            </x-panel.card>
        </div>

        <div class="col-span-12 md:col-span-8">
            <x-panel.card title="Měsíční průměr hodnocení">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Měsíc</th><th class="text-right">Hodnocení</th><th class="text-right">Počet</th></tr></thead>
                        <tbody>
                            @foreach($monthly as $m)
                            <tr>
                                <td>{{ $m->month }}</td>
                                <td class="text-right font-semibold">{{ $m->avg_rating }}</td>
                                <td class="text-right text-muted">{{ $m->rated_count }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-panel.card>
        </div>
    </div>

    <x-panel.card title="Poslední komentáře">
        @forelse($recentComments as $rc)
        <div class="border rounded p-3 mb-2">
            <div class="flex justify-between mb-1">
                <span>@for($i=1;$i<=5;$i++)<span class="{{ $i <= $rc->rating ? 'text-warning' : 'text-muted' }}">★</span>@endfor</span>
                <small class="text-muted">{{ $rc->rated_at ? \Carbon\Carbon::parse($rc->rated_at)->format('d.m.Y') : '' }}</small>
            </div>
            <p class="mb-0 text-muted small">{{ $rc->rating_comment }}</p>
        </div>
        @empty
        <p class="text-muted">Žádné komentáře.</p>
        @endforelse
    </x-panel.card>
</div>
@endsection
