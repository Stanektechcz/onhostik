@extends('layouts.panel')

@section('title', 'Onboarding — ' . $customer->display_name)

@section('content')
<div class="container-fluid">
    <x-panel.card title="Onboarding checklist — {{ $customer->display_name }}">
        <div class="mb-3">
            <div class="flex justify-between mb-1">
                <span class="f-12 text-muted">Dokončeno {{ $progress }}%</span>
            </div>
            <div class="progress" style="height:10px">
                <div class="progress-bar {{ $progress === 100 ? 'bg-success' : 'bg-primary' }}" style="width:{{ $progress }}%"></div>
            </div>
        </div>

        <ul class="list-group list-group-flush">
            @foreach($checklist as $item)
            <li class="list-group-item flex items-center gap-3 px-0">
                @if($item['done'])
                    <i class="ti ti-circle-check text-success f-20"></i>
                @else
                    <i class="ti ti-circle-x text-muted f-20"></i>
                @endif
                <span class="{{ $item['done'] ? '' : 'text-muted' }}">{{ $item['label'] }}</span>
                @if($item['done'])
                    <span class="badge bg-success ms-auto">Hotovo</span>
                @else
                    <span class="badge bg-light text-muted ms-auto">Čeká</span>
                @endif
            </li>
            @endforeach
        </ul>

        <div class="mt-3">
            <a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-outline-secondary btn-sm">Zpět na zákazníka</a>
        </div>
    </x-panel.card>
</div>
@endsection
