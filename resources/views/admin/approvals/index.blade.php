@extends('layouts.panel')

@section('title', 'Schvalování (4 oči)')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="Žádosti ke schválení">
        <x-slot name="headerRight">
            <div class="flex gap-1">
                @foreach(['pending' => 'Čekající', 'executed' => 'Provedené', 'rejected' => 'Zamítnuté', 'failed' => 'Selhané'] as $key => $label)
                    <a href="{{ route('admin.approvals.index', ['status' => $key]) }}"
                       class="btn btn-sm {{ $status === $key ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
                @endforeach
            </div>
        </x-slot>

        @error('approval')<div class="alert alert-light-danger f-12">{{ $message }}</div>@enderror

        @if($requests->isEmpty())
            <x-panel.empty-state icon="check-circle" title="Nic ke schválení"
                subtitle="V tomto stavu nejsou žádné žádosti." />
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Akce</th>
                            <th>Zadal</th>
                            <th>Vytvořeno</th>
                            <th>Stav</th>
                            <th class="text-right">Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($requests as $req)
                            <tr>
                                <td>
                                    <span class="f-w-600 f-12">{{ config('approvals.actions.' . $req->action . '.label', $req->action) }}</span>
                                    @if(isset($req->payload['amount_minor']))
                                        <div class="f-light f-11">{{ number_format($req->payload['amount_minor'] / 100, 2, ',', ' ') }} {{ $req->payload['currency'] ?? '' }}</div>
                                    @endif
                                </td>
                                <td class="f-12">{{ $req->requester?->name ?? '—' }}</td>
                                <td class="f-light f-12">{{ $req->created_at->format('d.m.Y H:i') }}</td>
                                <td>
                                    @php($badge = ['pending' => 'warning', 'executed' => 'success', 'approved' => 'success', 'rejected' => 'secondary', 'failed' => 'danger'][$req->status] ?? 'secondary')
                                    <span class="badge badge-light-{{ $badge }}">{{ $req->status }}</span>
                                    @if($req->status === 'failed' && $req->failure_reason)
                                        <div class="f-light f-11 txt-danger">{{ $req->failure_reason }}</div>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if($req->isPending())
                                        <div class="flex gap-1 justify-end">
                                            <form method="POST" action="{{ route('admin.approvals.approve', $req) }}"
                                                  data-confirm="Schválit a provést tuto akci? Nelze schválit vlastní žádost.">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success text-white">Schválit</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.approvals.reject', $req) }}"
                                                  data-confirm="Zamítnout tuto žádost?">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Zamítnout</button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="f-light f-11">{{ $req->reviewer?->name ? 'Vyřídil ' . $req->reviewer->name : '—' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $requests->links() }}</div>
        @endif
    </x-panel.card>
</div>
@endsection
