@extends('layouts.panel')

@php
    use App\Domains\Shared\Support\MoneyFormatter;
    $breadcrumbTitle = __('panel.services.change_plan');
    $breadcrumbItems = [
        __('panel.nav.services') => route('panel.services.index'),
        $service->label          => route('panel.services.show', $service),
        __('panel.services.change_plan') => '',
    ];
@endphp

@section('title', __('panel.services.change_plan') . ' — ' . $service->label)

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">

        {{-- Current plan info --}}
        <div class="col-span-4 xl:col-span-12">
            <x-panel.card :title="__('panel.services.current_plan')">
                <div class="flex items-center gap-3 mb-3">
                    <div class="bg-light-primary rounded p-2">
                        <i data-feather="server" class="font-primary"></i>
                    </div>
                    <div>
                        <p class="f-w-600 mb-0">{{ $service->label }}</p>
                        <p class="f-light f-12 mb-0">{{ $service->product?->name }}</p>
                    </div>
                </div>
                <x-panel.status-badge :status="$service->status" />

                @if(!empty($service->resources))
                    <hr class="my-3">
                    <p class="f-light f-12 mb-2">{{ __('panel.services.resources') }}</p>
                    @foreach($service->resources as $key => $val)
                        <div class="flex justify-between f-12 mb-1">
                            <span class="f-light">{{ __("front.resources.$key", [], 'cs') }}</span>
                            <span class="f-w-500">{{ is_array($val) ? json_encode($val) : $val }}</span>
                        </div>
                    @endforeach
                @endif

                <div class="mt-3">
                    <a href="{{ route('panel.services.show', $service) }}" class="btn btn-outline-secondary btn-sm">
                        <i data-feather="arrow-left" style="width:13px;height:13px"></i>
                        {{ __('panel.common.back') }}
                    </a>
                </div>
            </x-panel.card>
        </div>

        {{-- Available plans --}}
        <div class="col-span-8 xl:col-span-12">
            <x-panel.card :title="__('panel.services.available_plans')">
                @if($availablePlans->isEmpty())
                    <div class="text-center py-4">
                        <i data-feather="package" style="width:36px;height:36px" class="text-muted mb-2"></i>
                        <p class="f-light mb-0">{{ __('panel.services.no_other_plans') }}</p>
                    </div>
                @else
                    <div class="alert alert-light-info flex gap-2 items-start py-2 px-3 f-12 mb-3">
                        <i data-feather="info" style="width:14px;height:14px;margin-top:2px;flex-shrink:0" class="font-info"></i>
                        <span>{{ __('panel.services.plan_change_note') }}</span>
                    </div>

                    <div class="grid grid-cols-12 gap-3">
                        @foreach($availablePlans as $plan)
                            @php
                                $price = $plan->priceFor($currency);
                                $currentPlan = $currentPlanId ? \App\Domains\Products\Models\PricingPlan::find($currentPlanId) : null;
                                $currentPrice = $currentPlan?->supportsCurrency($currency)
                                    ? $currentPlan->priceFor($currency)->getAmount()->toFloat() / max(1, $currentPlan->billing_cycle->months())
                                    : null;
                                $newPrice = $price->getAmount()->toFloat() / max(1, $plan->billing_cycle->months());
                                $isUpgrade = $currentPrice === null || $newPrice > $currentPrice;
                                $upgradeLabel = $isUpgrade ? 'primary' : 'warning';
                                $upgradeText  = $isUpgrade ? __('panel.services.upgrade') : __('panel.services.downgrade');
                            @endphp
                            <div class="col-span-6 md:col-span-12">
                                <div class="border rounded p-3 h-full">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <span class="f-w-600">{{ $plan->name }}</span>
                                            <span class="badge badge-light-{{ $upgradeLabel }} ms-2 f-11">{{ $upgradeText }}</span>
                                        </div>
                                        <div class="text-right">
                                            <div class="f-w-600 font-primary">{{ MoneyFormatter::format($price) }}</div>
                                            <div class="f-light f-11">/ {{ $plan->billing_cycle->label() }}</div>
                                        </div>
                                    </div>

                                    @if(!empty($plan->resources))
                                        <ul class="list-unstyled f-12 f-light mb-3">
                                            @foreach($plan->resources as $rk => $rv)
                                                <li class="mb-1">
                                                    <i data-feather="check" style="width:11px;height:11px" class="font-success me-1"></i>
                                                    {{ __("front.resources.$rk", [], 'cs') }}: <strong>{{ \App\Domains\Shared\Support\ResourceFormatter::format($rk, $rv) }}</strong>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif

                                    <button type="button"
                                            class="btn btn-{{ $upgradeLabel }} btn-sm w-full plan-change-btn"
                                            data-plan-id="{{ $plan->id }}"
                                            data-plan-name="{{ $plan->name }}"
                                            data-action-label="{{ $upgradeText }}"
                                            data-preview-url="{{ route('panel.services.change-plan-preview', $service) }}"
                                            data-submit-url="{{ route('panel.services.apply-change-plan', $service) }}">
                                        {{ $upgradeText }} → {{ $plan->name }}
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @push('scripts')
                    <script>
                    document.querySelectorAll('.plan-change-btn').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            var planId      = this.dataset.planId;
                            var planName    = this.dataset.planName;
                            var actionLabel = this.dataset.actionLabel;
                            var previewUrl  = this.dataset.previewUrl + '?plan_id=' + planId;
                            var submitUrl   = this.dataset.submitUrl;

                            var modal = document.getElementById('planChangeModal');
                            var title = document.getElementById('pcmTitle');
                            var body  = document.getElementById('pcmBody');
                            var form  = document.getElementById('pcmForm');

                            title.textContent = actionLabel + ' na plán ' + planName;
                            body.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Načítám náhled…</div>';
                            form.action = submitUrl;
                            document.getElementById('pcmPlanId').value = planId;

                            var bsModal = new bootstrap.Modal(modal);
                            bsModal.show();

                            fetch(previewUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                                .then(function(r) { return r.json(); })
                                .then(function(d) {
                                    var cur  = d.current_plan_price.toFixed(2) + ' ' + d.currency;
                                    var next = d.new_plan_price.toFixed(2) + ' ' + d.currency;
                                    var html = '<table class="table table-sm table-borderless mb-3">'
                                        + '<tr><td class="f-light">Aktuální cena / cyklus</td><td class="f-w-500">' + cur + '</td></tr>'
                                        + '<tr><td class="f-light">Nová cena / cyklus</td><td class="f-w-600 ' + (d.is_upgrade ? 'txt-primary' : 'txt-warning') + '">' + next + '</td></tr>'
                                        + '</table>';
                                    if (d.prorated_days > 0 && d.prorated_amount > 0) {
                                        html += '<div class="alert alert-light-' + (d.is_upgrade ? 'info' : 'warning') + ' f-12">'
                                            + '<i data-feather="info" style="width:13px;height:13px"></i> '
                                            + 'Poměrná část za zbývajících <strong>' + d.prorated_days + ' dní</strong>: <strong>' + d.prorated_amount.toFixed(2) + ' ' + d.currency + '</strong>'
                                            + '</div>';
                                    } else {
                                        html += '<div class="alert alert-light-success f-12">Žádný příplatek dnes — změna nastoupí od příštího fakturačního cyklu.</div>';
                                    }
                                    body.innerHTML = html;
                                    if (typeof feather !== 'undefined') { feather.replace(); }
                                })
                                .catch(function() {
                                    body.innerHTML = '<p class="text-danger f-12">Nepodařilo se načíst náhled ceny.</p>';
                                });
                        });
                    });
                    </script>
                    @endpush
                @endif
            </x-panel.card>
        </div>

    </div>
</div>

{{-- Plan-change confirmation modal --}}
<div class="modal fade" id="planChangeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pcmTitle">Změna plánu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="pcmBody"></div>
                <p class="f-12 f-light mb-0">Potvrďte změnu plánu. Přesměrujeme vás na dokončení objednávky.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Zrušit</button>
                <form id="pcmForm" method="POST" action="">
                    @csrf
                    <input type="hidden" name="plan_id" id="pcmPlanId" value="">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i data-feather="check" style="width:13px;height:13px"></i>
                        Potvrdit změnu
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
