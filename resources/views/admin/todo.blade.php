@extends('layouts.panel')

@php
    $breadcrumbTitle = 'To-Do';
    $breadcrumbItems = ['To-Do' => ''];
@endphp

@section('title', 'To-Do seznam')

@section('content')
<div class="container-fluid">
    <div class="container email-wrap bookmark-wrap todo-wrap">
        <div class="grid grid-cols-12 card-gap">

            {{-- Left sidebar --}}
            <div class="col-span-3 xl:col-span-12 xl-40 box-col-3e">
                <div class="email-app-sidebar left-bookmark">
                    <div class="text-center mb-3">
                        <div style="width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,rgba(var(--theme-default),.2),rgba(var(--theme-default),.05));display:flex;align-items:center;justify-content:center;margin:0 auto 8px;">
                            <i data-feather="user" style="width:28px;height:28px;color:rgba(var(--theme-default),1);"></i>
                        </div>
                        <h6>Admin OnHost</h6>
                        <p class="f-light f-12">admin@onhost.cz</p>
                    </div>
                    <hr>
                    <ul class="nav flex-col">
                        <li class="nav-item">
                            <a class="nav-link active flex justify-between" href="#">
                                <span><i data-feather="list" style="width:14px;height:14px;margin-right:8px;"></i>Vše</span>
                                <span class="badge badge-primary text-white" id="all-count">6</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link flex justify-between" href="#">
                                <span><i data-feather="check-circle" style="width:14px;height:14px;margin-right:8px;"></i>Dokončené</span>
                                <span class="badge badge-light-success" id="done-count">2</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link flex justify-between" href="#">
                                <span><i data-feather="clock" style="width:14px;height:14px;margin-right:8px;"></i>Čekající</span>
                                <span class="badge badge-light-warning" id="pending-count">3</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link flex justify-between" href="#">
                                <span><i data-feather="refresh-cw" style="width:14px;height:14px;margin-right:8px;"></i>Probíhá</span>
                                <span class="badge badge-light-primary" id="inprog-count">1</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            {{-- Right: todo list --}}
            <div class="col-span-9 xl:col-span-12 xl-80 box-col-9e">
                <div class="card todo-tasks">
                    <div class="card-header card-no-border">
                        <div class="header-top">
                            <h5>To-Do seznam</h5>
                        </div>
                        <div class="flex gap-2 mt-2">
                            <input type="text" class="form-control form-control-sm" id="new-todo" placeholder="Přidat nový úkol…">
                            <button class="btn btn-primary btn-sm text-white" onclick="addTodo()">Přidat</button>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <ul class="checkbox-checked" id="task-list">
                            @foreach([
                                ['Zkontrolovat SSL certifikáty zákazníků', false, 'Vysoká'],
                                ['Připravit fakturace pro červenec', false, 'Střední'],
                                ['Aktualizovat PHP na serverech', false, 'Vysoká'],
                                ['Odpovědět na tickety zákazníků', true, 'Normální'],
                                ['Přezkoumat partnerské provize', false, 'Nízká'],
                                ['Backup konfigurace serverů', true, 'Normální'],
                            ] as [$todo, $done, $priority])
                            <li class="task-item flex items-center gap-3 py-2 border-bottom {{ $done ? 'task-done' : '' }}">
                                <div class="form-check mb-0">
                                    <input class="form-check-input checkbox-primary" type="checkbox" {{ $done ? 'checked' : '' }}
                                           onchange="toggleTodo(this)">
                                </div>
                                <span class="flex-1 {{ $done ? 'text-decoration-line-through f-light' : '' }}">{{ $todo }}</span>
                                <span class="badge badge-light-{{ $priority === 'Vysoká' ? 'danger' : ($priority === 'Střední' ? 'warning' : 'secondary') }} f-11 ms-auto">{{ $priority }}</span>
                                <a href="#" class="trash-3" onclick="this.closest('li').remove();return false;">
                                    <i data-feather="x" style="width:14px;height:14px;opacity:.4;"></i>
                                </a>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function addTodo() {
    var input = document.getElementById('new-todo');
    var val = input.value.trim();
    if (!val) return;
    var li = document.createElement('li');
    li.className = 'task-item flex items-center gap-3 py-2 border-bottom';
    li.innerHTML = '<div class="form-check mb-0"><input class="form-check-input checkbox-primary" type="checkbox" onchange="toggleTodo(this)"></div>' +
        '<span class="flex-1">' + val + '</span>' +
        '<span class="badge badge-light-secondary f-11 ms-auto">Normální</span>' +
        '<a href="#" class="trash-3" onclick="this.closest(\'li\').remove();return false;"><i data-feather="x" style="width:14px;height:14px;opacity:.4;"></i></a>';
    document.getElementById('task-list').appendChild(li);
    input.value = '';
    if (typeof feather !== 'undefined') feather.replace();
}
function toggleTodo(cb) {
    var span = cb.closest('li').querySelector('span.flex-1');
    if (cb.checked) span.classList.add('text-decoration-line-through', 'f-light');
    else span.classList.remove('text-decoration-line-through', 'f-light');
}
</script>
@endpush
