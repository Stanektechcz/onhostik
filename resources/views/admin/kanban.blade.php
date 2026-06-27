@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Kanban';
    $breadcrumbItems = ['Kanban' => ''];
@endphp

@section('title', 'Kanban Board')

@push('styles')
<style>
.kanban-board { display: flex; gap: 20px; overflow-x: auto; padding: 8px 0 16px; }
.kanban-col { min-width: 280px; max-width: 280px; }
.kanban-col-header { padding: 10px 14px; border-radius: 8px 8px 0 0; font-weight: 600; font-size: 14px; }
.kanban-col-body { background: rgba(var(--light-background),.4); border-radius: 0 0 8px 8px; padding: 10px; min-height: 200px; }
.kanban-card { background: #fff; border-radius: 8px; padding: 12px; margin-bottom: 10px; border: 1px solid rgba(var(--light-background),1); cursor: grab; }
.kanban-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); }
.kanban-card h6 { font-size: 13px; margin: 0 0 6px; }
.kanban-card p { font-size: 11px; color: var(--body-font-color); opacity: .7; margin: 0; }
.kanban-add { text-align: center; padding: 8px; color: var(--body-font-color); opacity: .5; cursor: pointer; font-size: 13px; }
.kanban-add:hover { opacity: 1; }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="container jkanban-container">
        <div class="grid grid-cols-12 card-gap mb-3">
            <div class="col-span-12">
                <div class="d-flex gap-2 justify-content-end">
                    <button class="btn btn-primary btn-sm text-white"
                            data-bs-toggle="modal" data-bs-target="#addTaskModal">
                        <i data-feather="plus" style="width:13px;height:13px;"></i> Přidat úkol
                    </button>
                </div>
            </div>
        </div>

        <div class="kanban-board">
            @foreach([
                ['Nové', 'primary', [
                    ['Nastavení DNS pro zákazníka #1234', 'Zpracovává: Adam K.', 'warning'],
                    ['SSL certifikát — opakované selhání', 'Přiřazeno: —', 'danger'],
                    ['Migrace webu na nový server', 'Zpracovává: Pavel S.', 'secondary'],
                ]],
                ['Probíhá', 'warning', [
                    ['Upgrade VPS pro zákazníka Novák s.r.o.', 'Zpracovává: Martin L.', 'primary'],
                    ['Implementace zálohování', 'Přiřazeno: Adam K.', 'success'],
                ]],
                ['Ke kontrole', 'info', [
                    ['Audit bezpečnosti serveru', 'Zpracovává: Pavel S.', 'primary'],
                ]],
                ['Dokončeno', 'success', [
                    ['Obnova databáze zákazník #887', 'Uzavřeno: 27.6.2026', 'success'],
                    ['Přenos domény onhost-client.cz', 'Uzavřeno: 26.6.2026', 'success'],
                    ['WordPress instalace', 'Uzavřeno: 25.6.2026', 'secondary'],
                ]],
            ] as [$colTitle, $color, $cards])
            <div class="kanban-col">
                <div class="kanban-col-header bg-{{ $color }} text-white">
                    {{ $colTitle }}
                    <span class="badge bg-white text-{{ $color }} ms-2">{{ count($cards) }}</span>
                </div>
                <div class="kanban-col-body">
                    @foreach($cards as [$title, $meta, $badge])
                    <div class="kanban-card">
                        <h6>{{ $title }}</h6>
                        <p>{{ $meta }}</p>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <span class="badge badge-light-{{ $badge }} f-11">{{ $badge === 'success' ? 'Hotovo' : ($badge === 'danger' ? 'Urgentní' : ($badge === 'warning' ? 'Střední' : 'Normální')) }}</span>
                            <i data-feather="more-horizontal" style="width:14px;height:14px;opacity:.4;cursor:pointer;"></i>
                        </div>
                    </div>
                    @endforeach
                    <div class="kanban-add" onclick="document.getElementById('addTaskModal')">
                        <i data-feather="plus" style="width:14px;height:14px;"></i> Přidat
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

<div class="modal fade" id="addTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nový úkol</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body custom-input">
                <div class="mb-3">
                    <label class="form-label">Název úkolu</label>
                    <input type="text" class="form-control" placeholder="Popis úkolu…">
                </div>
                <div class="mb-3">
                    <label class="form-label">Sloupec</label>
                    <select class="form-select">
                        <option>Nové</option>
                        <option>Probíhá</option>
                        <option>Ke kontrole</option>
                        <option>Dokončeno</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Priorita</label>
                    <select class="form-select">
                        <option>Normální</option>
                        <option>Střední</option>
                        <option>Urgentní</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušit</button>
                <button type="button" class="btn btn-primary text-white" data-bs-dismiss="modal">Přidat úkol</button>
            </div>
        </div>
    </div>
</div>
@endsection
