@extends('layouts.panel')

@section('title', 'Fronta neúspěšných plateb')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="Obnovy s neúspěšnou platbou">
        <p class="text-muted f-12 mb-3">Faktury za obnovu, u nichž bylo zákazníkovi zasláno upozornění o selhání platby a stále nejsou uhrazeny.</p>

        @if($invoices->isEmpty())
            <p class="text-muted">Žádné neúspěšné platby.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Faktura</th>
                        <th>Zákazník</th>
                        <th>Služba</th>
                        <th class="text-right">Částka</th>
                        <th>Poslední upomínka</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $invoice)
                    <tr>
                        <td>
                            <a href="{{ route('admin.invoices.show', $invoice) }}" class="f-w-500">
                                {{ $invoice->number }}
                            </a>
                        </td>
                        <td>{{ $invoice->customer?->company_name ?? $invoice->customer?->full_name ?? '—' }}</td>
                        <td class="f-12 text-muted">{{ $invoice->renewalService?->label ?? '—' }}</td>
                        <td class="text-right f-w-600">{{ number_format($invoice->total->getMinorAmount()->toInt() / 100, 0, ',', ' ') }} Kč</td>
                        <td class="f-12">{{ $invoice->renewal_failure_notified_at?->format('d.m.Y H:i') ?? '—' }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.failed-payment-queue.resend', $invoice) }}">
                                @csrf
                                <button type="submit" class="btn btn-xs btn-outline-warning">
                                    Znovu upomenout
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $invoices->links() }}</div>
        @endif
    </x-panel.card>
</div>
@endsection
