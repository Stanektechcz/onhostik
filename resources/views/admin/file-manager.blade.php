@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Správce souborů';
    $breadcrumbItems = ['Správce souborů' => ''];
@endphp

@section('title', 'Správce souborů')

@section('content')
<div class="container-fluid">
    <div class="container main-file-sidebar">
        <div class="grid grid-cols-12 card-gap">

            {{-- Left: file tree sidebar --}}
            <div class="col-span-3 xl:col-span-12 box-col-3e">
                <div class="card file-sidebar">
                    <div class="card-body">
                        <ul class="files-left-icons file-type-icons list-unstyled mb-3">
                            @foreach([
                                ['Složky','folder','primary',12,'—'],
                                ['Dokumenty','file-text','secondary',8,'2.3 MB'],
                                ['Obrázky','image','success',24,'18.5 MB'],
                                ['PDF soubory','file','danger',5,'4.1 MB'],
                                ['Zálohy','archive','warning',3,'120 MB'],
                                ['Logy','terminal','info',45,'8.7 MB'],
                            ] as [$label,$icon,$color,$count,$size])
                            <li class="d-flex align-items-center gap-3 py-2 border-bottom cursor-pointer">
                                <div style="width:36px;height:36px;border-radius:8px;background:rgba(var(--theme-default),.08);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i data-feather="{{ $icon }}" style="width:18px;height:18px;color:rgba(var(--theme-default),1);"></i>
                                </div>
                                <div class="flex-1">
                                    <span class="f-14 f-w-500">{{ $label }}</span>
                                    <small class="d-block f-light f-11">{{ $count }} souborů · {{ $size }}</small>
                                </div>
                            </li>
                            @endforeach
                        </ul>
                        <hr>
                        <h6 class="f-light f-12 mb-2">Využití úložiště</h6>
                        <div class="sm-progress-bar mb-1">
                            <div class="progress" style="height:6px;">
                                <div class="progress-bar bg-primary" style="width:34%"></div>
                            </div>
                        </div>
                        <p class="f-light f-11">153.6 MB z 500 MB (34%)</p>
                        <div class="pricing-plan mt-2 p-3 rounded" style="background:linear-gradient(135deg,rgba(var(--theme-default),.1),rgba(var(--theme-default),.03));">
                            <h6 class="f-12 mb-1">Potřebujete více místa?</h6>
                            <a href="{{ route('admin.products.index') }}" class="btn btn-primary btn-sm text-white w-full">Upgradovat tarif</a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right: file browser --}}
            <div class="col-span-9 xl:col-span-12 box-col-9e">
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top">
                            <h5>Správce souborů</h5>
                            <div class="card-header-right-icon d-flex gap-2">
                                <button class="btn btn-primary btn-sm text-white">
                                    <i data-feather="upload" style="width:13px;height:13px;"></i> Nahrát soubory
                                </button>
                                <button class="btn btn-outline-primary btn-sm">
                                    <i data-feather="folder-plus" style="width:13px;height:13px;"></i> Nová složka
                                </button>
                            </div>
                        </div>
                        {{-- Path bar --}}
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <a href="#" class="btn btn-xs btn-outline-secondary"><i data-feather="arrow-left" style="width:12px;height:12px;"></i></a>
                            <a href="#" class="btn btn-xs btn-outline-secondary"><i data-feather="arrow-right" style="width:12px;height:12px;"></i></a>
                            <a href="#" class="btn btn-xs btn-outline-secondary"><i data-feather="home" style="width:12px;height:12px;"></i></a>
                            <div class="input-group flex-1" style="max-width:400px;">
                                <span class="input-group-text f-12">/</span>
                                <input type="text" class="form-control form-control-sm" value="public/uploads">
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-light-info f-12 mb-3">
                            <i data-feather="info" style="width:12px;height:12px;"></i>
                            Správce souborů zobrazuje soubory z <code>public/uploads/</code>. Pro správu serverových souborů použijte SSH/SFTP přístup.
                        </div>
                        <div class="file-manager-grid">
                            <div class="grid grid-cols-12 gap-3">
                                @foreach([['uploads','folder'],['images','folder'],['documents','folder'],['backups','folder']] as [$name,$type])
                                <div class="col-span-2 xl:col-span-3 sm:col-span-4 text-center">
                                    <div class="folder p-3 border rounded cursor-pointer" style="border-radius:10px !important;">
                                        <div class="folder-icon-container mb-2">
                                            <i data-feather="{{ $type === 'folder' ? 'folder' : 'file' }}" style="width:40px;height:40px;color:rgba(var(--theme-default),1);"></i>
                                        </div>
                                        <p class="f-12 mb-0 f-w-500">{{ $name }}/</p>
                                        <small class="f-light f-11">Složka</small>
                                    </div>
                                </div>
                                @endforeach
                                @foreach([['readme.txt','file-text'],['config.php','code'],['storage.log','terminal']] as [$file,$icon])
                                <div class="col-span-2 xl:col-span-3 sm:col-span-4 text-center">
                                    <div class="folder p-3 border rounded cursor-pointer" style="border-radius:10px !important;">
                                        <div class="folder-icon-container mb-2">
                                            <i data-feather="{{ $icon }}" style="width:36px;height:36px;opacity:.5;"></i>
                                        </div>
                                        <p class="f-12 mb-0 f-w-500 text-truncate">{{ $file }}</p>
                                        <small class="f-light f-11">Soubor</small>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
