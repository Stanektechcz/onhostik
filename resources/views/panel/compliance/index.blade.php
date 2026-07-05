@extends('layouts.panel')

@php
    $breadcrumbTitle = 'GDPR & Ochrana dat';
    $breadcrumbItems = ['Účet' => '#', 'GDPR & Ochrana dat' => ''];
@endphp

@section('title', 'GDPR & Ochrana dat')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="row g-4">

        {{-- Previous Requests --}}
        <div class="col-lg-8">
            <div class="card card-no-border">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Moje GDPR žádosti</h5>
                    <span class="f-light f-12">{{ $requests->count() }} žádostí</span>
                </div>
                <div class="card-body p-0">
                    @if($requests->isEmpty())
                        <div class="p-3 text-muted small">Žádné GDPR žádosti.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Typ</th>
                                        <th>Stav</th>
                                        <th>Poznámka</th>
                                        <th>Datum</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($requests as $req)
                                    <tr>
                                        <td>
                                            <span class="badge bg-{{ $req->type->color() }}">
                                                {{ $req->type->label() }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $req->status->color() }}">
                                                {{ $req->status->label() }}
                                            </span>
                                        </td>
                                        <td class="text-muted small">{{ $req->admin_note ?? '—' }}</td>
                                        <td class="text-muted small">{{ $req->created_at->format('d.m.Y') }}</td>
                                        <td>
                                            @if($req->type->value === 'export' && $req->isCompleted())
                                                <a href="{{ route('panel.compliance.download', auth()->user()->customer) }}"
                                                   class="btn btn-xs btn-outline-info btn-sm">
                                                    Stáhnout JSON
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Actions Sidebar --}}
        <div class="col-lg-4">

            {{-- Data Export --}}
            <div class="card card-no-border mb-4">
                <div class="card-header"><h6 class="mb-0">Export osobních dat</h6></div>
                <div class="card-body">
                    <p class="small text-muted">
                        Požádejte o export všech vašich osobních dat uložených v systému ve formátu JSON (GDPR čl. 20).
                    </p>
                    <form method="POST" action="{{ route('panel.compliance.export') }}">
                        @csrf
                        <button type="submit" class="btn btn-info btn-sm w-100">
                            Požádat o export dat
                        </button>
                    </form>
                    @error('type')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- Account Deletion --}}
            <div class="card card-no-border border-danger">
                <div class="card-header bg-danger bg-opacity-10">
                    <h6 class="mb-0 text-danger">Smazání účtu</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted">
                        Požádejte o trvalé smazání vašeho účtu a všech osobních dat (GDPR čl. 17).
                        Žádost bude zpracována do 30 dnů. Aktivní služby musí být nejprve ukončeny.
                    </p>
                    <form method="POST" action="{{ route('panel.compliance.deletion') }}"
                          onsubmit="return confirm('Opravdu chcete požádat o smazání účtu? Tato akce je nevratná.')">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                            Požádat o smazání účtu
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
