@extends('layouts.panel')

@section('title', 'Nastavení widgetů dashboardu')

@section('content')
<div class="container py-5">
    <h2 class="mb-4">Nastavení widgetů</h2>

    @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <p class="text-muted mb-4">Vyberte, které widgety se mají zobrazovat na vašem dashboardu a v jakém pořadí.</p>

            <div id="widgetList">
                @foreach($widgets as $i => $widget)
                <div class="flex items-center gap-3 mb-2 p-3 border rounded bg-light widget-item" data-key="{{ $widget['key'] }}">
                    <span class="text-muted" style="cursor:grab">&#9776;</span>
                    <div class="grow font-semibold">{{ $widget['label'] }}</div>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input widget-toggle" type="checkbox" id="w_{{ $widget['key'] }}" @checked($widget['is_visible'])>
                        <label class="form-check-label text-muted" for="w_{{ $widget['key'] }}">Zobrazit</label>
                    </div>
                </div>
                @endforeach
            </div>

            <button class="btn btn-primary mt-3" id="saveWidgets">Uložit nastavení</button>
        </div>
    </div>
</div>

<script>
document.getElementById('saveWidgets').addEventListener('click', async function() {
    const items = document.querySelectorAll('.widget-item');
    const widgets = Array.from(items).map((item, i) => ({
        key: item.dataset.key,
        position: i,
        is_visible: item.querySelector('.widget-toggle').checked
    }));

    const resp = await fetch('{{ route('panel.dashboard-widgets.update') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({widgets})
    });

    if (resp.ok) {
        this.textContent = 'Uloženo ✓';
        this.classList.replace('btn-primary', 'btn-success');
        setTimeout(() => {
            this.textContent = 'Uložit nastavení';
            this.classList.replace('btn-success', 'btn-primary');
        }, 2000);
    }
});
</script>
@endsection
