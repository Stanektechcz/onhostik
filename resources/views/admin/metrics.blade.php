@extends('layouts.panel')

@php
    $breadcrumbTitle = __('admin.metrics.title');
    $breadcrumbItems = [__('admin.metrics.title') => ''];
@endphp

@section('title', __('admin.metrics.title'))

@push('head_scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endpush

@section('content')
<div class="container-fluid">

    {{-- KPI Cards --}}
    <div class="grid grid-cols-12 card-gap mb-3">
        {{-- MRR --}}
        <div class="col-span-3 xl:col-span-6 sm:col-span-12">
            <div class="card card-no-border">
                <div class="card-body">
                    <div class="flex items-center gap-3">
                        <div class="bg-light-primary rounded p-3">
                            <i data-feather="trending-up" class="font-primary" style="width:24px;height:24px"></i>
                        </div>
                        <div>
                            <h4 class="f-w-700 mb-0">{{ number_format($mrrCzk, 0, ',', ' ') }} Kč</h4>
                            <p class="f-light f-12 mb-0">
                                MRR
                                @if($mrrGrowthPct != 0)
                                    <span class="ms-1 {{ $mrrGrowthPct > 0 ? 'text-success' : 'text-danger' }} f-w-500">
                                        {{ $mrrGrowthPct > 0 ? '+' : '' }}{{ $mrrGrowthPct }} %
                                    </span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ARR --}}
        <div class="col-span-3 xl:col-span-6 sm:col-span-12">
            <div class="card card-no-border">
                <div class="card-body">
                    <div class="flex items-center gap-3">
                        <div class="bg-light-success rounded p-3">
                            <i data-feather="bar-chart-2" class="font-success" style="width:24px;height:24px"></i>
                        </div>
                        <div>
                            <h4 class="f-w-700 mb-0">{{ number_format($arrCzk, 0, ',', ' ') }} Kč</h4>
                            <p class="f-light f-12 mb-0">ARR (MRR × 12)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Churn --}}
        <div class="col-span-3 xl:col-span-6 sm:col-span-12">
            <div class="card card-no-border">
                <div class="card-body">
                    <div class="flex items-center gap-3">
                        <div class="bg-light-{{ $churnRatePct > 5 ? 'danger' : 'warning' }} rounded p-3">
                            <i data-feather="user-minus" class="font-{{ $churnRatePct > 5 ? 'danger' : 'warning' }}" style="width:24px;height:24px"></i>
                        </div>
                        <div>
                            <h4 class="f-w-700 mb-0">{{ $churnRatePct }} %</h4>
                            <p class="f-light f-12 mb-0">
                                Churn tento měsíc
                                <span class="text-muted">({{ $churnedThisMonth }} služeb)</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- LTV --}}
        <div class="col-span-3 xl:col-span-6 sm:col-span-12">
            <div class="card card-no-border">
                <div class="card-body">
                    <div class="flex items-center gap-3">
                        <div class="bg-light-info rounded p-3">
                            <i data-feather="users" class="font-info" style="width:24px;height:24px"></i>
                        </div>
                        <div>
                            <h4 class="f-w-700 mb-0">{{ number_format($ltvCzk, 0, ',', ' ') }} Kč</h4>
                            <p class="f-light f-12 mb-0">LTV / zákazník</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 card-gap mb-3">

        {{-- Revenue chart --}}
        <div class="col-span-8 xl:col-span-12">
            <x-panel.card title="Vývoj příjmů (12 měs.)">
                <canvas id="revenueChart" height="90"></canvas>
            </x-panel.card>
        </div>

        {{-- Stats sidebar --}}
        <div class="col-span-4 xl:col-span-12">
            <x-panel.card title="Zákazníci">
                <div class="flex justify-between mb-2">
                    <span class="f-light f-13">Celkem zákazníků</span>
                    <span class="f-w-600">{{ $totalCustomers }}</span>
                </div>
                <div class="flex justify-between mb-2">
                    <span class="f-light f-13">Noví (30 dní)</span>
                    <span class="f-w-600 text-success">+{{ $newCustomers30 }}</span>
                </div>
                <div class="flex justify-between mb-3">
                    <span class="f-light f-13">Aktivní služby</span>
                    <span class="f-w-600">{{ $activeServices }}</span>
                </div>
                <hr>
                <div class="flex justify-between mt-2">
                    <span class="f-light f-13">Nezaplacené faktury</span>
                    <span class="f-w-600 text-warning">{{ number_format($outstandingCzk, 0, ',', ' ') }} Kč</span>
                </div>
            </x-panel.card>

            <x-panel.card title="Růst zákazníků (12 měs.)">
                <canvas id="customerChart" height="130"></canvas>
            </x-panel.card>
        </div>

    </div>

    {{-- Second row: product breakdown + cohort + top products --}}
    <div class="grid grid-cols-12 card-gap">

        {{-- Revenue by product type --}}
        <div class="col-span-4 xl:col-span-12">
            <x-panel.card title="Tržby dle typu produktu">
                <canvas id="productTypeChart" height="200"></canvas>
            </x-panel.card>
        </div>

        {{-- New vs returning customers --}}
        <div class="col-span-4 xl:col-span-12">
            <x-panel.card title="Noví vs. vracející se zákazníci (tento měsíc)">
                @php
                    $totalBuyers = $newBuyersThisMonth + $returningBuyersThisMonth;
                    $newPct = $totalBuyers > 0 ? round($newBuyersThisMonth / $totalBuyers * 100) : 0;
                    $retPct = $totalBuyers > 0 ? 100 - $newPct : 0;
                @endphp
                <div class="flex justify-around text-center mb-3">
                    <div>
                        <div class="f-28 f-w-700 text-primary">{{ $newBuyersThisMonth }}</div>
                        <div class="f-light f-12">Noví</div>
                        <div class="badge badge-light-primary f-10">{{ $newPct }} %</div>
                    </div>
                    <div class="border-start mx-3"></div>
                    <div>
                        <div class="f-28 f-w-700 text-success">{{ $returningBuyersThisMonth }}</div>
                        <div class="f-light f-12">Vracející se</div>
                        <div class="badge badge-light-success f-10">{{ $retPct }} %</div>
                    </div>
                </div>
                @if($totalBuyers > 0)
                <div class="progress" style="height:8px;border-radius:4px;">
                    <div class="progress-bar bg-primary" style="width:{{ $newPct }}%;border-radius:4px 0 0 4px;"></div>
                    <div class="progress-bar bg-success" style="width:{{ $retPct }}%;border-radius:0 4px 4px 0;"></div>
                </div>
                <div class="flex justify-between mt-1">
                    <span class="f-light f-11">Noví</span>
                    <span class="f-light f-11">Vracející se</span>
                </div>
                @else
                <p class="f-light f-12 text-center mb-0">Žádné platby tento měsíc.</p>
                @endif
            </x-panel.card>
        </div>

        {{-- Top products by active services --}}
        <div class="col-span-4 xl:col-span-12">
            <x-panel.card title="Top produkty dle aktivních služeb">
                @if($topProducts->isEmpty())
                    <p class="f-light f-12 mb-0">Žádná data.</p>
                @else
                    @php $maxCount = $topProducts->max('count') ?: 1; @endphp
                    @foreach($topProducts as $product)
                    <div class="mb-3">
                        <div class="flex justify-between mb-1">
                            <span class="f-13 f-w-500">{{ $product['label'] }}</span>
                            <span class="f-13 f-w-600">{{ $product['count'] }}</span>
                        </div>
                        <div class="progress" style="height:6px;border-radius:3px;">
                            <div class="progress-bar bg-primary" style="width:{{ round($product['count'] / $maxCount * 100) }}%;border-radius:3px;"></div>
                        </div>
                    </div>
                    @endforeach
                @endif
            </x-panel.card>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
(function() {
    var labels = @json($chartLabels);
    var revenue = @json($chartRevenue);
    var customers = @json($customerGrowth);

    // Revenue chart
    new Chart(document.getElementById('revenueChart'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Příjmy (Kč)',
                data: revenue,
                backgroundColor: 'rgba(88, 72, 193, 0.15)',
                borderColor: '#5848C1',
                borderWidth: 2,
                borderRadius: 4,
                fill: true,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { drawBorder: false } },
                x: { grid: { display: false } }
            }
        }
    });

    // Customer growth chart
    new Chart(document.getElementById('customerChart'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Noví zákazníci',
                data: customers,
                borderColor: '#22BCA8',
                backgroundColor: 'rgba(34, 188, 168, 0.1)',
                borderWidth: 2,
                tension: 0.4,
                fill: true,
                pointRadius: 3,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { drawBorder: false } },
                x: { grid: { display: false } }
            }
        }
    });

    // Product type donut chart
    var productTypeData = @json($revenueByType);
    var ptLabels  = Object.keys(productTypeData);
    var ptValues  = Object.values(productTypeData);
    var ptColors  = ['#5848C1','#22BCA8','#F8B739','#e05263','#6c757d'];
    if (ptLabels.length > 0) {
        new Chart(document.getElementById('productTypeChart'), {
            type: 'doughnut',
            data: {
                labels: ptLabels,
                datasets: [{ data: ptValues, backgroundColor: ptColors.slice(0, ptLabels.length), borderWidth: 0 }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: { callbacks: { label: function(ctx) { return ctx.label + ': ' + ctx.parsed.toLocaleString('cs-CZ') + ' Kč'; } } }
                },
                cutout: '65%',
            }
        });
    }
})();
</script>
@endpush
