@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Odběratelé';
    $breadcrumbItems = ['Odběratelé' => ''];
@endphp

@section('title', 'Odběratelé newsletteru')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- KPI strip --}}
    <div class="grid grid-cols-12 card-gap mb-1">
        <div class="col-span-4 sm:col-span-12">
            <x-panel.stat-widget label="Celkem" :value="$totalCount" icon="users" color="primary" />
        </div>
        <div class="col-span-4 sm:col-span-12">
            <x-panel.stat-widget label="Aktivní" :value="$activeCount" icon="mail" color="success" />
        </div>
        <div class="col-span-4 sm:col-span-12">
            <x-panel.stat-widget label="Odhlášeni" :value="$unsubscribedCount" icon="user-x" color="danger" />
        </div>
    </div>

    <x-panel.card title="Odběratelé newsletteru">
        <div class="flex gap-2 mb-3 flex-wrap items-center">
            <form method="GET" action="{{ route('admin.subscribers.index') }}" class="flex gap-2 flex-wrap items-center">
                <input type="text" name="q" class="form-control form-control-sm" style="max-width:240px;"
                       placeholder="E-mail nebo jméno…" value="{{ $search }}">
                <select name="status" class="form-select form-select-sm w-auto">
                    <option value="">Všichni</option>
                    <option value="active" @selected($filter === 'active')>Aktivní</option>
                    <option value="unsubscribed" @selected($filter === 'unsubscribed')>Odhlášení</option>
                </select>
                <button type="submit" class="btn btn-outline-primary btn-sm">Filtrovat</button>
                @if($search || $filter)
                    <a href="{{ route('admin.subscribers.index') }}" class="btn btn-outline-secondary btn-sm">×</a>
                @endif
            </form>
            <div class="ms-auto flex gap-2">
                <a href="{{ route('admin.subscribers.export') }}" class="btn btn-outline-success btn-sm">
                    <i data-feather="download" style="width:13px;height:13px;"></i> CSV
                </a>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSubscriberModal">
                    <i data-feather="plus" style="width:13px;height:13px;"></i> Přidat
                </button>
            </div>
        </div>

        @if($subscribers->isEmpty())
            <div class="text-center py-5">
                <i data-feather="mail" style="width:40px;height:40px;" class="text-muted mb-3"></i>
                <h6 class="f-light mt-2">Žádní odběratelé</h6>
                <p class="f-light f-12 mb-0">Odběratelé se zobrazí zde po přihlášení na webu nebo ručním přidání.</p>
            </div>
        @else
            <x-panel.data-table :headers="['E-mail', 'Jméno', 'Jazyk', 'Zdroj', 'Přihlášen', 'Stav', '']">
                @foreach($subscribers as $sub)
                    <tr>
                        <td class="f-w-500">{{ $sub->email }}</td>
                        <td class="f-light">{{ $sub->name ?: '—' }}</td>
                        <td class="f-12">{{ strtoupper($sub->locale) }}</td>
                        <td class="f-12">{{ $sub->source }}</td>
                        <td class="f-12">{{ $sub->created_at?->format('d.m.Y') }}</td>
                        <td>
                            @if($sub->is_active)
                                <span class="badge badge-light-success">Aktivní</span>
                            @else
                                <span class="badge badge-light-danger">Odhlášen</span>
                            @endif
                        </td>
                        <td>
                            <div class="flex gap-1">
                                <form method="POST" action="{{ route('admin.subscribers.toggle', $sub) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-{{ $sub->is_active ? 'warning' : 'success' }} btn-xs"
                                            title="{{ $sub->is_active ? 'Odhlásit' : 'Reaktivovat' }}">
                                        <i data-feather="{{ $sub->is_active ? 'user-x' : 'user-check' }}" style="width:11px;height:11px;"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.subscribers.destroy', $sub) }}"
                                      onsubmit="return confirm('Smazat odběratele {{ $sub->email }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-xs">
                                        <i data-feather="trash-2" style="width:11px;height:11px;"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-panel.data-table>
            {{ $subscribers->withQueryString()->links() }}
        @endif
    </x-panel.card>
</div>

{{-- Add subscriber modal --}}
<div class="modal fade" id="addSubscriberModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.subscribers.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Přidat odběratele</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body custom-input">
                    <div class="mb-3">
                        <label class="form-label">E-mail *</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                               required placeholder="email@example.com">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jméno</label>
                        <input type="text" name="name" class="form-control" placeholder="Volitelné">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Jazyk</label>
                        <select name="locale" class="form-select">
                            <option value="cs">Čeština</option>
                            <option value="en">English</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušit</button>
                    <button type="submit" class="btn btn-primary">Přidat odběratele</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
