@extends('layouts.panel')

@section('title', 'Námitky k fakturám')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="Námitky k fakturám">
        @if($disputes->isEmpty())
            <p class="text-muted mb-0">Žádné námitky.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Faktura</th>
                        <th>Zákazník</th>
                        <th>Důvod</th>
                        <th>Stav</th>
                        <th>Podáno</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($disputes as $dispute)
                    <tr>
                        <td>
                            <a href="{{ route('admin.invoices.show', $dispute->invoice) }}" class="f-w-500">
                                {{ $dispute->invoice->number }}
                            </a>
                        </td>
                        <td>{{ $dispute->customer->company_name ?? $dispute->customer->full_name ?? '—' }}</td>
                        <td class="f-12 text-muted" style="max-width:250px;">{{ Str::limit($dispute->reason, 80) }}</td>
                        <td>
                            @if($dispute->status === 'open')
                                <span class="badge bg-warning text-dark">Otevřená</span>
                            @elseif($dispute->status === 'resolved')
                                <span class="badge bg-success">Vyřešena</span>
                            @else
                                <span class="badge bg-danger">Zamítnuta</span>
                            @endif
                        </td>
                        <td class="f-12">{{ $dispute->created_at?->format('d.m.Y') }}</td>
                        <td>
                            @if($dispute->status === 'open')
                            <button class="btn btn-xs btn-outline-primary" data-bs-toggle="modal" data-bs-target="#resolve-{{ $dispute->id }}">
                                Vyřešit
                            </button>
                            <!-- Resolve modal -->
                            <div class="modal fade" id="resolve-{{ $dispute->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <form method="POST" action="{{ route('admin.invoice-disputes.resolve', $dispute) }}">
                                        @csrf @method('PATCH')
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Vyřešit námitku</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p class="f-12 text-muted mb-2">Důvod zákazníka: {{ $dispute->reason }}</p>
                                                <div class="mb-3">
                                                    <label class="form-label">Výsledek</label>
                                                    <select name="status" class="form-select form-select-sm">
                                                        <option value="resolved">Vyřešena (uznána)</option>
                                                        <option value="rejected">Zamítnuta</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Poznámka admina</label>
                                                    <textarea name="admin_note" class="form-control form-control-sm" rows="3" required minlength="1" maxlength="1000"></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Zrušit</button>
                                                <button type="submit" class="btn btn-sm btn-primary">Uložit</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            @else
                                <small class="text-muted">{{ $dispute->admin_note ? Str::limit($dispute->admin_note, 40) : '—' }}</small>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $disputes->links() }}</div>
        @endif
    </x-panel.card>
</div>
@endsection
