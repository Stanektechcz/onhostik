@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Role a oprávnění';
    $breadcrumbItems = ['Role a oprávnění' => ''];
@endphp

@section('title', 'Role a oprávnění')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="container role-permission-wrapper">
        <div class="grid grid-cols-12 card-gap">

            <div class="col-span-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top">
                            <h5>Správa rolí</h5>
                            <div class="card-header-right-icon">
                                <button class="btn btn-primary btn-sm text-white"
                                        data-bs-toggle="modal" data-bs-target="#rolePermissionModal">
                                    <i data-feather="plus" style="width:13px;height:13px;"></i> Přidat roli
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body pt-0 px-0">
                        <div class="list-product permission-table">
                            <div class="recent-table overflow-x-auto custom-scrollbar">
                                <table class="table" id="roles-permission">
                                    <thead>
                                        <tr>
                                            <th></th>
                                            <th><span class="f-light font-semibold">Název role</span></th>
                                            <th><span class="f-light font-semibold">Oprávnění</span></th>
                                            <th><span class="f-light font-semibold">Uživatelů</span></th>
                                            <th><span class="f-light font-semibold">Vytvořeno</span></th>
                                            <th><span class="f-light font-semibold">Akce</span></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($roles ?? [] as $role)
                                        <tr class="product-removes inbox-data">
                                            <td></td>
                                            <td class="f-w-600">{{ $role->name }}</td>
                                            <td>
                                                @foreach($role->permissions->take(3) as $perm)
                                                    <span class="badge badge-light-primary me-1 f-11">{{ $perm->name }}</span>
                                                @endforeach
                                                @if($role->permissions->count() > 3)
                                                    <span class="badge badge-light-secondary f-11">+{{ $role->permissions->count() - 3 }} dalších</span>
                                                @endif
                                            </td>
                                            <td class="f-light">{{ $role->users()->count() }}</td>
                                            <td class="f-12">{{ $role->created_at?->format('d.m.Y') }}</td>
                                            <td>
                                                <div class="common-align gap-2 justify-start">
                                                    <a class="square-white trash-6" href="#"
                                                       data-bs-toggle="tooltip" data-tooltip="Smazat"
                                                       onclick="return confirm('Smazat roli?')">
                                                        <svg><use href="{{ asset('panel/svg/icon-sprite.svg#trash1') }}"></use></svg>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            @foreach([['admin','Plný přístup k systému',1],['partner','Přístup k partnerskému portálu',0],['customer','Přístup k zákaznickému panelu',0]] as [$name, $desc, $cnt])
                                            <td></td>
                                            <td class="f-w-600">{{ $name }}</td>
                                            <td>
                                                <span class="badge badge-light-primary me-1 f-11">access-admin</span>
                                                <span class="badge badge-light-secondary f-11">+12 dalších</span>
                                            </td>
                                            <td class="f-light">{{ $cnt }}</td>
                                            <td class="f-12">{{ now()->format('d.m.Y') }}</td>
                                            <td>
                                                <div class="common-align gap-2 justify-start">
                                                    <a class="square-white trash-6" href="#" data-bs-toggle="tooltip" data-tooltip="Smazat">
                                                        <svg><use href="{{ asset('panel/svg/icon-sprite.svg#trash1') }}"></use></svg>
                                                    </a>
                                                </div>
                                            </td>
                                            @endforeach
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Permissions matrix --}}
            <div class="col-span-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top"><h5>Matice oprávnění</h5></div>
                    </div>
                    <div class="card-body">
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="table table-bordered">
                                <thead>
                                    <tr class="bg-primary text-white">
                                        <th>Oprávnění</th>
                                        <th class="text-center">Admin</th>
                                        <th class="text-center">Partner</th>
                                        <th class="text-center">Zákazník</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach([
                                        ['access-admin','Přístup do adminu', true, false, false],
                                        ['access-partner','Přístup do partneru', true, true, false],
                                        ['view-invoices','Zobrazit faktury', true, false, true],
                                        ['manage-products','Spravovat produkty', true, false, false],
                                        ['manage-customers','Spravovat zákazníky', true, false, false],
                                        ['view-orders','Zobrazit objednávky', true, false, true],
                                        ['manage-partners','Spravovat partnery', true, false, false],
                                    ] as [$perm, $label, $admin, $partner, $customer])
                                    <tr>
                                        <td>
                                            <span class="f-w-500">{{ $label }}</span>
                                            <br><small class="f-light f-11">{{ $perm }}</small>
                                        </td>
                                        <td class="text-center">
                                            @if($admin)<i data-feather="check" style="width:16px;height:16px;color:rgba(var(--success-color),1);"></i>@else<i data-feather="x" style="width:16px;height:16px;color:rgba(var(--danger-color),1);"></i>@endif
                                        </td>
                                        <td class="text-center">
                                            @if($partner)<i data-feather="check" style="width:16px;height:16px;color:rgba(var(--success-color),1);"></i>@else<i data-feather="x" style="width:16px;height:16px;color:rgba(var(--danger-color),1);"></i>@endif
                                        </td>
                                        <td class="text-center">
                                            @if($customer)<i data-feather="check" style="width:16px;height:16px;color:rgba(var(--success-color),1);"></i>@else<i data-feather="x" style="width:16px;height:16px;color:rgba(var(--danger-color),1);"></i>@endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- Add role modal --}}
<div class="modal fade" id="rolePermissionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Přidat roli</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body custom-input">
                <div class="mb-3">
                    <label class="form-label">Název role</label>
                    <input type="text" class="form-control" placeholder="např. editor">
                </div>
                <div class="mb-3">
                    <label class="form-label">Popis</label>
                    <textarea class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušit</button>
                <button type="button" class="btn btn-primary text-white">Vytvořit roli</button>
            </div>
        </div>
    </div>
</div>
@endsection
