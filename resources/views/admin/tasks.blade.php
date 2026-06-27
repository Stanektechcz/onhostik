@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Úkoly';
    $breadcrumbItems = ['Úkoly' => ''];
@endphp

@section('title', 'Úkoly')

@section('content')
<div class="container-fluid">
    <div class="container main-tasks">
        <div class="email-wrap bookmark-wrap">
            <div class="grid grid-cols-12 card-gap">

                {{-- Left sidebar --}}
                <div class="col-span-3 xl:col-span-12 xl-40 box-col-3e">
                    <div class="email-app-sidebar left-bookmark task-sidebar">
                        <div class="mb-3">
                            <div style="width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,rgba(var(--theme-default),.2),rgba(var(--theme-default),.05));display:flex;align-items:center;justify-content:center;margin:0 auto 8px;">
                                <i data-feather="user" style="width:28px;height:28px;color:rgba(var(--theme-default),1);"></i>
                            </div>
                            <h6 class="text-center">Admin OnHost</h6>
                            <p class="f-light f-12 text-center">admin@onhost.cz</p>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <label class="form-label f-12 f-light">Stav</label>
                            <select class="form-select form-select-sm">
                                <option>Vše</option>
                                <option>Dokončené</option>
                                <option>Čekající</option>
                                <option>Probíhající</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label f-12 f-light">Důležitost</label>
                            <select class="form-select form-select-sm">
                                <option>Vše</option>
                                <option>Vysoká</option>
                                <option>Střední</option>
                                <option>Nízká</option>
                            </select>
                        </div>
                        <hr>
                        <div class="task-stats">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="f-light f-12">Celkem úkolů</span>
                                <span class="f-w-600">{{ $taskStats['total'] ?? 0 }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="f-light f-12">Dokončeno</span>
                                <span class="badge badge-light-success">{{ $taskStats['done'] ?? 0 }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="f-light f-12">Probíhá</span>
                                <span class="badge badge-light-warning">{{ $taskStats['inprogress'] ?? 0 }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="f-light f-12">Čekající</span>
                                <span class="badge badge-light-danger">{{ $taskStats['pending'] ?? 0 }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right: task table --}}
                <div class="col-span-9 xl:col-span-12 xl-80 box-col-9e">
                    <div class="card">
                        <div class="card-header card-no-border">
                            <div class="header-top">
                                <h5>Správa úkolů</h5>
                                <div class="card-header-right-icon">
                                    <button class="btn btn-primary btn-sm text-white"
                                            data-bs-toggle="modal" data-bs-target="#newTaskModal">
                                        <i data-feather="plus" style="width:13px;height:13px;"></i> Nový úkol
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body pt-0 px-0">
                            <div class="common-task-table">
                                <div class="recent-table overflow-x-auto custom-scrollbar">
                                    <table class="table" id="main-task-table">
                                        <thead>
                                            <tr>
                                                <th><span class="f-light font-semibold">Úkol</span></th>
                                                <th><span class="f-light font-semibold">Termín</span></th>
                                                <th><span class="f-light font-semibold">Stav</span></th>
                                                <th><span class="f-light font-semibold">Priorita</span></th>
                                                <th><span class="f-light font-semibold">Akce</span></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach([
                                                ['Kontrola SSL certifikátů', '30.6.2026', 'pending', 'high'],
                                                ['Aktualizace serverů na PHP 8.3', '15.7.2026', 'inprogress', 'medium'],
                                                ['Záloha databází zákazníků', '1.7.2026', 'pending', 'high'],
                                                ['Implementace 2FA pro admin', '10.7.2026', 'inprogress', 'medium'],
                                                ['Audit partnerských provizí', '5.7.2026', 'done', 'low'],
                                                ['Příprava ceníku Q3/2026', '20.7.2026', 'pending', 'low'],
                                            ] as [$title, $due, $status, $priority])
                                            <tr class="inbox-data">
                                                <td class="f-w-500">{{ $title }}</td>
                                                <td class="f-12 f-light">{{ $due }}</td>
                                                <td>
                                                    @if($status === 'done')
                                                        <span class="badge badge-light-success">Dokončeno</span>
                                                    @elseif($status === 'inprogress')
                                                        <span class="badge badge-light-primary">Probíhá</span>
                                                    @else
                                                        <span class="badge badge-light-warning">Čeká</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($priority === 'high')
                                                        <span class="badge badge-light-danger">Vysoká</span>
                                                    @elseif($priority === 'medium')
                                                        <span class="badge badge-light-warning">Střední</span>
                                                    @else
                                                        <span class="badge badge-light-secondary">Nízká</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="common-align gap-2 justify-start">
                                                        <a class="square-white" href="#"><i data-feather="edit-2" style="width:13px;height:13px;"></i></a>
                                                        <a class="square-white trash-3" href="#"><svg><use href="{{ asset('panel/assets/svg/icon-sprite.svg#trash1') }}"></use></svg></a>
                                                    </div>
                                                </td>
                                            </tr>
                                            @endforeach
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
</div>

<div class="modal fade" id="newTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nový úkol</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body custom-input">
                <div class="mb-3"><label class="form-label">Název</label><input type="text" class="form-control"></div>
                <div class="mb-3"><label class="form-label">Termín</label><input type="date" class="form-control"></div>
                <div class="mb-3">
                    <label class="form-label">Priorita</label>
                    <select class="form-select"><option>Nízká</option><option>Střední</option><option>Vysoká</option></select>
                </div>
                <div class="mb-3"><label class="form-label">Popis</label><textarea class="form-control" rows="3"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušit</button>
                <button type="button" class="btn btn-primary text-white" data-bs-dismiss="modal">Přidat</button>
            </div>
        </div>
    </div>
</div>
@endsection
