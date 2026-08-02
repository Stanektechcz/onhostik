@extends('layouts.panel')

@section('title', 'Věrnostní program')

@section('content')
<div class="container py-4">

    <h2 class="mb-4">Věrnostní program</h2>

    <x-panel.flash />

    {{-- Points balance + tier --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <div class="f-w-600">
                        Věrnostní body
                        <span class="badge {{ $tier['tier']->badgeClass() }} ms-2">{{ $tier['tier']->label() }}</span>
                    </div>
                    <div class="text-muted small">
                        Získávejte body za zaplacené faktury a uplatněte je za kredit.
                        @if($tier['tier']->discountPercent() > 0)
                            Vaše úroveň zahrnuje slevu {{ $tier['tier']->discountPercent() }} %.
                        @endif
                    </div>
                </div>
                <div class="text-right">
                    <div class="f-w-700" style="font-size:1.6rem">{{ number_format($pointsBalance, 0, ',', ' ') }}</div>
                    <div class="text-muted small">bodů k uplatnění</div>
                </div>
            </div>

            @if($tier['next'] !== null)
                <div class="flex justify-between small mb-1">
                    <span>Do úrovně {{ $tier['next']->label() }}</span>
                    <span>{{ number_format($tier['to_next'], 0, ',', ' ') }} bodů</span>
                </div>
                <div class="progress" style="height:14px">
                    <div class="progress-bar bg-primary" role="progressbar" style="width:{{ $tier['percent'] }}%"
                         aria-valuenow="{{ $tier['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                        {{ $tier['percent'] }} %
                    </div>
                </div>
                <div class="text-muted small mt-1">
                    Celkem získáno za celou dobu: {{ number_format($tier['lifetime'], 0, ',', ' ') }} bodů
                    (uplatnění bodů úroveň nesnižuje).
                </div>
            @else
                <div class="text-muted small">Máte nejvyšší úroveň — děkujeme za přízeň!</div>
            @endif
        </div>
    </div>

    {{-- Next milestone progress --}}
    @if($next)
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Další milník: <strong>{{ $next['milestone']->name }}</strong></h5>
            <p class="text-muted mb-2 small">{{ $next['milestone']->description }}</p>
            <div class="flex justify-between small mb-1">
                <span>Podmínka: {{ $next['milestone']->triggerLabel() }}</span>
                <span>{{ $next['current'] }} / {{ $next['target'] }}</span>
            </div>
            <div class="progress" style="height:18px">
                <div class="progress-bar bg-warning"
                     role="progressbar"
                     style="width:{{ $next['percent'] }}%"
                     aria-valuenow="{{ $next['percent'] }}"
                     aria-valuemin="0"
                     aria-valuemax="100">
                    {{ $next['percent'] }} %
                </div>
            </div>
            <div class="mt-2 small text-muted">
                Odměna po dosažení: <strong>{{ $next['milestone']->rewardLabel() }}</strong>
            </div>
        </div>
    </div>
    @else
    <div class="alert alert-success mb-4">
        Gratulujeme! Dosáhli jste všech dostupných věrnostních milníků.
    </div>
    @endif

    {{-- Earned rewards --}}
    <div class="card">
        <div class="card-header"><strong>Vaše odměny</strong></div>
        @if($rewards->isEmpty())
            <div class="card-body text-muted">Zatím žádné odměny. Plňte milníky a získejte výhody!</div>
        @else
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Milník</th>
                        <th>Odměna</th>
                        <th>Datum</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rewards as $r)
                    <tr>
                        <td>
                            <strong>{{ $r->milestone?->name ?? '—' }}</strong>
                            @if($r->milestone?->description)
                                <div class="text-muted small">{{ $r->milestone->description }}</div>
                            @endif
                        </td>
                        <td class="small">{{ $r->milestone?->rewardLabel() ?? '—' }}</td>
                        <td class="small text-muted">{{ $r->awarded_at?->format('d.m.Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- Redeemable reward catalog --}}
    @if($catalog->isNotEmpty())
    <div class="card mt-4">
        <div class="card-header"><strong>Katalog odměn</strong></div>
        <div class="card-body">
            <div class="grid grid-cols-12 gap-3">
                @foreach($catalog as $reward)
                <div class="col-span-12 md:col-span-6">
                    <div class="border rounded p-3 h-full flex flex-col gap-2">
                        <div class="flex items-center justify-between">
                            <span class="f-w-600">{{ $reward->name }}</span>
                            <span class="badge badge-light-primary">{{ number_format($reward->points_cost, 0, ',', ' ') }} b.</span>
                        </div>
                        @if($reward->description)
                            <p class="text-muted small mb-0 grow">{{ $reward->description }}</p>
                        @endif
                        <div class="small text-muted">Získáte: {{ $reward->rewardLabel() }}</div>
                        <form method="POST" action="{{ route('panel.loyalty.redeem', $reward) }}"
                              data-confirm="Uplatnit {{ $reward->points_cost }} bodů za {{ $reward->rewardLabel() }}?">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm w-full" @disabled($pointsBalance < $reward->points_cost)>
                                @if($pointsBalance < $reward->points_cost) Nedostatek bodů @else Uplatnit @endif
                            </button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

</div>
@endsection
