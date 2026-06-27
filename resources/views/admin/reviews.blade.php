@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Recenze';
    $breadcrumbItems = ['Recenze' => ''];
@endphp

@section('title', 'Správa recenzí')

@section('content')
<div class="container-fluid">
    <div class="container manage-review-wrapper">
        <div class="grid grid-cols-12 card-gap">
            <div class="col-span-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top">
                            <h5>Správa recenzí zákazníků</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        {{-- Filters --}}
                        <div class="d-flex gap-3 mb-3 flex-wrap">
                            <select class="form-select w-auto form-select-sm">
                                <option>Hodnocení</option>
                                <option>★★★★★ (5)</option>
                                <option>★★★★ (4)</option>
                                <option>★★★ (3)</option>
                                <option>★★ (2)</option>
                                <option>★ (1)</option>
                            </select>
                            <select class="form-select w-auto form-select-sm">
                                <option>Stav</option>
                                <option>Schválená</option>
                                <option>Čeká</option>
                                <option>Zamítnuta</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body pt-0 px-0">
                        <div class="manage-review">
                            <div class="recent-table overflow-x-auto custom-scrollbar">
                                <table class="table" id="manage-review-table">
                                    <thead>
                                        <tr>
                                            <th><span class="f-light font-semibold">Zákazník</span></th>
                                            <th><span class="f-light font-semibold">Služba</span></th>
                                            <th><span class="f-light font-semibold">Hodnocení</span></th>
                                            <th><span class="f-light font-semibold">Recenze</span></th>
                                            <th><span class="f-light font-semibold">Datum</span></th>
                                            <th><span class="f-light font-semibold">Stav</span></th>
                                            <th><span class="f-light font-semibold">Akce</span></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="inbox-data">
                                            <td colspan="7" class="text-center py-5 f-light">
                                                <i data-feather="star" style="width:40px;height:40px;" class="d-block mx-auto mb-3 text-muted"></i>
                                                Modul recenzí bude implementován v dalším vydání.
                                                <br><small>Aktuálně zákazníci mohou hodnotit přes e-mail nebo tickety.</small>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
