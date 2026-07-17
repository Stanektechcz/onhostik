@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Reseller program';
    $breadcrumbItems = ['Reseller program' => ''];
@endphp

@section('title', 'Reseller program')

@section('content')
<div class="container-fluid">

    @if(session('status'))
        <div class="alert alert-light-success mb-4">{{ session('status') }}</div>
    @endif

    @if(($profile ?? null) !== null)
        {{-- Already has a profile — show status --}}
        @php
            $statusLabels = ['pending' => 'Čeká na schválení', 'active' => 'Aktivní', 'suspended' => 'Pozastaveno', 'rejected' => 'Zamítnuto'];
            $statusBadges = ['pending' => 'warning', 'active' => 'success', 'suspended' => 'danger', 'rejected' => 'secondary'];
        @endphp
        <div class="card">
            <div class="card-header card-no-border">
                <h5>Váš reseller profil</h5>
            </div>
            <div class="card-body pt-0">
                <ul class="list-group list-group-flush mb-3">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="f-light">Obchodní jméno</span>
                        <strong>{{ $profile->business_name }}</strong>
                    </li>
                    @if($profile->custom_domain)
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="f-light">Vlastní doména</span>
                        <strong>{{ $profile->custom_domain }}</strong>
                    </li>
                    @endif
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="f-light">Stav</span>
                        <span class="badge badge-light-{{ $statusBadges[$profile->status] ?? 'secondary' }}">
                            {{ $statusLabels[$profile->status] ?? $profile->status }}
                        </span>
                    </li>
                </ul>

                @if($profile->status === 'active')
                    <a href="{{ route('reseller.dashboard') }}" class="btn btn-primary btn-sm">
                        <i data-feather="briefcase" style="width:13px;height:13px;"></i>
                        Reseller Dashboard
                    </a>
                @elseif($profile->status === 'pending')
                    <p class="f-light f-12 mb-0">
                        <i data-feather="clock" style="width:13px;height:13px;"></i>
                        Žádost byla přijata a čeká na schválení administrátorem. Budeme vás informovat e-mailem.
                    </p>
                @endif
            </div>
        </div>

    @else
        {{-- No profile yet — show program info + apply form --}}
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header card-no-border">
                        <h5>Žádost o reseller program</h5>
                    </div>
                    <div class="card-body pt-0">
                        <p class="f-light f-13 mb-4">
                            Připojte se k reseller programu OnHost a nabízejte hosting pod vlastní značkou
                            s vlastním cenovým markem.
                        </p>

                        @if($errors->any())
                            <div class="alert alert-light-danger mb-3">
                                @foreach($errors->all() as $err)
                                    <p class="mb-0 f-12">{{ $err }}</p>
                                @endforeach
                            </div>
                        @endif

                        <form method="POST" action="{{ route('panel.reseller-program.apply') }}" class="theme-form">
                            @csrf
                            <div class="form-group mb-3">
                                <label class="col-form-label">Obchodní jméno <span class="txt-danger">*</span></label>
                                <input type="text" name="business_name" value="{{ old('business_name') }}"
                                       class="form-control @error('business_name') is-invalid @enderror"
                                       placeholder="Název vaší firmy nebo značky" required maxlength="255">
                                @error('business_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group mb-3">
                                <label class="col-form-label">Vlastní doména <span class="f-light f-12">(volitelné)</span></label>
                                <input type="text" name="custom_domain" value="{{ old('custom_domain') }}"
                                       class="form-control @error('custom_domain') is-invalid @enderror"
                                       placeholder="hosting.mujfirma.cz">
                                @error('custom_domain')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="f-light f-11">Doména, pod kterou budete prodávat produkty.</small>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i data-feather="send" style="width:13px;height:13px;"></i>
                                Odeslat žádost
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header card-no-border">
                        <h6>Výhody reseller programu</h6>
                    </div>
                    <div class="card-body pt-0">
                        <ul class="list-unstyled">
                            <li class="mb-2 d-flex align-items-start gap-2">
                                <i data-feather="check-circle" style="width:14px;height:14px;color:rgba(var(--success-color),1);flex-shrink:0;margin-top:2px;"></i>
                                <span class="f-light f-13">Vlastní cenový markup na všechny produkty</span>
                            </li>
                            <li class="mb-2 d-flex align-items-start gap-2">
                                <i data-feather="check-circle" style="width:14px;height:14px;color:rgba(var(--success-color),1);flex-shrink:0;margin-top:2px;"></i>
                                <span class="f-light f-13">Možnost prodeje pod vlastní doménou</span>
                            </li>
                            <li class="mb-2 d-flex align-items-start gap-2">
                                <i data-feather="check-circle" style="width:14px;height:14px;color:rgba(var(--success-color),1);flex-shrink:0;margin-top:2px;"></i>
                                <span class="f-light f-13">Plná technická podpora pro vaše zákazníky</span>
                            </li>
                            <li class="mb-2 d-flex align-items-start gap-2">
                                <i data-feather="check-circle" style="width:14px;height:14px;color:rgba(var(--success-color),1);flex-shrink:0;margin-top:2px;"></i>
                                <span class="f-light f-13">Flexibilní branding a nastavení</span>
                            </li>
                        </ul>
                        <hr class="mt-2">
                        <p class="f-light f-11 mb-0">
                            Po odeslání žádosti vás kontaktujeme do 1–2 pracovních dní.<br>
                            Dotazy: <a href="mailto:info@onhost.cz">info@onhost.cz</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
@endsection
