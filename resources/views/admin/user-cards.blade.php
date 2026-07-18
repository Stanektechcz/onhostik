@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Uživatelé — Karty';
    $breadcrumbItems = ['Uživatelé' => route('admin.users.index'), 'Karty' => ''];
@endphp

@section('title', 'Uživatelé — Karty')

@section('content')
<div class="container-fluid">
    <div class="container social-user-cards">
        <div class="flex justify-end mb-3 gap-2">
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-primary btn-sm">
                <i data-feather="list" style="width:13px;height:13px;"></i> Tabulkový pohled
            </a>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm">
                <i data-feather="user-plus" style="width:13px;height:13px;"></i> Přidat uživatele
            </a>
        </div>

        <div class="grid grid-cols-12 card-gap">
            @forelse($users as $user)
            <div class="col-span-3 xl:col-span-4 md:col-span-6 sm:col-span-12">
                <div class="card social-profile">
                    <div class="card-body">
                        <div class="social-img-wrap">
                            <div class="social-img" style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,rgba(var(--theme-default),.2),rgba(var(--theme-default),.05));display:flex;align-items:center;justify-content:center;margin:0 auto;">
                                <span class="f-w-700 f-20" style="color:rgba(var(--theme-default),1);">
                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                </span>
                            </div>
                        </div>
                        <div class="social-details text-center mt-3">
                            <h5 class="mb-1">{{ $user->name }}</h5>
                            <p class="f-light f-12 mb-2">{{ $user->email }}</p>
                            <div class="mb-2">
                                @foreach($user->getRoleNames() as $r)
                                    <span class="badge badge-light-primary me-1">{{ $r }}</span>
                                @endforeach
                                @if($user->getRoleNames()->isEmpty())
                                    <span class="badge badge-light-secondary">zákazník</span>
                                @endif
                            </div>
                            <p class="f-12 f-light">Od {{ $user->created_at?->format('d.m.Y') }}</p>
                        </div>
                        <div class="card-social flex justify-center gap-2 mt-3">
                            <div class="social-follow text-center">
                                <h5>{{ $user->customer?->orders()->count() ?? 0 }}</h5>
                                <h6 class="f-light">Objednávek</h6>
                            </div>
                            <div class="social-follow text-center">
                                <h5>{{ $user->customer?->invoices()->count() ?? 0 }}</h5>
                                <h6 class="f-light">Faktur</h6>
                            </div>
                            <div class="social-follow text-center">
                                <h5>{{ $user->customer?->services()->count() ?? 0 }}</h5>
                                <h6 class="f-light">Služeb</h6>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-3">
                            <a href="{{ route('admin.users.edit', $user) }}"
                               class="btn btn-outline-primary btn-sm w-full">Upravit</a>
                            @if($user->customer)
                            <a href="{{ route('admin.customers.show', $user->customer) }}"
                               class="btn btn-primary btn-sm w-full text-white">Detail</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-span-12">
                <div class="card"><div class="card-body text-center py-5 f-light">Žádní uživatelé.</div></div>
            </div>
            @endforelse
        </div>

        @if(method_exists($users, 'links'))
            <div class="mt-4">{{ $users->links() }}</div>
        @endif
    </div>
</div>
@endsection
