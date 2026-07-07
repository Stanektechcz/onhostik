@extends('layouts.panel')

@section('title', 'WHOIS vyhledávání')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="WHOIS vyhledávání domény">
        <form id="whoisForm" class="row g-3 mb-4">
            <div class="col-md-8">
                <input type="text" id="domainInput" class="form-control" placeholder="Zadejte doménu (např. example.cz)" autocomplete="off">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Vyhledat</button>
            </div>
        </form>

        <div id="whoisResult" class="d-none">
            <h6 class="text-muted">Výsledek WHOIS</h6>
            <pre id="whoisOutput" class="bg-dark text-light p-3 rounded" style="max-height:500px;overflow-y:auto;font-size:0.8rem"></pre>
        </div>
        <div id="whoisError" class="alert alert-danger d-none"></div>
        <div id="whoisLoading" class="text-muted d-none">Načítám...</div>
    </x-panel.card>
</div>

<script>
document.getElementById('whoisForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const domain = document.getElementById('domainInput').value.trim();
    if (!domain) return;

    document.getElementById('whoisResult').classList.add('d-none');
    document.getElementById('whoisError').classList.add('d-none');
    document.getElementById('whoisLoading').classList.remove('d-none');

    try {
        const resp = await fetch('{{ route('admin.domain-whois.lookup') }}', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'},
            body: JSON.stringify({domain})
        });
        const data = await resp.json();
        document.getElementById('whoisLoading').classList.add('d-none');

        if (data.error) {
            document.getElementById('whoisError').textContent = data.error;
            document.getElementById('whoisError').classList.remove('d-none');
        } else {
            document.getElementById('whoisOutput').textContent = data.raw || JSON.stringify(data, null, 2);
            document.getElementById('whoisResult').classList.remove('d-none');
        }
    } catch (err) {
        document.getElementById('whoisLoading').classList.add('d-none');
        document.getElementById('whoisError').textContent = 'Chyba při načítání dat.';
        document.getElementById('whoisError').classList.remove('d-none');
    }
});
</script>
@endsection
