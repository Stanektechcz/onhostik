{{--
  Billing cycle toggle: Monthly / Annual
  Props:
    $hasAnnual   (bool) — hide toggle when no annual plans exist
    $savePercent (int)  — e.g. 20 → "Ušetřete 20 %"
--}}
@props(['hasAnnual' => true, 'savePercent' => 0])

@if($hasAnnual)
<div class="billing-toggle-wrap text-center mb-4">
    <div class="billing-toggle d-inline-flex align-items-center gap-3 rounded-pill px-4 py-2"
         style="background:rgba(0,0,0,.08)">
        <span class="billing-lbl f-w-500 mergecolor billing-lbl-monthly active-lbl"
              id="lbl-monthly">{{ __('front.pricing.billing_monthly') }}</span>
        <div class="form-check form-switch mb-0">
            <input class="form-check-input billing-switch"
                   type="checkbox"
                   id="billing-cycle-toggle"
                   role="switch"
                   style="width:2.5rem;height:1.25rem;cursor:pointer">
        </div>
        <span class="billing-lbl mergecolor billing-lbl-annual" id="lbl-annual">
            {{ __('front.pricing.billing_annual') }}
            @if($savePercent > 0)
                <span class="badge badge-light-success ms-1 f-11">
                    {{ __('front.pricing.billing_save', ['pct' => $savePercent]) }}
                </span>
            @endif
        </span>
    </div>
</div>
@endif

<style>
.billing-lbl { cursor:default; opacity:.65; transition:opacity .2s; }
.billing-lbl.active-lbl { opacity:1; }
[data-billing="monthly"] .plan-annual  { display:none; }
[data-billing="annual"]  .plan-monthly { display:none; }
</style>

<script>
(function(){
    var toggle = document.getElementById('billing-cycle-toggle');
    var wrap   = document.querySelector('[data-billing-wrapper]');
    var lblM   = document.getElementById('lbl-monthly');
    var lblA   = document.getElementById('lbl-annual');
    if (!toggle || !wrap) return;

    function apply(annual) {
        wrap.setAttribute('data-billing', annual ? 'annual' : 'monthly');
        toggle.checked = annual;
        lblM.classList.toggle('active-lbl', !annual);
        lblA.classList.toggle('active-lbl',  annual);
    }

    apply(false);

    toggle.addEventListener('change', function() { apply(this.checked); });
    if (lblA) lblA.addEventListener('click', function() { apply(true);  });
    if (lblM) lblM.addEventListener('click', function() { apply(false); });
})();
</script>
