@extends('layouts.auth')

@section('title', 'Pozvánka do účtu')

@section('content')
    @if($invalid)
        <h2 class="mergecolor mb-2"><b>Pozvánka není platná</b></h2>
        <p class="seccolor mb-4">Tato pozvánka již byla použita, zrušena, nebo její platnost vypršela.</p>
        <a href="{{ route('login') }}" class="btn btn-default-yellow-fill">Přejít na přihlášení</a>
    @else
        <h2 class="mergecolor mb-2"><b>Pozvánka do účtu {{ $accountName }}</b></h2>
        <p class="seccolor mb-4">Pozvánka byla vystavena pro <b>{{ $email }}</b>.</p>

        @if(session('status'))
            <div class="alert alert-success mb-4" role="alert">{{ session('status') }}</div>
        @endif

        @if($wrongUser)
            <div class="alert alert-warning mb-4" role="alert">
                Jste přihlášeni pod jiným e-mailem. Odhlaste se a přihlaste se jako {{ $email }}, nebo pozvánku přijměte v anonymním okně.
            </div>
        @elseif($mustLogIn)
            <p class="seccolor mb-4">Tento e-mail už u nás účet má. Přihlaste se a poté pozvánku přijměte.</p>
            <a href="{{ route('login') }}" class="btn btn-default-yellow-fill">Přihlásit se</a>
        @elseif($needsAccount)
            <p class="seccolor mb-4">Vytvořte si heslo a získáte přístup k účtu.</p>
            <div class="cd-filter-block mb-0">
                <div class="cd-filter-content">
                    <form method="POST" action="{{ route('invitation.accept.store', ['token' => $token]) }}">
                        @csrf
                        <div class="row">
                            <div class="col-md-12 position-relative mb-3">
                                <div class="general-input">
                                    <label class="seccolor d-block pb-1" for="name">Jméno</label>
                                    <input id="name" class="fill-input w-100" type="text" name="name"
                                           value="{{ old('name') }}" required autofocus placeholder="Jan Novák">
                                    @error('name')<div class="text-danger mt-1 f-13">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-6 position-relative mb-3">
                                <div class="general-input">
                                    <label class="seccolor d-block pb-1" for="password">Heslo</label>
                                    <input id="password" class="fill-input w-100" type="password" name="password"
                                           required autocomplete="new-password" placeholder="••••••••">
                                    @error('password')<div class="text-danger mt-1 f-13">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-6 position-relative mb-3">
                                <div class="general-input">
                                    <label class="seccolor d-block pb-1" for="password_confirmation">Heslo znovu</label>
                                    <input id="password_confirmation" class="fill-input w-100" type="password"
                                           name="password_confirmation" required autocomplete="new-password" placeholder="••••••••">
                                </div>
                            </div>
                            <div class="col-md-12 mt-3 position-relative">
                                <button type="submit" class="btn btn-default-yellow-fill">
                                    Vytvořit účet a přijmout <i class="fas fa-check ps-1 f-15"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @else
            {{-- Signed in as the invited address → one-click accept. --}}
            <form method="POST" action="{{ route('invitation.accept.store', ['token' => $token]) }}">
                @csrf
                <button type="submit" class="btn btn-default-yellow-fill">
                    Přijmout pozvánku <i class="fas fa-check ps-1 f-15"></i>
                </button>
            </form>
        @endif
    @endif
@endsection
