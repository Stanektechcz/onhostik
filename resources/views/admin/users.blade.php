@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Uživatelé';
    $breadcrumbItems = ['Uživatelé' => ''];
@endphp

@section('title', 'Uživatelé')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="container user-list-wrapper">
        <div class="grid grid-cols-12 card-gap">

            {{-- Filter/Action row --}}
            <div class="col-span-12">
                <div class="card">
                    <div class="card-body py-2">
                        <form method="GET" action="{{ route('admin.users.index') }}" class="d-flex gap-2 flex-wrap align-items-center">
                            <input type="text" name="q" class="form-control form-control-sm" style="max-width:260px;"
                                   placeholder="Hledat jméno nebo e-mail…" value="{{ $search }}">
                            <select name="role" class="form-select form-select-sm w-auto">
                                <option value="">Všechny role</option>
                                <option value="admin" @selected($role === 'admin')>Admin</option>
                                <option value="customer" @selected($role === 'customer')>Zákazník</option>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">Hledat</button>
                            @if($search || $role)
                                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm">Resetovat</a>
                            @endif
                            <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm ms-auto">
                                <i data-feather="user-plus" style="width:14px;height:14px;"></i> Přidat uživatele
                            </a>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Users table --}}
            <div class="col-span-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top">
                            <h5>Přehled uživatelů</h5>
                            <div class="card-header-right-icon">
                                <a href="{{ route('admin.user-cards') }}" class="btn btn-outline-primary btn-sm">
                                    <i data-feather="grid" style="width:13px;height:13px;"></i> Kartový pohled
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body pt-0 px-0">
                        <div class="list-product user-list-table">
                            <div class="recent-table overflow-x-auto custom-scrollbar">
                                <table class="table" id="roles-permission">
                                    <thead>
                                        <tr>
                                            <th></th>
                                            <th><span class="f-light font-semibold">Uživatel</span></th>
                                            <th><span class="f-light font-semibold">E-mail</span></th>
                                            <th><span class="f-light font-semibold">Role</span></th>
                                            <th><span class="f-light font-semibold">Registrace</span></th>
                                            <th><span class="f-light font-semibold">Stav</span></th>
                                            <th><span class="f-light font-semibold">Akce</span></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($users as $user)
                                        <tr class="product-removes inbox-data">
                                            <td></td>
                                            <td>
                                                <div class="product-names d-flex align-items-center gap-2">
                                                    <div class="light-product-box d-flex align-items-center justify-content-center"
                                                         style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,rgba(var(--theme-default),.15),rgba(var(--theme-default),.05));">
                                                        <span class="f-w-600 f-12" style="color:rgba(var(--theme-default),1);">
                                                            {{ strtoupper(substr($user->name, 0, 2)) }}
                                                        </span>
                                                    </div>
                                                    <span class="f-w-500">{{ $user->name }}</span>
                                                </div>
                                            </td>
                                            <td class="f-light">{{ $user->email }}</td>
                                            <td>
                                                @foreach($user->getRoleNames() as $r)
                                                    <span class="badge badge-light-primary me-1">{{ $r }}</span>
                                                @endforeach
                                                @if($user->getRoleNames()->isEmpty())
                                                    <span class="badge badge-light-secondary">zákazník</span>
                                                @endif
                                            </td>
                                            <td class="f-12">{{ $user->created_at?->format('d.m.Y') }}</td>
                                            <td>
                                                @if($user->is_active ?? true)
                                                    <span class="badge badge-light-success">Aktivní</span>
                                                @else
                                                    <span class="badge badge-light-warning">Neaktivní</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="common-align gap-2 justify-start">
                                                    <a class="square-white" href="{{ route('admin.users.edit', $user) }}"
                                                       data-bs-toggle="tooltip" data-tooltip="Upravit">
                                                        <i data-feather="edit-2" style="width:14px;height:14px;"></i>
                                                    </a>
                                                    <a class="square-white" href="{{ route('admin.customers.show', $user->customer ?? $user->id) }}"
                                                       data-bs-toggle="tooltip" data-tooltip="Detail zákazníka">
                                                        <svg><use href="{{ asset('panel/assets/svg/icon-sprite.svg#fill-view') }}"></use></svg>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-4 f-light">Žádní uživatelé.</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @if(method_exists($users, 'links'))
                            <div class="px-3 pt-2">{{ $users->links() }}</div>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
