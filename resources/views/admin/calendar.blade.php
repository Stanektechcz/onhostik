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
.fc-event { cursor: pointer; font-size: 12px !important; }
.cal-legend { display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; }
.cal-legend span { display:inline-flex; align-items:center; gap:5px; font-size:12px; }
.cal-dot { width:10px; height:10px; border-radius:50%; display:inline-block; }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <x-panel.flash />
    <div class="container calendar-basic">
        <div class="grid grid-cols-12 card-gap">

            {{-- Left: Legend + quick add --}}
            <div class="col-span-3 xl:col-span-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top">
                            <h5>Legenda</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="cal-legend mb-3">
                            <div><span><span class="cal-dot" style="background:#7366FF;"></span> Obnova služby (za 8+ dní)</span></div>
                            <div><span><span class="cal-dot" style="background:#f39c12;"></span> Obnova (za 4–7 dní)</span></div>
                            <div><span><span class="cal-dot" style="background:#dc3545;"></span> Obnova / doména (≤3 dny)</span></div>
                            <div><span><span class="cal-dot" style="background:#e67e22;"></span> Expirace domény</span></div>
                            <div><span><span class="cal-dot" style="background:#54ba4a;"></span> Splatnost faktury</span></div>
                        </div>
                        <hr>
                        <p class="f-light f-12">
                            Kliknutím na událost otevřete detail.<br>
                            Data jsou načítána z DB — obnovy, domény, faktury.
                        </p>
                        <hr>
                        <button class="btn btn-primary w-full text-white btn-sm"
                                data-bs-toggle="modal" data-bs-target="#createEventModal">
                            <i data-feather="plus" style="width:13px;height:13px;"></i> Vlastní událost
                        </button>
                    </div>
                </div>

                {{-- Upcoming renewals summary --}}
                <div class="card mt-0">
                    <div class="card-header card-no-border">
                        <h6 class="mb-0">Nejbližší události</h6>
                    </div>
                    <div class="card-body pt-0">
                        <div id="upcoming-list" class="f-12 f-light">Načítání…</div>
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

{{-- Create event modal (client-side only) --}}
<div class="modal fade" id="createEventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Vlastní událost</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body custom-input">
                <div class="mb-3"><label class="form-label">Název události</label><input type="text" class="form-control" id="event-name"></div>
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
<script nonce="{{ $cspNonce ?? '' }}">
(function () {
    var calendarEl = document.getElementById('calendar');
    if (!calendarEl || typeof FullCalendar === 'undefined') return;

    var calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'cs',
        initialView: 'dayGridMonth',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' },
        events: '{{ route('admin.calendar.events') }}',
        eventClick: function (info) {
            if (info.event.url) {
                info.jsEvent.preventDefault();
                window.location.href = info.event.url;
            }
        },
        eventDidMount: function (info) {
            info.el.setAttribute('title',
                info.event.title + (info.event.extendedProps.customer ? ' — ' + info.event.extendedProps.customer : '')
            );
        },
        editable: false,
        selectable: true,
        select: function (info) {
            document.getElementById('event-start').value = info.startStr;
            document.getElementById('event-end').value = info.endStr;
            new bootstrap.Modal(document.getElementById('createEventModal')).show();
        },
        loading: function (isLoading) {
            if (!isLoading) buildUpcomingList(calendar.getEvents());
        },
    });
    calendar.render();

    document.getElementById('add-event-btn').addEventListener('click', function () {
        var name  = document.getElementById('event-name').value.trim();
        var start = document.getElementById('event-start').value;
        var end   = document.getElementById('event-end').value;
        var color = document.getElementById('event-color').value;
        if (name && start) {
            calendar.addEvent({ title: name, start: start, end: end || null, color: color });
            document.getElementById('event-name').value = '';
        }
    });

    function buildUpcomingList(events) {
        var list = document.getElementById('upcoming-list');
        if (!list) return;
        var sorted = events
            .filter(function (e) { return e.start >= new Date(); })
            .sort(function (a, b) { return a.start - b.start; })
            .slice(0, 8);
        if (!sorted.length) { list.innerHTML = '<em>Žádné nadcházející události.</em>'; return; }
        list.innerHTML = sorted.map(function (e) {
            var d = e.start;
            var dateStr = d.toLocaleDateString('cs-CZ', { day: '2-digit', month: '2-digit' });
            return '<div class="flex justify-between mb-1">'
                + '<span style="color:' + (e.backgroundColor || '#333') + '">● ' + e.title + '</span>'
                + '<span class="text-muted">' + dateStr + '</span>'
                + '</div>';
        }).join('');
    }
})();
</script>
@endpush
