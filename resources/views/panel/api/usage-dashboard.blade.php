@extends('layouts.panel')

@section('title', 'Využití API')

@section('content')
<div class="container-fluid">

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="f-w-700">{{ number_format($totalRequests) }}</h3>
                    <p class="text-muted mb-0">Celkem požadavků</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="f-w-700">{{ number_format($requestsToday) }}</h3>
                    <p class="text-muted mb-0">Dnes</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="f-w-700">{{ number_format($requests7d) }}</h3>
                    <p class="text-muted mb-0">Posledních 7 dní</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="f-w-700 {{ $errorCount > 0 ? 'text-danger' : '' }}">{{ number_format($errorCount) }}</h3>
                    <p class="text-muted mb-0">Chybné požadavky (4xx/5xx)</p>
                </div>
            </div>
        </div>
    </div>

    <x-panel.card title="Denní aktivita — posledních 30 dní">
        <div class="d-flex align-items-end gap-1" style="height:80px">
            @php $maxCnt = max(1, $days->max('cnt')); @endphp
            @foreach($days as $d)
            <div style="flex:1; background:#4361ee{{ $d['cnt'] > 0 ? '' : '22' }}; height:{{ max(4, round($d['cnt']/$maxCnt*76)) }}px"
                 title="{{ $d['day'] }}: {{ $d['cnt'] }} požadavků">
            </div>
            @endforeach
        </div>
        <div class="d-flex justify-content-between mt-1">
            <small class="text-muted">{{ $days->first()['day'] }}</small>
            <small class="text-muted">{{ $days->last()['day'] }}</small>
        </div>
    </x-panel.card>
</div>
@endsection
