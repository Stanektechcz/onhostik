@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Pošta';
    $breadcrumbItems = ['Pošta' => ''];
@endphp

@section('title', 'Pošta')

@section('content')
<div class="container-fluid">
    <div class="container">
        <div class="email-wrap email-main-wrapper">
            <div class="grid grid-cols-12 card-gap email-application">

                {{-- Left sidebar --}}
                <div class="col-span-3 xl:col-span-12 box-col-3e">
                    <div class="email-app-sidebar left-bookmark">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h5 class="mb-0">Pošta</h5>
                            <button class="btn btn-primary btn-sm text-white"
                                    data-bs-toggle="modal" data-bs-target="#composeModal">
                                <i data-feather="edit" style="width:13px;height:13px;"></i> Napsat
                            </button>
                        </div>
                        <ul class="nav flex-column email-sidebar-list">
                            <li class="nav-item">
                                <a class="nav-link active d-flex align-items-center gap-2" href="#">
                                    <i data-feather="inbox" style="width:16px;height:16px;"></i>
                                    Doručené
                                    <span class="badge badge-primary ms-auto text-white">{{ $inboxCount ?? 0 }}</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link d-flex align-items-center gap-2" href="#">
                                    <i data-feather="send" style="width:16px;height:16px;"></i>
                                    Odeslané
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link d-flex align-items-center gap-2" href="#">
                                    <i data-feather="star" style="width:16px;height:16px;"></i>
                                    Označené
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link d-flex align-items-center gap-2" href="#">
                                    <i data-feather="file" style="width:16px;height:16px;"></i>
                                    Koncepty
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link d-flex align-items-center gap-2" href="#">
                                    <i data-feather="trash-2" style="width:16px;height:16px;"></i>
                                    Koš
                                </a>
                            </li>
                        </ul>
                        <hr>
                        <h6 class="f-light f-12 mb-2">Štítky</h6>
                        <ul class="nav flex-column">
                            <li class="nav-item"><a class="nav-link f-12" href="#"><span class="badge badge-primary me-2 text-white">●</span>Podpora</a></li>
                            <li class="nav-item"><a class="nav-link f-12" href="#"><span class="badge badge-success me-2 text-white">●</span>Fakturace</a></li>
                            <li class="nav-item"><a class="nav-link f-12" href="#"><span class="badge badge-warning me-2 text-white">●</span>Urgentní</a></li>
                        </ul>
                    </div>
                </div>

                {{-- Right: email list --}}
                <div class="col-span-9 xl:col-span-12 box-col-9e">
                    <div class="email-right-aside">
                        <div class="card email-body">
                            <div class="card-header card-no-border">
                                <div class="header-top">
                                    <h5>Doručené</h5>
                                    <div class="card-header-right-icon">
                                        <a href="{{ route('admin.support.index') }}" class="btn btn-outline-primary btn-sm">
                                            <i data-feather="external-link" style="width:13px;height:13px;"></i>
                                            Ticket systém
                                        </a>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <div class="faq-form">
                                        <input class="form-control" type="text" placeholder="Hledat v poště…">
                                        <i class="search-icon" data-feather="search"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body pt-0 px-0">
                                <div class="text-center py-5">
                                    <i data-feather="mail" style="width:48px;height:48px;" class="text-muted mb-3 d-block mx-auto"></i>
                                    <h5 class="f-light">Poštovní schránka</h5>
                                    <p class="f-light f-13 mb-4">
                                        Interní e-mailový systém bude dostupný v dalším vydání.<br>
                                        Pro zákaznické dotazy používejte ticket systém.
                                    </p>
                                    <a href="{{ route('admin.support.index') }}" class="btn btn-primary text-white">
                                        <i data-feather="message-square" style="width:14px;height:14px;"></i>
                                        Přejít na tickety
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

{{-- Compose modal --}}
<div class="modal fade" id="composeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nová zpráva</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body custom-input">
                <div class="mb-3">
                    <label class="form-label">Komu</label>
                    <input type="email" class="form-control" placeholder="Zadejte e-mail příjemce">
                </div>
                <div class="mb-3">
                    <label class="form-label">Předmět</label>
                    <input type="text" class="form-control" placeholder="Předmět zprávy">
                </div>
                <div class="mb-3">
                    <label class="form-label">Zpráva</label>
                    <textarea class="form-control" rows="6" placeholder="Napište zprávu…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušit</button>
                <button type="button" class="btn btn-primary text-white">
                    <i data-feather="send" style="width:13px;height:13px;"></i> Odeslat
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
