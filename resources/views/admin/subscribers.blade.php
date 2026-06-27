@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Odběratelé';
    $breadcrumbItems = ['Odběratelé' => ''];
@endphp

@section('title', 'Odběratelé newsletteru')

@section('content')
<div class="container-fluid">
    <div class="container subscribed-user">
        <div class="grid grid-cols-12 card-gap">
            <div class="col-span-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top">
                            <h5>Odběratelé newsletteru</h5>
                            <div class="card-header-right-icon">
                                <button class="btn btn-primary btn-sm text-white" data-bs-toggle="modal" data-bs-target="#addSubscriberModal">
                                    <i data-feather="plus" style="width:13px;height:13px;"></i> Přidat odběratele
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body pt-0 px-0">
                        <div class="subscribed-user-wrapper">
                            <div class="recent-table overflow-x-auto custom-scrollbar">
                                <table class="table" id="subscribed-user-wrapper">
                                    <thead>
                                        <tr>
                                            <th></th>
                                            <th><span class="f-light font-semibold">E-mail</span></th>
                                            <th><span class="f-light font-semibold">Jméno</span></th>
                                            <th><span class="f-light font-semibold">Datum odběru</span></th>
                                            <th><span class="f-light font-semibold">Stav</span></th>
                                            <th><span class="f-light font-semibold">Akce</span></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($subscribers ?? [] as $sub)
                                        <tr class="inbox-data">
                                            <td></td>
                                            <td class="f-w-500">{{ $sub->email }}</td>
                                            <td class="f-light">{{ $sub->name ?? '—' }}</td>
                                            <td class="f-12">{{ $sub->created_at?->format('d.m.Y H:i') }}</td>
                                            <td>
                                                @if($sub->is_active ?? true)
                                                    <span class="badge badge-light-success">Odebírá</span>
                                                @else
                                                    <span class="badge badge-light-danger">Odhlášen</span>
                                                @endif
                                            </td>
                                            <td>
                                                <a class="square-white" href="#" data-bs-toggle="tooltip" data-tooltip="Přepnout stav">
                                                    <i data-feather="toggle-left" style="width:14px;height:14px;"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-5 f-light">
                                                <i data-feather="mail" style="width:40px;height:40px;" class="text-muted d-block mx-auto mb-3"></i>
                                                Žádní odběratelé newsletteru.<br>
                                                <small>Zákazníci se mohou přihlásit k odběru na veřejném webu.</small>
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addSubscriberModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Přidat odběratele</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body custom-input">
                <div class="mb-3"><label class="form-label">E-mail</label><input type="email" class="form-control"></div>
                <div class="mb-3"><label class="form-label">Jméno</label><input type="text" class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušit</button><button type="button" class="btn btn-primary text-white" data-bs-dismiss="modal">Přidat</button></div>
        </div>
    </div>
</div>
@endsection
