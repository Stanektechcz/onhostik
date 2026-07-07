@extends('layouts.panel')

@section('title', 'Šablony faktur')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="Šablony opakujících se faktur">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Zákazník</th>
                        <th>Název</th>
                        <th>Měna</th>
                        <th>Položek</th>
                        <th>Aktivní</th>
                        <th>Vytvořeno</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($templates as $t)
                    <tr>
                        <td>{{ $t->customer?->company_name ?? ('Zákazník #' . $t->customer_id) }}</td>
                        <td>{{ $t->name }}</td>
                        <td>{{ $t->currency }}</td>
                        <td>{{ count($t->line_items ?? []) }}</td>
                        <td>
                            @if($t->is_active)
                                <span class="badge bg-success">Ano</span>
                            @else
                                <span class="badge bg-secondary">Ne</span>
                            @endif
                        </td>
                        <td class="text-muted small">{{ $t->created_at?->format('d.m.Y') }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.invoice-templates.destroy', $t) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Smazat</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Žádné šablony.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $templates->links() }}</div>
    </x-panel.card>
</div>
@endsection
