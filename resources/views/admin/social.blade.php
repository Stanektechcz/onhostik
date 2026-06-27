@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Profil';
    $breadcrumbItems = ['Profil' => ''];
@endphp

@section('title', 'Profil — OnHost Admin')

@section('content')
<div class="container-fluid">
    <div class="container social-app-profile1 user-profile">
        <div class="grid grid-cols-12 card-gap">

            {{-- Profile hero --}}
            <div class="col-span-12">
                <div class="card hovercard">
                    <div class="card-header" style="height:140px;background:linear-gradient(135deg,rgba(var(--theme-default),.2),rgba(var(--theme-default),.05));"></div>
                    <div class="user-image">
                        <div class="avatar">
                            <div style="width:80px;height:80px;border-radius:50%;background:rgba(var(--theme-default),1);display:flex;align-items:center;justify-content:center;margin:0 auto;border:4px solid #fff;">
                                <span class="f-w-700 f-20 text-white">{{ strtoupper(substr(auth()->user()?->name ?? 'A', 0, 2)) }}</span>
                            </div>
                        </div>
                        <div class="icon-wrapper d-inline-block" style="cursor:pointer;">
                            <i class="icofont icofont-pencil-alt-5"></i>
                        </div>
                    </div>
                    <div class="info">
                        <div class="grid grid-cols-12 gap-3">
                            <div class="col-span-3 xl:col-span-12 text-end xl:text-center">
                                <div class="social-btngroup d-flex gap-2 justify-content-end xl:justify-content-center">
                                    <a href="{{ route('panel.account.profile') }}" class="btn btn-primary btn-sm text-white">Upravit profil</a>
                                    <a href="{{ route('panel.account.security') }}" class="btn btn-outline-primary btn-sm">Zabezpečení</a>
                                </div>
                            </div>
                            <div class="col-span-6 xl:col-span-12 text-center">
                                <h3>{{ auth()->user()?->name }}</h3>
                                <p class="f-light">{{ auth()->user()?->email }}</p>
                                <div class="d-flex gap-3 justify-content-center mt-2">
                                    @foreach(auth()->user()?->getRoleNames() ?? [] as $r)
                                    <span class="badge badge-light-primary">{{ $r }}</span>
                                    @endforeach
                                </div>
                            </div>
                            <div class="col-span-3 xl:col-span-12">
                                <div class="follow d-flex gap-4 justify-content-center">
                                    <div class="text-center">
                                        <h5>{{ $stats['customers'] ?? 0 }}</h5>
                                        <h6 class="f-light">Zákazníků</h6>
                                    </div>
                                    <div class="text-center">
                                        <h5>{{ $stats['orders'] ?? 0 }}</h5>
                                        <h6 class="f-light">Objednávek</h6>
                                    </div>
                                    <div class="text-center">
                                        <h5>{{ $stats['tickets'] ?? 0 }}</h5>
                                        <h6 class="f-light">Ticketů</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- Tabs --}}
                        <div class="nav tab-links border-tab nav-primary mt-3">
                            <a class="nav-link active" href="#timeline" data-bs-toggle="tab">Přehled</a>
                            <a class="nav-link" href="#about" data-bs-toggle="tab">O profilu</a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tab content --}}
            <div class="col-span-12">
                <div class="tab-content">
                    <div class="tab-pane active" id="timeline">
                        <div class="grid grid-cols-12 card-gap">
                            <div class="col-span-4 xl:col-span-12">
                                <div class="card">
                                    <div class="card-header card-no-border"><h5>Aktivita</h5></div>
                                    <div class="card-body">
                                        @foreach([
                                            ['Přihlášení do systému','Před 2 minutami','log-in'],
                                            ['Zákazník #1234 aktualizován','Před 1 hodinou','users'],
                                            ['Faktura CZ-2026-000123 vystavena','Před 3 hodinami','file-text'],
                                            ['Server test proběhl úspěšně','Před 5 hodinami','server'],
                                        ] as [$act, $time, $icon])
                                        <div class="d-flex align-items-start gap-3 py-2 border-bottom">
                                            <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,rgba(var(--theme-default),.15),rgba(var(--theme-default),.03));display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                                <i data-feather="{{ $icon }}" style="width:16px;height:16px;color:rgba(var(--theme-default),1);"></i>
                                            </div>
                                            <div><p class="f-14 mb-0">{{ $act }}</p><small class="f-light f-11">{{ $time }}</small></div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="col-span-8 xl:col-span-12">
                                <div class="card">
                                    <div class="card-header card-no-border"><h5>Rychlé statistiky</h5></div>
                                    <div class="card-body">
                                        <div class="grid grid-cols-12 card-gap">
                                            @foreach([['Celkem zákazníků','users','primary',$stats['customers'] ?? 0],['Aktivní objednávky','shopping-cart','success',$stats['orders'] ?? 0],['Otevřené tickety','message-square','warning',$stats['tickets'] ?? 0],['Aktivní servery','server','info',$stats['servers'] ?? 0]] as [$label,$icon,$color,$val])
                                            <div class="col-span-6">
                                                <div class="d-flex align-items-center gap-3 p-3 border rounded">
                                                    <div style="width:44px;height:44px;border-radius:10px;background:linear-gradient(135deg,rgba(var(--theme-default),.15),rgba(var(--theme-default),.03));display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                                        <i data-feather="{{ $icon }}" style="width:20px;height:20px;color:rgba(var(--theme-default),1);"></i>
                                                    </div>
                                                    <div><h5 class="mb-0">{{ $val }}</h5><small class="f-light f-12">{{ $label }}</small></div>
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane" id="about">
                        <div class="card">
                            <div class="card-header card-no-border"><h5>O účtu</h5></div>
                            <div class="card-body">
                                <div class="grid grid-cols-12 card-gap">
                                    <div class="col-span-6">
                                        <h6 class="mb-3">Informace o účtu</h6>
                                        @foreach([['Jméno',auth()->user()?->name],['E-mail',auth()->user()?->email],['Jazyk',auth()->user()?->locale ?? 'cs'],['Registrace',auth()->user()?->created_at?->format('d.m.Y')]] as [$l,$v])
                                        <div class="d-flex justify-content-between py-2 border-bottom">
                                            <span class="f-light f-13">{{ $l }}</span><span class="f-w-500 f-13">{{ $v }}</span>
                                        </div>
                                        @endforeach
                                    </div>
                                    <div class="col-span-6">
                                        <h6 class="mb-3">Rychlé akce</h6>
                                        <div class="d-flex flex-column gap-2">
                                            <a href="{{ route('admin.settings.index') }}" class="btn btn-outline-primary btn-sm">Nastavení systému</a>
                                            <a href="{{ route('panel.account.security') }}" class="btn btn-outline-secondary btn-sm">Změnit heslo</a>
                                            <a href="{{ route('panel.account.profile') }}" class="btn btn-outline-secondary btn-sm">Upravit profil</a>
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
</div>
@endsection
