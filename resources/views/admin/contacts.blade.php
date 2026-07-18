@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Kontakty';
    $breadcrumbItems = ['Kontakty' => ''];
@endphp

@section('title', 'Kontakty')

@section('content')
<div class="container-fluid">
    <div class="container email-wrap bookmark-wrap">
        <div class="grid grid-cols-12 card-gap">

            {{-- Left sidebar --}}
            <div class="col-span-3 xl:col-span-12 xl-40 box-col-3e">
                <div class="email-app-sidebar left-bookmark">
                    <button class="btn btn-primary w-full text-white mb-3"
                            data-bs-toggle="modal" data-bs-target="#newContactModal">
                        <i data-feather="user-plus" style="width:13px;height:13px;"></i> Nový kontakt
                    </button>
                    <ul class="nav flex-col">
                        @foreach(['Všechny','Zákazníci','Partneři','Interní'] as $filter)
                        <li class="nav-item">
                            <a class="nav-link {{ $loop->first ? 'active' : '' }}" href="#">{{ $filter }}</a>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- Right: contacts --}}
            <div class="col-span-9 xl:col-span-12 xl-80 box-col-9e">
                <div class="grid grid-cols-12 gap-3">

                    {{-- Contact list --}}
                    <div class="col-span-5 xl:col-span-12">
                        <div class="card" style="max-height:600px;overflow-y:auto;">
                            <div class="card-header card-no-border">
                                <div class="header-top"><h5>Kontakty ({{ $contacts->count() ?? 0 }})</h5></div>
                                <div class="mt-2">
                                    <div class="faq-form"><input class="form-control form-control-sm" type="text" placeholder="Hledat kontakt…"><i class="search-icon" data-feather="search"></i></div>
                                </div>
                            </div>
                            <div class="card-body pt-0">
                                @forelse($contacts ?? [] as $contact)
                                <div class="flex items-center gap-3 py-2 border-bottom cursor-pointer">
                                    <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,rgba(var(--theme-default),.2),rgba(var(--theme-default),.05));display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <span class="f-w-600 f-12" style="color:rgba(var(--theme-default),1);">{{ strtoupper(substr($contact->name, 0, 2)) }}</span>
                                    </div>
                                    <div class="flex-1">
                                        <h6 class="mb-0 f-14">{{ $contact->name }}</h6>
                                        <p class="f-light f-12 mb-0">{{ $contact->email }}</p>
                                    </div>
                                    <a href="{{ route('admin.customers.show', $contact->customer ?? 1) }}" class="btn btn-xs btn-outline-primary">Detail</a>
                                </div>
                                @empty
                                {{-- Static contacts fallback --}}
                                @foreach([['Jan Novák','jan.novak@example.com','JN'],['Petra Svobodová','petra@firma.cz','PS'],['Martin Kříž','mkriz@web.cz','MK'],['Eva Procházková','eva@hosting.cz','EP'],['Tomáš Blaha','tomas@blaha.cz','TB']] as [$name, $email, $initials])
                                <div class="flex items-center gap-3 py-2 border-bottom">
                                    <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,rgba(var(--theme-default),.2),rgba(var(--theme-default),.05));display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <span class="f-w-600 f-12" style="color:rgba(var(--theme-default),1);">{{ $initials }}</span>
                                    </div>
                                    <div class="flex-1">
                                        <h6 class="mb-0 f-14">{{ $name }}</h6>
                                        <p class="f-light f-12 mb-0">{{ $email }}</p>
                                    </div>
                                </div>
                                @endforeach
                                @endforelse
                            </div>
                        </div>
                    </div>

                    {{-- Contact detail --}}
                    <div class="col-span-7 xl:col-span-12">
                        <div class="card">
                            <div class="card-header card-no-border">
                                <div class="header-top"><h5>Detail kontaktu</h5></div>
                            </div>
                            <div class="card-body">
                                <div class="email-general">
                                    <div class="text-center mb-4">
                                        <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,rgba(var(--theme-default),.2),rgba(var(--theme-default),.05));display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                                            <span class="f-w-700 f-20" style="color:rgba(var(--theme-default),1);">JN</span>
                                        </div>
                                        <h5 class="mb-1">Jan Novák</h5>
                                        <p class="f-light f-12">Zákazník — od 15.3.2024</p>
                                    </div>
                                    <ul class="list-unstyled">
                                        @foreach([['E-mail','jan.novak@example.com','mail'],['Telefon','+420 777 123 456','phone'],['Město','Praha, ČR','map-pin'],['Web','www.jnweb.cz','globe']] as [$label, $val, $icon])
                                        <li class="flex gap-3 py-2 border-bottom">
                                            <i data-feather="{{ $icon }}" style="width:16px;height:16px;flex-shrink:0;opacity:.5;margin-top:2px;"></i>
                                            <div><small class="f-light block f-11">{{ $label }}</small><span class="f-14">{{ $val }}</span></div>
                                        </li>
                                        @endforeach
                                    </ul>
                                    <div class="flex gap-2 mt-4">
                                        <a href="{{ route('admin.customers.index') }}" class="btn btn-outline-primary btn-sm">Zákaznický profil</a>
                                        <a href="{{ route('admin.support.index') }}" class="btn btn-primary btn-sm text-white">Tickety</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="newContactModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Nový kontakt</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body custom-input">
                <div class="mb-3"><label class="form-label">Jméno</label><input type="text" class="form-control"></div>
                <div class="mb-3"><label class="form-label">E-mail</label><input type="email" class="form-control"></div>
                <div class="mb-3"><label class="form-label">Telefon</label><input type="tel" class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušit</button><button type="button" class="btn btn-primary text-white" data-bs-dismiss="modal">Přidat</button></div>
        </div>
    </div>
</div>
@endsection
