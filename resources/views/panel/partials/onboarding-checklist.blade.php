{{-- Onboarding checklist — shown on dashboard until dismissed or completed --}}
@if ($showOnboarding ?? false)
<div class="row mb-3">
    <div class="col-12">
        <div class="card b-l-warning">
            <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i data-feather="check-square" class="me-1" style="width:18px;height:18px;"></i>
                    Začínáme — nastavte svůj účet
                </h5>
                <form method="POST" action="{{ route('panel.onboarding.dismiss') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-light btn-sm">
                        <i data-feather="x" style="width:14px;height:14px;"></i> Přeskočit
                    </button>
                </form>
            </div>
            <div class="card-body pt-2">
                <div class="mb-2 d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1" style="height:8px;">
                        <div class="progress-bar bg-warning"
                             role="progressbar"
                             style="width: {{ $onboardingPercent ?? 0 }}%"
                             aria-valuenow="{{ $onboardingPercent ?? 0 }}"
                             aria-valuemin="0"
                             aria-valuemax="100"></div>
                    </div>
                    <small class="text-muted text-nowrap">{{ $onboardingPercent ?? 0 }} %</small>
                </div>

                <div class="row g-2">

                    {{-- Step: Profile --}}
                    @php $done = $onboardingSteps['profile'] ?? false; @endphp
                    <div class="col-sm-6 col-xl-3">
                        <div class="d-flex align-items-start gap-2 p-2 rounded {{ $done ? 'bg-light-success' : 'bg-light' }}">
                            <span class="mt-1">
                                @if ($done)
                                    <i data-feather="check-circle" class="text-success" style="width:16px;height:16px;"></i>
                                @else
                                    <i data-feather="circle" class="text-muted" style="width:16px;height:16px;"></i>
                                @endif
                            </span>
                            <div>
                                <p class="mb-0 f-13 fw-medium {{ $done ? 'text-success' : '' }}">Doplňte profil</p>
                                <p class="mb-0 f-11 text-muted">Jméno firmy nebo telefon</p>
                                @if (! $done)
                                    <a href="{{ route('panel.account.profile') }}" class="f-11 text-warning">Upravit →</a>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Step: Billing address --}}
                    @php $done = $onboardingSteps['billing_address'] ?? false; @endphp
                    <div class="col-sm-6 col-xl-3">
                        <div class="d-flex align-items-start gap-2 p-2 rounded {{ $done ? 'bg-light-success' : 'bg-light' }}">
                            <span class="mt-1">
                                @if ($done)
                                    <i data-feather="check-circle" class="text-success" style="width:16px;height:16px;"></i>
                                @else
                                    <i data-feather="circle" class="text-muted" style="width:16px;height:16px;"></i>
                                @endif
                            </span>
                            <div>
                                <p class="mb-0 f-13 fw-medium {{ $done ? 'text-success' : '' }}">Fakturační adresa</p>
                                <p class="mb-0 f-11 text-muted">Potřebná pro vydávání faktur</p>
                                @if (! $done)
                                    <a href="{{ route('panel.account.billing') }}" class="f-11 text-warning">Nastavit →</a>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Step: 2FA --}}
                    @php $done = $onboardingSteps['two_factor'] ?? false; @endphp
                    <div class="col-sm-6 col-xl-3">
                        <div class="d-flex align-items-start gap-2 p-2 rounded {{ $done ? 'bg-light-success' : 'bg-light' }}">
                            <span class="mt-1">
                                @if ($done)
                                    <i data-feather="check-circle" class="text-success" style="width:16px;height:16px;"></i>
                                @else
                                    <i data-feather="circle" class="text-muted" style="width:16px;height:16px;"></i>
                                @endif
                            </span>
                            <div>
                                <p class="mb-0 f-13 fw-medium {{ $done ? 'text-success' : '' }}">Dvoufaktorové ověření</p>
                                <p class="mb-0 f-11 text-muted">Zabezpečte svůj účet</p>
                                @if (! $done)
                                    <a href="{{ route('panel.account.security') }}" class="f-11 text-warning">Aktivovat →</a>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Step: First service --}}
                    @php $done = $onboardingSteps['first_service'] ?? false; @endphp
                    <div class="col-sm-6 col-xl-3">
                        <div class="d-flex align-items-start gap-2 p-2 rounded {{ $done ? 'bg-light-success' : 'bg-light' }}">
                            <span class="mt-1">
                                @if ($done)
                                    <i data-feather="check-circle" class="text-success" style="width:16px;height:16px;"></i>
                                @else
                                    <i data-feather="circle" class="text-muted" style="width:16px;height:16px;"></i>
                                @endif
                            </span>
                            <div>
                                <p class="mb-0 f-13 fw-medium {{ $done ? 'text-success' : '' }}">Objednejte první službu</p>
                                <p class="mb-0 f-11 text-muted">Hosting, doména nebo VPS</p>
                                @if (! $done)
                                    <a href="{{ route('front.webhosting') }}" class="f-11 text-warning">Prohlédnout →</a>
                                @endif
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
@endif
