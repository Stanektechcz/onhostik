@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Úkoly';
    $breadcrumbItems = ['Úkoly' => ''];
@endphp

@section('title', 'Správa úkolů')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="container main-tasks">
        <div class="email-wrap bookmark-wrap">
            <div class="grid grid-cols-12 card-gap">

                {{-- Left sidebar --}}
                <div class="col-span-3 xl:col-span-12 xl-40 box-col-3e">
                    <div class="email-app-sidebar left-bookmark task-sidebar">
                        <div class="mb-3">
                            <div style="width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,rgba(var(--theme-default),.2),rgba(var(--theme-default),.05));display:flex;align-items:center;justify-content:center;margin:0 auto 8px;">
                                <i data-feather="check-square" style="width:28px;height:28px;color:rgba(var(--theme-default),1);"></i>
                            </div>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <label class="form-label f-12 f-light">Filtr stavu</label>
                            <div class="d-flex flex-column gap-1">
                                <a href="{{ route('admin.tasks') }}" class="btn btn-sm {{ $status === '' ? 'btn-primary text-white' : 'btn-outline-secondary' }}">Vše ({{ $taskStats['total'] }})</a>
                                <a href="{{ route('admin.tasks', ['status' => 'pending']) }}" class="btn btn-sm {{ $status === 'pending' ? 'btn-warning text-white' : 'btn-outline-warning' }}">
                                    Čekající ({{ $taskStats['pending'] }})
                                </a>
                                <a href="{{ route('admin.tasks', ['status' => 'inprogress']) }}" class="btn btn-sm {{ $status === 'inprogress' ? 'btn-primary text-white' : 'btn-outline-primary' }}">
                                    Probíhá ({{ $taskStats['inprogress'] }})
                                </a>
                                <a href="{{ route('admin.tasks', ['status' => 'done']) }}" class="btn btn-sm {{ $status === 'done' ? 'btn-success text-white' : 'btn-outline-success' }}">
                                    Dokončeno ({{ $taskStats['done'] }})
                                </a>
                            </div>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <label class="form-label f-12 f-light">Filtr priority</label>
                            <div class="d-flex flex-column gap-1">
                                <a href="{{ route('admin.tasks', ['priority' => 'high'] + ($status ? ['status' => $status] : [])) }}"
                                   class="btn btn-sm {{ $priority === 'high' ? 'btn-danger text-white' : 'btn-outline-danger' }}">Vysoká</a>
                                <a href="{{ route('admin.tasks', ['priority' => 'medium'] + ($status ? ['status' => $status] : [])) }}"
                                   class="btn btn-sm {{ $priority === 'medium' ? 'btn-warning text-white' : 'btn-outline-warning' }}">Střední</a>
                                <a href="{{ route('admin.tasks', ['priority' => 'low'] + ($status ? ['status' => $status] : [])) }}"
                                   class="btn btn-sm {{ $priority === 'low' ? 'btn-secondary text-white' : 'btn-outline-secondary' }}">Nízká</a>
                            </div>
                        </div>
                        <hr>
                        <div class="task-stats">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="f-light f-12">Celkem úkolů</span>
                                <span class="f-w-600">{{ $taskStats['total'] }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="f-light f-12">Dokončeno</span>
                                <span class="badge badge-light-success">{{ $taskStats['done'] }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="f-light f-12">Probíhá</span>
                                <span class="badge badge-light-warning">{{ $taskStats['inprogress'] }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="f-light f-12">Čekající</span>
                                <span class="badge badge-light-danger">{{ $taskStats['pending'] }}</span>
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
                                                <th><span class="f-light font-semibold">Přiřazen</span></th>
                                                <th><span class="f-light font-semibold">Stav</span></th>
                                                <th><span class="f-light font-semibold">Priorita</span></th>
                                                <th><span class="f-light font-semibold">Akce</span></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($tasks as $task)
                                            <tr class="inbox-data {{ $task->isDone() ? 'opacity-50' : '' }}">
                                                <td>
                                                    <div class="f-w-500 {{ $task->isDone() ? 'text-decoration-line-through' : '' }}">{{ $task->title }}</div>
                                                    @if($task->description)
                                                        <div class="f-light f-12 mt-1">{{ Str::limit($task->description, 80) }}</div>
                                                    @endif
                                                </td>
                                                <td class="f-12">
                                                    @if($task->due_date)
                                                        <span class="{{ $task->isOverdue() ? 'text-danger f-w-600' : 'f-light' }}">
                                                            {{ $task->due_date->format('d.m.Y') }}
                                                            @if($task->isOverdue()) <i data-feather="alert-triangle" style="width:12px;height:12px;"></i> @endif
                                                        </span>
                                                    @else
                                                        <span class="f-light">—</span>
                                                    @endif
                                                </td>
                                                <td class="f-12">
                                                    {{ $task->assignee?->name ?? '—' }}
                                                </td>
                                                <td>
                                                    <form method="POST" action="{{ route('admin.tasks.toggle', $task) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="badge badge-light-{{ $task->statusColor() }} border-0"
                                                                style="cursor:pointer;" title="Kliknutím změnit stav">
                                                            {{ $task->statusLabel() }}
                                                        </button>
                                                    </form>
                                                </td>
                                                <td>
                                                    <span class="badge badge-light-{{ $task->priorityColor() }}">
                                                        {{ $task->priorityLabel() }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="common-align gap-2 justify-start">
                                                        <button class="square-white" type="button"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#editTaskModal{{ $task->id }}"
                                                                title="Upravit">
                                                            <i data-feather="edit-2" style="width:14px;height:14px;"></i>
                                                        </button>
                                                        <form method="POST" action="{{ route('admin.tasks.destroy', $task) }}" class="d-inline"
                                                              onsubmit="return confirm('Smazat úkol?')">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" class="square-white trash-3" title="Smazat">
                                                                <svg><use href="{{ asset('panel/svg/icon-sprite.svg#trash1') }}"></use></svg>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>

                                            {{-- Edit modal per task --}}
                                            <div class="modal fade" id="editTaskModal{{ $task->id }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Upravit úkol</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST" action="{{ route('admin.tasks.update', $task) }}">
                                                            @csrf @method('PUT')
                                                            <div class="modal-body custom-input">
                                                                <div class="mb-3"><label class="form-label">Název</label>
                                                                    <input type="text" name="title" class="form-control" value="{{ $task->title }}" required></div>
                                                                <div class="mb-3"><label class="form-label">Popis</label>
                                                                    <textarea name="description" class="form-control" rows="3">{{ $task->description }}</textarea></div>
                                                                <div class="grid grid-cols-12 gap-3">
                                                                    <div class="col-span-6 sm:col-span-12">
                                                                        <label class="form-label">Termín</label>
                                                                        <input type="date" name="due_date" class="form-control" value="{{ $task->due_date?->format('Y-m-d') }}">
                                                                    </div>
                                                                    <div class="col-span-6 sm:col-span-12">
                                                                        <label class="form-label">Priorita</label>
                                                                        <select name="priority" class="form-select">
                                                                            <option value="low" @selected($task->priority === 'low')>Nízká</option>
                                                                            <option value="medium" @selected($task->priority === 'medium')>Střední</option>
                                                                            <option value="high" @selected($task->priority === 'high')>Vysoká</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div class="mt-3">
                                                                    <label class="form-label">Stav</label>
                                                                    <select name="status" class="form-select">
                                                                        <option value="pending" @selected($task->status === 'pending')>Čeká</option>
                                                                        <option value="inprogress" @selected($task->status === 'inprogress')>Probíhá</option>
                                                                        <option value="done" @selected($task->status === 'done')>Dokončeno</option>
                                                                    </select>
                                                                </div>
                                                                <div class="mt-3">
                                                                    <label class="form-label">Přiřadit adminovi</label>
                                                                    <select name="assigned_to" class="form-select">
                                                                        <option value="">— nepřiřazeno —</option>
                                                                        @foreach($admins as $admin)
                                                                            <option value="{{ $admin->id }}" @selected($task->assigned_to === $admin->id)>{{ $admin->name }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušit</button>
                                                                <button type="submit" class="btn btn-primary text-white">Uložit</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            @empty
                                            <tr>
                                                <td colspan="6" class="text-center py-4 f-light">Žádné úkoly.</td>
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
</div>

{{-- New task modal --}}
<div class="modal fade" id="newTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nový úkol</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('admin.tasks.store') }}">
                @csrf
                <div class="modal-body custom-input">
                    <div class="mb-3"><label class="form-label">Název *</label>
                        <input type="text" name="title" class="form-control" required placeholder="Název úkolu"></div>
                    <div class="mb-3"><label class="form-label">Popis</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Volitelný popis…"></textarea></div>
                    <div class="grid grid-cols-12 gap-3">
                        <div class="col-span-6 sm:col-span-12">
                            <label class="form-label">Termín</label>
                            <input type="date" name="due_date" class="form-control" min="{{ now()->format('Y-m-d') }}">
                        </div>
                        <div class="col-span-6 sm:col-span-12">
                            <label class="form-label">Priorita</label>
                            <select name="priority" class="form-select">
                                <option value="low">Nízká</option>
                                <option value="medium" selected>Střední</option>
                                <option value="high">Vysoká</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Přiřadit adminovi</label>
                        <select name="assigned_to" class="form-select">
                            <option value="">— nepřiřazeno —</option>
                            @foreach($admins as $admin)
                                <option value="{{ $admin->id }}">{{ $admin->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušit</button>
                    <button type="submit" class="btn btn-primary text-white">Přidat úkol</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
