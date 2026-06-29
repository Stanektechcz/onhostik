@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Záložky';
    $breadcrumbItems = ['Záložky' => ''];
@endphp

@section('title', 'Záložky')

@section('content')
<div class="container-fluid">
    <div class="container email-wrap bookmark-wrap">
        <div class="grid grid-cols-12 card-gap">

            {{-- Left sidebar --}}
            <div class="col-span-3 xl:col-span-12 xl-40 box-col-3e">
                <div class="email-app-sidebar left-bookmark">
                    <button class="btn btn-primary w-full text-white mb-3" data-bs-toggle="modal" data-bs-target="#addBookmarkModal">
                        <i data-feather="plus" style="width:13px;height:13px;"></i> Přidat záložku
                    </button>
                    <ul class="nav flex-column">
                        @foreach([['Moje záložky','bookmark'],['Oblíbené','heart'],['Sdílené','share-2'],['Archiv','archive']] as [$label, $icon])
                        <li class="nav-item">
                            <a class="nav-link {{ $loop->first ? 'active' : '' }} d-flex align-items-center gap-2" href="#">
                                <i data-feather="{{ $icon }}" style="width:14px;height:14px;"></i>{{ $label }}
                            </a>
                        </li>
                        @endforeach
                    </ul>
                    <hr>
                    <h6 class="f-light f-12 mb-2">Štítky</h6>
                    @foreach([['Admin','primary'],['Faktury','success'],['Zákazníci','warning'],['Servery','danger']] as [$tag,$color])
                    <span class="badge badge-{{ $color }} me-1 mb-1 text-white">{{ $tag }}</span>
                    @endforeach
                </div>
            </div>

            {{-- Right: bookmarks grid --}}
            <div class="col-span-9 xl:col-span-12 xl-80 box-col-9e">
                <div class="grid grid-cols-12 card-gap">
                    @php
                    $bookmarkLinks = [
                        ['Dashboard',    'Dashboard systému',  route('admin.dashboard'),          'Přehled systému', 'primary'],
                        ['Zákazníci',    'Správa zákazníků',   route('admin.customers.index'),     'CRM',             'info'],
                        ['Fakturace',    'Přehled faktur',     route('admin.invoices.index'),      'Finance',         'success'],
                        ['Produkty',     'Správa tarifů',      route('admin.products.index'),      'Produkty',        'warning'],
                        ['Servery',      'Správa serverů',     route('admin.servers.index'),       'Infrastruktura',  'danger'],
                        ['Audit log',    'Log aktivit',        route('admin.logs.audit'),          'Bezpečnost',      'secondary'],
                    ];
                    @endphp
                    @foreach($bookmarkLinks as [$title, $desc, $bmUrl, $collection, $color])
                    <div class="col-span-4 xl:col-span-6 sm:col-span-12">
                        <div class="card bookmark-card card-with-border h-full">
                            <div class="card-body">
                                <div class="details-bookmark text-center">
                                    <div style="width:56px;height:56px;border-radius:12px;background:linear-gradient(135deg,rgba(var(--theme-default),.15),rgba(var(--theme-default),.03));display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                                        <i data-feather="link" style="width:24px;height:24px;color:rgba(var(--theme-default),1);"></i>
                                    </div>
                                    <h6>{{ $title }}</h6>
                                    <a href="{{ $bmUrl }}" class="details-website f-12 f-light d-block mb-2">{{ parse_url($bmUrl, PHP_URL_PATH) }}</a>
                                    <p class="f-light f-12 mb-2">{{ $desc }}</p>
                                    <span class="badge badge-light-{{ $color }}">{{ $collection }}</span>
                                </div>
                                <div class="hover-block">
                                    <ul class="list-unstyled d-flex gap-2 justify-content-center mt-3">
                                        <li><a href="{{ $bmUrl }}" class="square-white"><i data-feather="external-link" style="width:14px;height:14px;"></i></a></li>
                                        <li><a href="#" class="square-white"><i data-feather="share-2" style="width:14px;height:14px;"></i></a></li>
                                        <li><a href="#" class="square-white trash-3"><i data-feather="trash-2" style="width:14px;height:14px;"></i></a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="addBookmarkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Nová záložka</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body custom-input">
                <div class="mb-3"><label class="form-label">Název</label><input type="text" class="form-control"></div>
                <div class="mb-3"><label class="form-label">URL</label><input type="url" class="form-control" placeholder="https://"></div>
                <div class="mb-3"><label class="form-label">Popis</label><input type="text" class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušit</button><button type="button" class="btn btn-primary text-white" data-bs-dismiss="modal">Přidat</button></div>
        </div>
    </div>
</div>
@endsection
