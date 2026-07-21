@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Kanban';
    $breadcrumbItems = ['Kanban' => ''];
@endphp

@section('title', 'Kanban Board')

@push('styles')
<style>
.kanban-board { display: flex; gap: 20px; overflow-x: auto; padding: 8px 0 16px; }
.kanban-col { min-width: 300px; max-width: 300px; }
.kanban-col-header { padding: 10px 14px; border-radius: 8px 8px 0 0; font-weight: 600; font-size: 14px; display:flex; align-items:center; justify-content:space-between; }
.kanban-col-body { background: rgba(var(--light-background),.4); border-radius: 0 0 8px 8px; padding: 10px; min-height: 200px; }
.kanban-card { background: #fff; border-radius: 8px; padding: 12px; margin-bottom: 10px; border: 1px solid rgba(var(--light-background),1); }
.kanban-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); }
.kanban-card .card-title { font-size: 13px; font-weight: 600; margin: 0 0 4px; }
.kanban-card .card-meta { font-size: 11px; opacity: .6; margin: 0 0 8px; }
.kanban-card .card-desc { font-size: 12px; opacity: .7; margin-bottom: 8px; }
.kanban-add-btn { text-align: center; padding: 8px; opacity: .5; cursor: pointer; font-size: 12px; border: 1px dashed rgba(var(--light-background),2); border-radius: 6px; margin-top: 6px; }
.kanban-add-btn:hover { opacity: 1; background: rgba(var(--light-background),.3); }
.overdue-chip { color: var(--danger-color); font-size: 10px; font-weight:600; }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="flex gap-2 justify-between items-center mb-3">
        <div>
            <span class="f-light f-12">Celkem: {{ $pending->count() + $inprogress->count() + $done->count() }} úkolů</span>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.tasks') }}" class="btn btn-outline-secondary btn-sm">
                <i data-feather="list" style="width:13px;height:13px;"></i> Seznam
            </a>
            <button class="btn btn-primary btn-sm text-white"
                    data-bs-toggle="modal" data-bs-target="#addKanbanTaskModal">
                <i data-feather="plus" style="width:13px;height:13px;"></i> Přidat úkol
            </button>
        </div>
    </div>

    <div class="kanban-board">
        {{-- ── Čeká (pending) ─────────────────────────────────────── --}}
        <div class="kanban-col">
            <div class="kanban-col-header bg-warning text-white">
                <span>Čeká</span>
                <span class="badge bg-white text-warning">{{ $pending->count() }}</span>
            </div>
            <div class="kanban-col-body" id="col-pending">
                @forelse($pending as $task)
                    @include('admin.partials.kanban-card', ['task' => $task, 'nextStatus' => 'inprogress', 'nextLabel' => 'Zahájit'])
                @empty
                    <p class="f-light f-12 text-center py-3 mb-0">Žádné úkoly</p>
                @endforelse
                <div class="kanban-add-btn" data-bs-toggle="modal" data-bs-target="#addKanbanTaskModal"
                     data-set-value="pending" data-set-target="#kanban-default-status">
                    <i data-feather="plus" style="width:12px;height:12px;"></i> Přidat úkol
                </div>
            </div>
        </div>

        {{-- ── Probíhá (inprogress) ────────────────────────────────── --}}
        <div class="kanban-col">
            <div class="kanban-col-header bg-primary text-white">
                <span>Probíhá</span>
                <span class="badge bg-white text-primary">{{ $inprogress->count() }}</span>
            </div>
            <div class="kanban-col-body" id="col-inprogress">
                @forelse($inprogress as $task)
                    @include('admin.partials.kanban-card', ['task' => $task, 'nextStatus' => 'done', 'nextLabel' => 'Dokončit'])
                @empty
                    <p class="f-light f-12 text-center py-3 mb-0">Žádné úkoly</p>
                @endforelse
                <div class="kanban-add-btn" data-bs-toggle="modal" data-bs-target="#addKanbanTaskModal"
                     data-set-value="inprogress" data-set-target="#kanban-default-status">
                    <i data-feather="plus" style="width:12px;height:12px;"></i> Přidat úkol
                </div>
            </div>
        </div>

        {{-- ── Dokončeno (done) ─────────────────────────────────────── --}}
        <div class="kanban-col">
            <div class="kanban-col-header bg-success text-white">
                <span>Dokončeno</span>
                <span class="badge bg-white text-success">{{ $done->count() }}</span>
            </div>
            <div class="kanban-col-body" id="col-done">
                @forelse($done as $task)
                    @include('admin.partials.kanban-card', ['task' => $task, 'nextStatus' => 'pending', 'nextLabel' => 'Znovu otevřít'])
                @empty
                    <p class="f-light f-12 text-center py-3 mb-0">Žádné hotové úkoly</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- Add task modal --}}
<div class="modal fade" id="addKanbanTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nový úkol</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('admin.tasks.store') }}">
                @csrf
                <input type="hidden" name="status" id="kanban-default-status" value="pending">
                <div class="modal-body custom-input">
                    <div class="mb-3">
                        <label class="form-label">Název *</label>
                        <input type="text" name="title" class="form-control" required maxlength="200" placeholder="Co je potřeba udělat?">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Popis</label>
                        <textarea name="description" class="form-control" rows="2" maxlength="1000" placeholder="Volitelný popis…"></textarea>
                    </div>
                    <div class="grid grid-cols-12 gap-2">
                        <div class="col-span-6">
                            <label class="form-label">Priorita</label>
                            <select name="priority" class="form-select">
                                <option value="low">Nízká</option>
                                <option value="medium" selected>Střední</option>
                                <option value="high">Vysoká</option>
                            </select>
                        </div>
                        <div class="col-span-6">
                            <label class="form-label">Termín</label>
                            <input type="date" name="due_date" class="form-control" min="{{ now()->toDateString() }}">
                        </div>
                    </div>
                    @if($admins->count() > 1)
                    <div class="mb-3 mt-2">
                        <label class="form-label">Přiřadit</label>
                        <select name="assigned_to" class="form-select">
                            <option value="">— Nikdo —</option>
                            @foreach($admins as $admin)
                                <option value="{{ $admin->id }}">{{ $admin->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
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
