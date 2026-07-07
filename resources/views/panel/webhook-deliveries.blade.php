@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Doručení webhooků';
    $breadcrumbItems = [
        'Vývojář'    => route('panel.developer.index'),
        'Webhooky'   => route('panel.webhooks.index'),
        $webhook->name => '',
    ];
@endphp

@section('title', 'Doručení — ' . $webhook->name)

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="card card-no-border">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0">{{ $webhook->name }}</h5>
                <span class="f-12 f-light text-truncate">{{ $webhook->url }}</span>
            </div>
            <a href="{{ route('panel.webhooks.index') }}" class="btn btn-outline-secondary btn-sm">
                <i data-feather="arrow-left" style="width:12px;height:12px;"></i>
                Zpět
            </a>
        </div>
        <div class="card-body">
            @if($deliveries->isEmpty())
                <p class="f-light f-12 mb-0">Žádná doručení zatím.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Událost</th>
                                <th>Stav</th>
                                <th>HTTP kód</th>
                                <th>Doručeno</th>
                                <th>Čas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($deliveries as $delivery)
                                <tr>
                                    <td><code class="f-11">{{ $delivery->event }}</code></td>
                                    <td>
                                        @if($delivery->status === 'success')
                                            <span class="badge badge-light-success f-10">Úspěch</span>
                                        @elseif($delivery->status === 'pending')
                                            <span class="badge badge-light-warning f-10">Čeká</span>
                                        @else
                                            <span class="badge badge-light-danger f-10">Chyba</span>
                                        @endif
                                    </td>
                                    <td class="f-12">{{ $delivery->response_code ?? '—' }}</td>
                                    <td class="f-12">{{ $delivery->delivered_at?->format('d.m.Y H:i:s') ?? '—' }}</td>
                                    <td class="f-light f-12">{{ $delivery->created_at?->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    {{ $deliveries->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
