@extends('layouts.panel')

@section('title', 'Export audit logu')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="Export audit / activity logu do CSV">
        <form method="GET" action="{{ route('admin.audit-log-export.export') }}">
            <div class="grid grid-cols-12 gap-3 mb-4">
                <div class="col-span-12 md:col-span-3">
                    <label class="form-label">Od data</label>
                    <input type="date" name="from" class="form-control" value="{{ old('from') }}">
                </div>
                <div class="col-span-12 md:col-span-3">
                    <label class="form-label">Do data</label>
                    <input type="date" name="to" class="form-control" value="{{ old('to', date('Y-m-d')) }}">
                </div>
                <div class="col-span-12 md:col-span-3">
                    <label class="form-label">Log name</label>
                    <select name="log_name" class="form-select">
                        <option value="">Vše</option>
                        @foreach($logNames as $name)
                        <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-12 md:col-span-3">
                    <label class="form-label">ID uživatele</label>
                    <input type="number" name="causer_id" class="form-control" placeholder="volitelné">
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fa fa-download"></i> Exportovat CSV
            </button>
        </form>

        <div class="alert alert-info mt-4 mb-0">
            Export obsahuje max. 10 000 záznamů. Pro větší export použijte přímý přístup do databáze.
        </div>
    </x-panel.card>
</div>
@endsection
