@extends('layouts.panel')

@section('title', 'Hromadné operace')

@section('content')
<div class="container-fluid py-4">
    <h1 class="h4 mb-4">Hromadné operace (Bulk)</h1>

    @if(session('status'))
        <div class="alert alert-success py-2">{{ session('status') }}</div>
    @endif

    <div class="row g-3">

        {{-- Services --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <strong>Služby</strong>
                    <span class="badge bg-light text-dark float-end">{{ number_format($serviceCount) }} celkem</span>
                </div>
                <div class="card-body">

                    <p class="small text-muted">Zadejte ID služeb oddělená čárkami.</p>

                    {{-- Extend due date --}}
                    <form action="{{ route('admin.bulk.service-extend') }}" method="POST" class="mb-3">
                        @csrf
                        <label class="form-label small fw-semibold">Prodloužit splatnost</label>
                        <input type="text" name="ids[]" id="extend_ids" class="form-control form-control-sm mb-1"
                               placeholder="ID: 1,2,3" required>
                        <script>
                            document.getElementById('extend_ids').addEventListener('change', function() {
                                var val = this.value.replace(/\s+/g, '');
                                this.name = 'ids[]';
                                // Convert comma-separated to multiple inputs handled server-side
                            });
                        </script>
                        <div class="d-flex gap-2 mt-1">
                            <input type="number" name="days" class="form-control form-control-sm" placeholder="Dní" min="1" max="365" required>
                            <button class="btn btn-sm btn-outline-primary text-nowrap">Prodloužit</button>
                        </div>
                    </form>

                    {{-- Terminate --}}
                    <form action="{{ route('admin.bulk.service-terminate') }}" method="POST" class="mb-3">
                        @csrf
                        <label class="form-label small fw-semibold text-danger">Ukončit služby</label>
                        <input type="text" name="_ids_terminate" class="form-control form-control-sm mb-1"
                               placeholder="ID oddělená čárkami" required>
                        <input type="text" name="reason" class="form-control form-control-sm mb-1"
                               placeholder="Důvod ukončení" required>
                        <button class="btn btn-sm btn-outline-danger">Ukončit</button>
                    </form>

                </div>
            </div>
        </div>

        {{-- Invoices --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-warning text-dark">
                    <strong>Faktury</strong>
                    <span class="badge bg-light text-dark float-end">{{ number_format($invoiceCount) }} celkem</span>
                </div>
                <div class="card-body">
                    <p class="small text-muted">Storno funguje pouze u faktur ve stavu draft nebo pending.</p>

                    <form action="{{ route('admin.bulk.invoice-void') }}" method="POST" class="mb-3">
                        @csrf
                        <label class="form-label small fw-semibold">Stornovat faktury</label>
                        <input type="text" name="_ids_void" class="form-control form-control-sm mb-1"
                               placeholder="ID oddělená čárkami" required>
                        <button class="btn btn-sm btn-outline-warning text-dark">Stornovat</button>
                    </form>

                    <hr>
                    <a href="{{ route('admin.invoices.index') }}" class="btn btn-sm btn-outline-secondary w-100">
                        → Hromadné platby (v seznamu faktur)
                    </a>
                </div>
            </div>
        </div>

        {{-- Customers --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <strong>Zákazníci</strong>
                    <span class="badge bg-light text-dark float-end">{{ number_format($customerCount) }} celkem</span>
                </div>
                <div class="card-body">
                    <p class="small text-muted">Export vybraných zákazníků do CSV.</p>

                    <form action="{{ route('admin.bulk.customer-export') }}" method="POST">
                        @csrf
                        <label class="form-label small fw-semibold">Export zákazníků (CSV)</label>
                        <input type="text" name="_ids_customer" class="form-control form-control-sm mb-2"
                               placeholder="ID oddělená čárkami" required>
                        <button class="btn btn-sm btn-outline-info">Stáhnout CSV</button>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <div class="mt-4">
        <p class="text-muted small">
            <strong>Pozor:</strong> Hromadné operace jsou nevratné (ukončení, storno). Vždy si ověřte ID před odesláním.
            Maximum 50 služeb pro ukončení, 200 pro prodloužení, 100 pro storno faktur, 500 pro export.
        </p>
    </div>
</div>

{{-- Client-side CSV parsing: convert comma-separated IDs to array inputs --}}
<script>
document.querySelectorAll('form[action*="bulk"]').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        const idInput = form.querySelector('input[name^="_ids"]');
        if (!idInput) return;

        e.preventDefault();
        const ids = idInput.value.split(',').map(s => s.trim()).filter(Boolean);
        idInput.remove();

        ids.forEach(id => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'ids[]';
            hidden.value = id;
            form.appendChild(hidden);
        });

        form.submit();
    });
});

// Also handle the service-extend form which already has ids[]
document.querySelector('form[action*="service-extend"]')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const input = this.querySelector('input[id="extend_ids"]');
    if (!input) { this.submit(); return; }

    const ids = input.value.split(',').map(s => s.trim()).filter(Boolean);
    input.remove();
    ids.forEach(id => {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'ids[]';
        hidden.value = id;
        this.appendChild(hidden);
    });
    this.submit();
});
</script>
@endsection
