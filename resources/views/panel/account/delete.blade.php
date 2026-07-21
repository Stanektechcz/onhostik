@extends('layouts.panel')

@section('title', 'Smazání účtu')

@section('content')
<div class="container py-5">
    <div class="grid grid-cols-12 justify-center">
        <div class="col-span-12 md:col-span-8 col-span-12 lg:col-span-6">
            <h2 class="mb-4 text-danger">Žádost o smazání účtu</h2>

            @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            @if($existing)
            <div class="alert alert-warning">
                <strong>Žádost již existuje.</strong>
                Vaše žádost o smazání účtu je ve stavu <strong>{{ $existing->status }}</strong>.
                @if($existing->scheduled_deletion_at)
                    Účet bude smazán {{ $existing->scheduled_deletion_at->format('d.m.Y') }}.
                @endif
            </div>
            @else
            <div class="alert alert-danger">
                <strong>Varování!</strong> Tato akce je nevratná. Všechna vaše data budou trvale odstraněna do 30 dní.
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('panel.account.delete.store') }}" data-confirm="Opravdu chcete požádat o smazání účtu? Tato akce je nevratná.">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Důvod odchodu (volitelné)</label>
                            <textarea name="reason" class="form-control @error('reason') is-invalid @enderror" rows="4" maxlength="1000" placeholder="Povězte nám, proč odcházíte...">{{ old('reason') }}</textarea>
                            @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="flex gap-2">
                            <a href="{{ route('panel.dashboard') }}" class="btn btn-outline-secondary">Zrušit</a>
                            <button type="submit" class="btn btn-danger">Odeslat žádost o smazání</button>
                        </div>
                    </form>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
