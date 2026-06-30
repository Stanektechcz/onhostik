@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Kalendář';
    $breadcrumbItems = ['Kalendář' => ''];
@endphp

@section('title', 'Kalendář')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
<style>
.fc-toolbar-title { font-size: 1.1rem !important; }
.fc-button-primary { background-color: rgba(var(--theme-default),1) !important; border-color: rgba(var(--theme-default),1) !important; }
.fc-event { cursor: pointer; }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="container calendar-basic">
        <div class="grid grid-cols-12 card-gap">

            {{-- Left: Draggable events + create --}}
            <div class="col-span-3 xl:col-span-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top">
                            <h5>Události</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="external-events-list" class="mb-3">
                            @foreach([
                                ['Obnova SSL','warning'],
                                ['Výpadek serveru','danger'],
                                ['Fakturace Q3','primary'],
                                ['Údržba','secondary'],
                                ['Platba faktury','success'],
                            ] as [$event, $color])
                            <div class="fc-event badge badge-light-{{ $color }} d-block mb-2 text-start"
                                 style="cursor:grab;padding:8px 12px;font-size:13px;">
                                <i data-feather="calendar" style="width:12px;height:12px;margin-right:6px;"></i>
                                {{ $event }}
                            </div>
                            @endforeach
                        </div>
                        <hr>
                        <button class="btn btn-primary w-full text-white btn-sm"
                                data-bs-toggle="modal" data-bs-target="#createEventModal">
                            <i data-feather="plus" style="width:13px;height:13px;"></i> Vytvořit událost
                        </button>
                    </div>
                </div>
            </div>

            {{-- Right: Calendar --}}
            <div class="col-span-9 xl:col-span-12">
                <div class="card">
                    <div class="card-body">
                        <div id="calendar"></div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- Create event modal --}}
<div class="modal fade" id="createEventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nová událost</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body custom-input">
                <div class="mb-3"><label class="form-label">Název události</label><input type="text" class="form-control" id="event-name"></div>
                <div class="mb-3"><label class="form-label">Popis</label><textarea class="form-control" rows="2" id="event-desc"></textarea></div>
                <div class="grid grid-cols-12 gap-3">
                    <div class="col-span-6 sm:col-span-12"><label class="form-label">Datum od</label><input type="date" class="form-control" id="event-start"></div>
                    <div class="col-span-6 sm:col-span-12"><label class="form-label">Datum do</label><input type="date" class="form-control" id="event-end"></div>
                </div>
                <div class="mt-3">
                    <label class="form-label">Barva</label>
                    <select class="form-select" id="event-color">
                        <option value="#7366FF">Fialová (primární)</option>
                        <option value="#54ba4a">Zelená</option>
                        <option value="#f39c12">Oranžová</option>
                        <option value="#dc3545">Červená</option>
                        <option value="#0dcaf0">Modrá</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušit</button>
                <button type="button" class="btn btn-primary text-white" id="add-event-btn" data-bs-dismiss="modal">Přidat</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/cs.js"></script>
<script>
(function() {
    var calendarEl = document.getElementById('calendar');
    if (!calendarEl || typeof FullCalendar === 'undefined') return;

    var calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'cs',
        initialView: 'dayGridMonth',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' },
        events: [
            { title: 'Obnova SSL — zákazník #1234', start: '{{ now()->format('Y-m') }}-05', color: '#f39c12' },
            { title: 'Fakturace Q3', start: '{{ now()->format('Y-m') }}-01', end: '{{ now()->format('Y-m') }}-03', color: '#7366FF' },
            { title: 'Plánovaná údržba serveru', start: '{{ now()->format('Y-m') }}-15', color: '#dc3545' },
            { title: 'Partnerský výplata', start: '{{ now()->format('Y-m') }}-28', color: '#54ba4a' },
        ],
        editable: true,
        selectable: true,
        select: function(info) {
            document.getElementById('event-start').value = info.startStr;
            document.getElementById('event-end').value = info.endStr;
            new bootstrap.Modal(document.getElementById('createEventModal')).show();
        },
    });
    calendar.render();

    document.getElementById('add-event-btn').addEventListener('click', function() {
        var name  = document.getElementById('event-name').value;
        var start = document.getElementById('event-start').value;
        var end   = document.getElementById('event-end').value;
        var color = document.getElementById('event-color').value;
        if (name && start) {
            calendar.addEvent({ title: name, start: start, end: end || null, color: color });
            document.getElementById('event-name').value = '';
        }
    });
})();
</script>
@endpush
