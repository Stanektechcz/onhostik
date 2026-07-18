@extends('layouts.panel')

@section('title', 'Záložní kódy 2FA')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="Záložní kódy pro dvoufaktorové ověření">
        <p class="text-muted f-14">Každý kód lze použít jednou při přihlášení, pokud nemáte přístup k autentizátoru. Uložte je na bezpečné místo.</p>

        @if($codes->isEmpty())
            <p class="text-muted">Žádné záložní kódy. Vygenerujte je kliknutím níže.</p>
        @else
        <div class="grid grid-cols-12 gap-2 mb-3">
            @foreach($codes as $code)
            <div class="col-span-12 md:col-span-3">
                <code class="block p-2 border rounded text-center {{ $code->isUsed() ? 'text-muted text-decoration-line-through' : 'f-w-600' }}">
                    {{ $code->code }}
                </code>
            </div>
            @endforeach
        </div>
        @endif

        <form method="POST" action="{{ route('panel.account.2fa-recovery.regenerate') }}"
              onsubmit="return confirm('Tím zneplatníte všechny stávající kódy. Pokračovat?')">
            @csrf
            <button type="submit" class="btn btn-outline-warning btn-sm">Vygenerovat nové kódy</button>
        </form>
    </x-panel.card>
</div>
@endsection
