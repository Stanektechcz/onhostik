@extends('layouts.panel')
@section('title', 'White-label nastavení resellerů')
@section('content')
<div class="container-fluid">
    <x-panel.flash />
    <x-panel.card title="Aktivní reselleři">
        @if($resellers->isEmpty())
            <p class="text-muted">Žádní aktivní reselleři.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Firma</th><th>Doména</th><th>Panel title</th><th>Support e-mail</th><th></th></tr></thead>
                <tbody>
                    @foreach($resellers as $r)
                    <tr>
                        <td>{{ $r->business_name }}</td>
                        <td>{{ $r->custom_domain ?? '—' }}</td>
                        <td>{{ $r->panel_title ?? '—' }}</td>
                        <td>{{ $r->support_email ?? '—' }}</td>
                        <td><a href="{{ route('admin.reseller-whitelabel.edit', $r) }}" class="btn btn-sm btn-outline-primary">Upravit</a></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </x-panel.card>
</div>
@endsection
