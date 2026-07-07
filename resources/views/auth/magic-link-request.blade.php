@extends('layouts.front')

@section('title', 'Přihlášení bez hesla')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h4 class="mb-3">Přihlášení bez hesla</h4>

                    @if(session('status'))
                        <div class="alert alert-success">{{ session('status') }}</div>
                    @endif

                    <form method="POST" action="{{ route('magic-link.send') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">E-mailová adresa</label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                   value="{{ old('email') }}" required autofocus>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Zaslat přihlašovací odkaz</button>
                    </form>

                    <div class="mt-3 text-center">
                        <a href="/login" class="text-muted f-14">Zpět na přihlášení</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
