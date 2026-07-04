@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Doručení webhooků';
    $breadcrumbItems = ['Odchozí webhooky' => route('admin.outgoing-webhooks.index'), $webhook->name => ''];
@endphp

@section('title', 'Doručení: ' . $webhook->name)

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card :title="'Doručení: ' . $webhook->name">
        <div class="table-responsive">
            <table class="table table-hover f-13">
                <thead>
                    <tr>
                        <th>Událost</th>
                        <th>Status</th>
                        <th>HTTP kód</th>
                        <th>Doručeno</th>
                        <th>Odpověď</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deliveries as $delivery)
                        <tr>
                            <td><code class="f-12">{{ $delivery->event }}</code></td>
                            <td>
                                <span class="badge badge-light-{{ $delivery->status === 'delivered' ? 'success' : ($delivery->status === 'pending' ? 'warning' : 'danger') }}">
                                    {{ $delivery->status }}
                                </span>
                            </td>
                            <td>{{ $delivery->response_code ?? '—' }}</td>
                            <td class="f-light f-12">{{ $delivery->delivered_at?->format('d.m.Y H:i:s') ?? '—' }}</td>
                            <td>
                                @if($delivery->response_body)
                                    <span class="f-11 f-light" title="{{ $delivery->response_body }}">{{ mb_substr($delivery->response_body, 0, 60) }}…</span>
                                @else
                                    <span class="f-light">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="f-light f-13">Žádná doručení.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $deliveries->links() }}
    </x-panel.card>
</div>
@endsection
