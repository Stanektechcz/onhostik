@extends('layouts.panel')

@php
    $breadcrumbTitle = __('panel.nav.admin_dashboard');
    use App\Domains\Shared\Support\MoneyFormatter;
    use Brick\Money\Money;
    $revenueFormatted   = MoneyFormatter::format(Money::ofMinor($revenueCzkMinor, 'CZK'));
    $revTrendUp         = $revTrendPct >= 0;
    $iconSprite         = asset('panel/svg/icon-sprite.svg');
    $ordersThisMonthK   = $ordersThisMonth > 1000 ? round($ordersThisMonth / 1000, 1) . 'K' : $ordersThisMonth;
@endphp

@section('title', __('panel.nav.admin_dashboard'))

@push('styles')
<link rel="stylesheet" href="{{ asset('panel/css/vendors/animate.css') }}">
@endpush

@section('content')
<div class="container default-dashboard">
  <div class="grid grid-cols-12 card-gap widget-grid">

    {{-- ╔══════════════════════════════════════════════════════════════════╗
         ║  1. Profile / Greeting card                     col-span-4     ║
         ╚══════════════════════════════════════════════════════════════════╝ --}}
    <div class="col-span-4 xxl:col-span-6 sm:col-span-12 box-col-6">
      <div class="card profile-box">
        <div class="card-body">
          <div class="flex media-wrapper justify-between">
            <div class="grow">
              <div class="greeting-user">
                <h2 class="font-semibold line-clamp-[1]">Dobrý den, {{ auth()->user()?->name ?? 'Admin' }}!</h2>
                <p class="line-clamp-[2]">Přehled systému OnHost — {{ now()->format('d. m. Y') }}</p>
                <div class="whatsnew-btn">
                  <a class="btn btn-outline-white" href="{{ route('admin.system.index') }}">System check</a>
                </div>
              </div>
            </div>
            <div>
              <div class="clockbox">
                <svg id="clock" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 600">
                  <g id="face">
                    <circle class="circle" cx="300" cy="300" r="253.9"></circle>
                    <path class="hour-marks" d="M300.5 94V61M506 300.5h32M300.5 506v33M94 300.5H60M411.3 107.8l7.9-13.8M493 190.2l13-7.4M492.1 411.4l16.5 9.5M411 492.3l8.9 15.3M189 492.3l-9.2 15.9M107.7 411L93 419.5M107.5 189.3l-17.1-9.9M188.1 108.2l-9-15.6"></path>
                    <circle class="mid-circle" cx="300" cy="300" r="16.2"></circle>
                  </g>
                  <g id="hour">
                    <path class="hour-hand" d="M300.5 298V142"></path>
                    <circle class="sizing-box" cx="300" cy="300" r="253.9"></circle>
                  </g>
                  <g id="minute">
                    <path class="minute-hand" d="M300.5 298V67"></path>
                    <circle class="sizing-box" cx="300" cy="300" r="253.9"></circle>
                  </g>
                  <g id="second">
                    <path class="second-hand" d="M300.5 350V55"></path>
                    <circle class="sizing-box" cx="300" cy="300" r="253.9"></circle>
                  </g>
                </svg>
              </div>
              <div class="badge f-10 p-0" id="txt"></div>
            </div>
          </div>
          <div class="cartoon">
            <img class="max-w-full h-auto" src="{{ asset('panel/images/dashboard/cartoon.svg') }}" alt="admin illustration"/>
          </div>
        </div>
      </div>
    </div>

    {{-- ╔══════════════════════════════════════════════════════════════════╗
         ║  2. 4 KPI mini-cards                            col-span-5     ║
         ╚══════════════════════════════════════════════════════════════════╝ --}}
    <div class="col-span-5 xxl:col-span-6 xl:col-span-12 box-col-6 ord-md-2 ord-custom-2">
      <div class="grid grid-cols-12 card-gap">

        {{-- Revenue --}}
        <div class="col-span-6 sm:col-span-12">
          <div class="card widget-1">
            <div class="card-body">
              <div class="widget-content">
                <div class="widget-round secondary">
                  <div class="bg-round">
                    <svg><use href="{{ $iconSprite }}#c-revenue"></use></svg>
                    <svg class="half-circle svg-fill"><use href="{{ $iconSprite }}#halfcircle"></use></svg>
                  </div>
                </div>
                <div>
                  <h4><span class="counter" data-target="{{ (int)($revenueCzkMinor / 100) }}">0</span> Kč</h4>
                  <span class="f-light">Tržby CZK</span>
                </div>
              </div>
              <div class="font-{{ $revTrendUp ? 'success' : 'danger' }} font-medium [@media(max-width:1680px)]:!hidden">
                <i class="bookmark-search me-1" data-feather="{{ $revTrendUp ? 'trending-up' : 'trending-down' }}"></i>
                <span class="txt-{{ $revTrendUp ? 'success' : 'danger' }}">{{ $revTrendPct > 0 ? '+' : '' }}{{ $revTrendPct }}%</span>
              </div>
            </div>
          </div>
        </div>

        {{-- Customers --}}
        <div class="col-span-6 sm:col-span-12">
          <div class="card widget-1">
            <div class="card-body">
              <div class="widget-content">
                <div class="widget-round success">
                  <div class="bg-round">
                    <svg><use href="{{ $iconSprite }}#c-customer"></use></svg>
                    <svg class="half-circle svg-fill"><use href="{{ $iconSprite }}#halfcircle"></use></svg>
                  </div>
                </div>
                <div>
                  <h4><span class="counter" data-target="{{ $customerCount }}">0</span></h4>
                  <span class="f-light">Zákazníci</span>
                </div>
              </div>
              <div class="font-success font-medium [@media(max-width:1680px)]:!hidden">
                <i class="bookmark-search me-1" data-feather="trending-up"></i>
                <span class="txt-success">+{{ $customerLastMonth }} tento měsíc</span>
              </div>
            </div>
          </div>
        </div>

        {{-- Active Services --}}
        <div class="col-span-6 sm:col-span-12">
          <div class="card widget-1">
            <div class="card-body">
              <div class="widget-content">
                <div class="widget-round warning">
                  <div class="bg-round">
                    <svg><use href="{{ $iconSprite }}#c-profit"></use></svg>
                    <svg class="half-circle svg-fill"><use href="{{ $iconSprite }}#halfcircle"></use></svg>
                  </div>
                </div>
                <div>
                  <h4><span class="counter" data-target="{{ $activeServices }}">0</span></h4>
                  <span class="f-light">Aktivní služby</span>
                </div>
              </div>
              <div class="font-{{ $suspendedServices > 0 ? 'danger' : 'success' }} font-medium [@media(max-width:1680px)]:!hidden">
                <i class="bookmark-search me-1" data-feather="{{ $suspendedServices > 0 ? 'pause-circle' : 'check-circle' }}"></i>
                <span class="txt-{{ $suspendedServices > 0 ? 'danger' : 'success' }}">{{ $suspendedServices }} pozastaveno</span>
              </div>
            </div>
          </div>
        </div>

        {{-- Unpaid Invoices --}}
        <div class="col-span-6 sm:col-span-12">
          <div class="card widget-1">
            <div class="card-body">
              <div class="widget-content">
                <div class="widget-round primary">
                  <div class="bg-round">
                    <svg class="fill-primary"><use href="{{ $iconSprite }}#c-invoice"></use></svg>
                    <svg class="half-circle svg-fill"><use href="{{ $iconSprite }}#halfcircle"></use></svg>
                  </div>
                </div>
                <div>
                  <h4 class="counter" data-target="{{ $unpaidInvoices }}">0</h4>
                  <span class="f-light">Faktury čekající</span>
                </div>
              </div>
              <div class="font-{{ $overdueInvoices > 0 ? 'danger' : 'success' }} font-medium [@media(max-width:1680px)]:!hidden">
                <i class="bookmark-search me-1" data-feather="{{ $overdueInvoices > 0 ? 'alert-circle' : 'check' }}"></i>
                <span class="txt-{{ $overdueInvoices > 0 ? 'danger' : 'success' }}">{{ $overdueInvoices }} po splatnosti</span>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    {{-- ╔══════════════════════════════════════════════════════════════════╗
         ║  3. Visitor / Orders chart                      col-span-3     ║
         ╚══════════════════════════════════════════════════════════════════╝ --}}
    <div class="col-span-3 xxl:col-span-4 xl:col-span-6 sm:col-span-12 box-col-4 ord-md-1 box-ord-1 ord-xl-5 box-ord-5 ord-custom-1">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <div class="header-top">
            <h5>Objednávky</h5>
            <div class="card-header-right-icon">
              <div class="dropdown icon-dropdown">
                <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="icon-more-alt"></i></button>
                <div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="{{ route('admin.orders.index') }}">Všechny</a></div>
              </div>
            </div>
          </div>
        </div>
        <div class="card-body visitor-chart pt-0">
          <div class="common-flex">
            <h6><span class="counter" data-target="{{ $ordersThisMonth }}">0</span></h6>
            <div class="flex">
              <p class="[@media(max-width:1805px)]:!hidden">(<span class="txt-success font-medium me-1">tento měsíc</span>)</p>
            </div>
          </div>
          <div id="visitor_chart"></div>
        </div>
      </div>
    </div>

    {{-- ╔══════════════════════════════════════════════════════════════════╗
         ║  4. Top Customers table                         col-span-4     ║
         ╚══════════════════════════════════════════════════════════════════╝ --}}
    <div class="col-span-4 xxl:col-span-6 sm:col-span-12 ord-xl-1 ord-md-3 box-ord-1 box-col-6 ord-custom-3">
      <div class="card">
        <div class="card-header card-no-border">
          <div class="header-top">
            <h5>Top zákazníci</h5>
            <div class="card-header-right-icon">
              <div class="dropdown icon-dropdown">
                <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="icon-more-alt"></i></button>
                <div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="{{ route('admin.customers.index') }}">Všichni zákazníci</a></div>
              </div>
            </div>
          </div>
        </div>
        <div class="card-body main-customer-table px-0 pt-0">
          <div class="recent-table overflow-x-auto custom-scrollbar">
            <table class="table [@media(max-width:1700px)]:whitespace-nowrap" id="top-customer">
              <thead>
                <tr>
                  <th></th>
                  <th>Zákazník</th>
                  <th>Objednávky</th>
                  <th class="[@media(min-width:1399px)_and_(max-width:1700)]:!hidden [@media(min-width:1200px)_and_(max-width:1230px)]:!hidden">Zaplaceno</th>
                </tr>
              </thead>
              <tbody>
                @foreach($topCustomers as $i => $cust)
                  @php
                    $avatarNum = ($i % 5) + 1;
                    $name = $cust->company_name ?? $cust->email;
                    $paid = $cust->total_paid ?? 0;
                  @endphp
                  <tr>
                    <td></td>
                    <td>
                      <div class="flex"><img class="max-w-full h-auto img-40 rounded-full me-2"
                           src="{{ asset('panel/images/dashboard/user/'.$avatarNum.'.jpg') }}" alt="user"/>
                        <div class="img-content-box">
                          <a class="font-medium" href="{{ route('admin.customers.show', $cust) }}">{{ $name }}</a>
                          <p class="mb-0 f-light">{{ $cust->email }}</p>
                        </div>
                      </div>
                    </td>
                    <td>{{ $cust->orders_count }} objednávek</td>
                    <td class="font-medium txt-success [@media(min-width:1399px)_and_(max-width:1700)]:!hidden [@media(min-width:1200px)_and_(max-width:1230px)]:!hidden">
                      {{ number_format($paid / 100, 0, ',', ' ') }} Kč
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    {{-- ╔══════════════════════════════════════════════════════════════════╗
         ║  5. Sales Statistical Overview (Přehled příjmů) col-span-5    ║
         ╚══════════════════════════════════════════════════════════════════╝ --}}
    <div class="col-span-5 xxl:col-span-6 lg:col-span-12 box-col-6 ord-xl-2 ord-md-5 box-ord-2 ord-custom-4">
      <div class="card">
        <div class="card-header card-no-border">
          <div class="header-top">
            <h5>Přehled příjmů</h5>
            <div class="card-header-right-icon">
              <div class="dropdown custom-dropdown">
                <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Měsíce</button>
                <ul class="dropdown-menu">
                  <li><a class="dropdown-item" href="{{ route('admin.payments.index') }}">Všechny platby</a></li>
                </ul>
              </div>
            </div>
          </div>
        </div>
        <div class="card-body pt-0">
          <div class="grid grid-cols-12 m-0 overall-card">
            <div class="col-span-12 p-0">
              <div class="chart-right">
                <div class="grid grid-cols-12">
                  <div class="col-span-12">
                    <div class="statistical-card">
                      <ul class="flex !mb-[15px]">
                        <li>
                          <h5 class="counter" data-target="{{ $saleReportOrders->sum() }}">0</h5>
                          <span class="f-light">Objednávky celkem</span>
                        </li>
                        <li>
                          <h5><span class="counter" data-target="{{ (int)($revenueCzkMinor / 100) }}">0</span> Kč</h5>
                          <span class="f-light">Tržby celkem</span>
                        </li>
                      </ul>
                      <div class="current-sale-container">
                        <div id="chart-currently"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ╔══════════════════════════════════════════════════════════════════╗
         ║  6. Monthly Target (Faktury tento měsíc)        col-span-3    ║
         ╚══════════════════════════════════════════════════════════════════╝ --}}
    <div class="col-span-3 xl:col-span-6 md:col-span-12 ord-xl-3 ord-md-6 box-ord-3 ord-custom-5">
      <div class="card monthly-header">
        <div class="card-header card-no-border">
          <div class="header-top">
            <h5>Faktury tento měsíc</h5>
            <div class="card-header-right-icon">
              <div class="dropdown icon-dropdown">
                <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="icon-more-alt"></i></button>
                <div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="{{ route('admin.invoices.index') }}">Všechny faktury</a></div>
              </div>
            </div>
          </div>
        </div>
        <div class="card-body">
          <div class="monthly-target">
            <div class="relative" id="monthly_target"></div>
          </div>
          <div class="target-content">
            <p class="[@media(max-width:1590px)]:!my-[28px] [@media(min-width:1400px)]:my-[0px]">
              {{ $monthlyPaid }} zaplacených z {{ $monthlyTotal }} faktur tento měsíc.
              @if($overdueInvoices > 0) {{ $overdueInvoices }} je po splatnosti. @endif
            </p>
            <div class="common-box">
              <ul class="common-flex [@media(max-width:1500px)]:flex-nowrap">
                <li>
                  <h6>Zaplaceno</h6>
                  <span class="common-space badge badge-light-success"><i class="me-1" data-feather="check"></i>{{ $monthlyPaid }}</span>
                </li>
                <li>
                  <h6>Celkem</h6>
                  <span class="common-space badge badge-light-primary"><i class="me-1" data-feather="file-text"></i>{{ $monthlyTotal }}</span>
                </li>
                <li class="[@media(max-width:1497px)]:!hidden">
                  <h6>Po splat.</h6>
                  <span class="common-space badge badge-light-{{ $overdueInvoices > 0 ? 'danger' : 'success' }}"><i class="me-1" data-feather="{{ $overdueInvoices > 0 ? 'alert-circle' : 'check' }}"></i>{{ $overdueInvoices }}</span>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ╔══════════════════════════════════════════════════════════════════╗
         ║  7. Activity Log (Audit log)                    col-span-5     ║
         ╚══════════════════════════════════════════════════════════════════╝ --}}
    <div class="col-span-5 xl:col-span-6 md:col-span-12 ord-xl-4 ord-md-7 box-ord-4 ord-custom-6">
      <div class="card activity-log notification main-timeline">
        <div class="card-header card-no-border">
          <div class="header-top">
            <h5>Audit log</h5>
            <div class="card-header-right-icon">
              <div class="dropdown icon-dropdown">
                <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="icon-more-alt"></i></button>
                <div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="{{ route('admin.logs.audit') }}">Celý log</a></div>
              </div>
            </div>
          </div>
        </div>
        <div class="card-body pt-0 dark-timeline basic-timeline">
          <ul>
            @forelse($recentAudit as $activity)
              @php
                $dotClass = match(true) {
                  str_contains($activity->description, 'fail') || str_contains($activity->description, 'error') || str_contains($activity->description, 'cancel') => 'danger',
                  str_contains($activity->description, 'paid') || str_contains($activity->description, 'created') || str_contains($activity->description, 'renewed') => 'success',
                  str_contains($activity->description, 'suspend') => 'warning',
                  default => 'primary',
                };
              @endphp
              <li class="flex">
                <div class="timeline-dot-{{ $dotClass }}"></div>
                <div class="w-full !pl-[16px] rtl:!pr-[16px] rtl:!pl-[0px]">
                  <div class="flex justify-between items-center">
                    <p class="mb-0 f-16 font-medium [@media(max-width:1589px)]:line-clamp-[1]">
                      <span class="badge badge-light-primary me-1 f-10">{{ $activity->log_name }}</span>
                      {{ $activity->description }}
                    </p>
                    <span class="c-light whitespace-nowrap [@media(max-width:1400px)]:hidden">{{ $activity->created_at?->format('H:i') }}</span>
                  </div>
                  <p class="mb-0 f-light pb-1 [@media(max-width:1589px)]:line-clamp-[1]">{{ $activity->causer?->name ?? 'system' }}</p>
                  <p class="date-content p-0">{{ $activity->created_at?->format('d. m. Y') }}</p>
                </div>
              </li>
            @empty
              <li class="text-center f-light py-4">Žádné záznamy</li>
            @endforelse
          </ul>
        </div>
      </div>
    </div>

    {{-- ╔══════════════════════════════════════════════════════════════════╗
         ║  8. Recent Orders table                         col-span-7     ║
         ╚══════════════════════════════════════════════════════════════════╝ --}}
    <div class="col-span-7 xxl:col-span-8 lg:col-span-12 ord-xl-6 ord-md-8 box-ord-6 box-col-8e ord-custom-7">
      <div class="card">
        <div class="card-header card-no-border">
          <div class="header-top">
            <h5>Poslední objednávky</h5>
            <div class="card-header-right-icon">
              <div class="dropdown icon-dropdown">
                <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="icon-more-alt"></i></button>
                <div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="{{ route('admin.orders.index') }}">Všechny objednávky</a></div>
              </div>
            </div>
          </div>
        </div>
        <div class="card-body px-0 pt-0 common-option">
          <div class="recent-table overflow-x-auto currency-table recent-order-table custom-scrollbar">
            <table class="table" id="main-recent-order">
              <thead>
                <tr class="whitespace-nowrap">
                  <th></th>
                  <th>Zákazník</th>
                  <th>Celkem</th>
                  <th>Datum</th>
                  <th class="[@media(max-width:1696px)]:hidden [@media(max-width:1199px)]:!table-cell">Stav</th>
                </tr>
              </thead>
              <tbody>
                @forelse($recentOrders as $order)
                  @php
                    $statusColor = match($order->status->value) {
                      'active'     => 'success',
                      'pending'    => 'warning',
                      'processing' => 'info',
                      'cancelled','fraud' => 'danger',
                      default      => 'secondary',
                    };
                    $statusLabel = match($order->status->value) {
                      'active'     => 'Aktivní',
                      'pending'    => 'Čeká',
                      'processing' => 'Zpracovává',
                      'cancelled'  => 'Zrušeno',
                      'fraud'      => 'Podvod',
                      default      => $order->status->value,
                    };
                    $dispName = $order->customer?->company_name ?? $order->customer?->email ?? '—';
                  @endphp
                  <tr>
                    <td>
                      <div class="flex items-center gap-2">
                        <div class="currency-icon warning">
                          <i data-feather="package" style="width:18px;height:18px;"></i>
                        </div>
                        <div>
                          <a class="f-14 mb-0 font-medium c-light" href="{{ route('admin.orders.show', $order) }}">#{{ $order->id }}</a>
                          <p class="c-o-light">{{ $order->created_at->format('d.m.Y') }}</p>
                        </div>
                      </div>
                    </td>
                    <td>{{ $dispName }}</td>
                    <td>{{ \App\Domains\Shared\Support\MoneyFormatter::format($order->total) }}</td>
                    <td>{{ $order->created_at->format('d. m. Y') }}</td>
                    <td class="[@media(max-width:1696px)]:hidden [@media(max-width:1199px)]:!table-cell">
                      <button class="btn button-light-{{ $statusColor }} txt-{{ $statusColor }} font-medium">{{ $statusLabel }}</button>
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="5" class="text-center f-light py-4">Žádné objednávky</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    {{-- ╔══════════════════════════════════════════════════════════════════╗
         ║  9. Quick actions / Doctor CTA card             col-span-3     ║
         ╚══════════════════════════════════════════════════════════════════╝ --}}
    <div class="col-span-3 xxl:col-span-4 lg:col-span-6 sm:col-span-12 box-col-4 ord-xl-7 ord-md-4 box-ord-7 ord-custom-8">
      <div class="card buy-card text-center">
        <img class="max-w-full" src="{{ asset('panel/images/dashboard/purchase1.png') }}" alt="system check"/>
        <div class="card-body [@media(max-width:1700px)]:!mx-[0]">
          <h6 class="mb-3 [@media(max-width:1399px)]:w-[56%] mx-[auto] [@media(max-width:1299px)]:!w-[88%]">
            Spusťte <a class="txt-info" href="{{ route('admin.system.index') }}">System Doctor</a> pro kontrolu před ostrým provozem
          </h6>
          @php $failCount = $failedTasks + ($aiApprovals > 0 ? 1 : 0); @endphp
          <a class="purchase-btn btn btn-{{ $failCount > 0 ? 'danger' : 'primary' }} btn-hover-effect font-medium text-white"
             href="{{ route('admin.system.index') }}">
            {{ $failCount > 0 ? $failCount . ' problémů' : 'Vše OK' }}
          </a>
        </div>
      </div>
    </div>

    {{-- ╔══════════════════════════════════════════════════════════════════╗
         ║  10. Sales Report chart (Přehled plateb)        col-span-5     ║
         ╚══════════════════════════════════════════════════════════════════╝ --}}
    <div class="col-span-5 xxl:col-span-6 lg:col-span-12 ord-xl-9 ord-md-9 box-ord-7 box-col-6 ord-custom-9">
      <div class="card sales-report">
        <div class="card-header card-no-border">
          <div class="header-top">
            <h5>Přehled plateb</h5>
            <div class="card-header-right-icon">
              <div class="dropdown icon-dropdown">
                <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="icon-more-alt"></i></button>
                <div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="{{ route('admin.payments.index') }}">Všechny platby</a></div>
              </div>
            </div>
          </div>
        </div>
        <div class="card-body pt-0">
          <ul class="balance-data">
            <li><span class="circle bg-primary"></span><span class="c-light ms-1">Objednávky</span></li>
            <li><span class="circle bg-warning"></span><span class="c-light ms-1">Příjmy (Kč)</span></li>
            <li><span class="circle bg-secondary"></span><span class="c-light ms-1">Selhané platby</span></li>
          </ul>
          <div id="sale_report"></div>
        </div>
      </div>
    </div>

    {{-- ╔══════════════════════════════════════════════════════════════════╗
         ║  11. Nadcházející obnovy (Appointments)         col-span-4     ║
         ╚══════════════════════════════════════════════════════════════════╝ --}}
    <div class="col-span-4 xxl:col-span-6 lg:col-span-12 ord-xl-10 ord-md-10 box-ord-7 box-col-6 ord-custom-10">
      <div class="card">
        <div class="card-header card-no-border">
          <div class="header-top">
            <h5>Nadcházející obnovy</h5>
            <div class="card-header-right-icon">
              <div class="dropdown icon-dropdown">
                <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="icon-more-alt"></i></button>
                <div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="{{ route('admin.services.index') }}">Všechny služby</a></div>
              </div>
            </div>
          </div>
        </div>
        <div class="card-body pt-0">
          <ul class="appointments-wrapper">
            @forelse($upcomingRenewals as $svc)
              @php
                $daysLeft = (int) now()->diffInDays($svc->next_due_date, false);
                $color    = $daysLeft <= 3 ? 'danger' : ($daysLeft <= 7 ? 'warning' : 'success');
                $custName = $svc->customer?->company_name ?? $svc->customer?->email ?? '—';
              @endphp
              <li class="flex items-start">
                <span>{{ $svc->next_due_date->format('d.m') }}</span>
                <div class="bg-lighter-{{ $color }}"></div>
                <div class="main-box">
                  <div class="mb-2">
                    <span>{{ $svc->label ?? 'Služba #'.$svc->id }}</span>
                    <span class="c-o-light">{{ $custName }}</span>
                    <span class="txt-{{ $color }}">za {{ $daysLeft }} dní</span>
                  </div>
                  <div>
                    <button class="btn btn-{{ $color }} text-white btn-sm">Obnova</button>
                  </div>
                </div>
              </li>
            @empty
              <li class="flex items-start">
                <span></span>
                <div class="bg-lighter-success"></div>
                <div class="main-box">
                  <div><span class="txt-success">Žádné obnovy v příštích 14 dnech</span></div>
                </div>
              </li>
            @endforelse
          </ul>
        </div>
      </div>
    </div>

  </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('panel/js/clock.js') }}"></script>
<script src="{{ asset('panel/js/chart/apex-chart/apex-chart.js') }}"></script>
<script src="{{ asset('panel/js/counter/counter-custom.js') }}"></script>
<script>
(function () {
  // ── 3. visitor_chart — orders per month ──────────────────────────────
  new ApexCharts(document.querySelector("#visitor_chart"), {
    series: [{ name: "Objednávky", data: {!! $orderCountsData->toJson() !!} }],
    chart: { height: 160, type: "line", stacked: true, offsetY: -18, toolbar: { show: false } },
    colors: ["#7366FF"],
    stroke: { width: 3, curve: "smooth" },
    xaxis: {
      categories: {!! $chartLabels->toJson() !!},
      labels: { style: { fontFamily: "Rubik, sans-serif", fontWeight: 500, colors: "#8D8D8D" } },
      axisTicks: { show: false }, axisBorder: { show: false }
    },
    grid: { show: true, borderColor: "rgba(var(--chart-dashed-border),1)", strokeDashArray: 3, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
    fill: { type: "gradient", gradient: { shade: "dark", gradientToColors: ["#7366FF"], shadeIntensity: 1, type: "horizontal", opacityFrom: 1, opacityTo: 1, colorStops: [{ offset: 0, color: "#48A3D7", opacity: 1 }, { offset: 100, color: "rgba(var(--theme-default),1)", opacity: 1 }] } },
    yaxis: { labels: { show: false } },
    responsive: [{ breakpoint: 1400, options: { chart: { height: 310, offsetY: 0 } } }, { breakpoint: 576, options: { chart: { height: 150, offsetY: -20 } } }]
  }).render();

  // ── 5. chart-currently — revenue + orders bar ──────────────────────
  new ApexCharts(document.querySelector("#chart-currently"), {
    series: [
      { name: "Tržby (Kč)", data: {!! $saleReportRevenue->toJson() !!} },
      { name: "Objednávky", data: {!! $saleReportOrders->toJson() !!} }
    ],
    chart: { type: "bar", height: 312, stacked: true, toolbar: { show: false }, dropShadow: { enabled: true, top: 8, left: 0, blur: 8, color: "#7064F5", opacity: 0.1 } },
    plotOptions: { bar: { horizontal: false, columnWidth: "20%", borderRadius: 0 } },
    grid: { borderColor: "rgba(var(--chart-border),1)", yaxis: { lines: { show: true } } },
    dataLabels: { enabled: false },
    stroke: { width: 2, dashArray: 0, lineCap: "butt", colors: "#fff" },
    fill: { opacity: 1 },
    legend: { show: false },
    colors: ["rgba(var(--theme-default),1)", "#AAAFCB"],
    xaxis: {
      categories: {!! $chartLabels->toJson() !!},
      labels: { style: { fontFamily: "Rubik, sans-serif" } },
      axisBorder: { show: false }, axisTicks: { show: false }
    },
    yaxis: { labels: { formatter: function(v) { return v + "k"; }, style: { fontFamily: "Rubik, sans-serif", fontWeight: 400, colors: "#52526C", fontSize: 12 } } },
    responsive: [{ breakpoint: 767, options: { plotOptions: { bar: { columnWidth: "15px" } }, yaxis: { labels: { show: false } } } }]
  }).render();

  // ── 6. monthly_target — % paid invoices this month ────────────────
  new ApexCharts(document.querySelector("#monthly_target"), {
    series: [{{ $monthlyTargetPct }}],
    chart: { type: "radialBar", height: 320, offsetY: -20, sparkline: { enabled: true } },
    plotOptions: {
      radialBar: {
        hollow: { size: "65%" },
        startAngle: -90, endAngle: 90,
        track: { background: "#d7e2e9", strokeWidth: "97%", margin: 5, dropShadow: { enabled: true, top: 2, left: 0, color: "#999", opacity: 1, blur: 2 } },
        dataLabels: {
          name: { show: true, offsetY: -10 },
          value: { show: true, offsetY: -50, fontSize: "18px", fontWeight: "600", color: "#2F2F3B" },
          total: { show: true, label: "zaplaceno", color: CubaAdminConfig.primary, fontSize: "14px", fontFamily: "Rubik, sans-serif", fontWeight: 400, formatter: function() { return "{{ $monthlyTargetPct }}%"; } }
        }
      }
    },
    grid: { padding: { top: -10 } },
    fill: { type: "gradient", gradient: { shade: "dark", shadeIntensity: 0.4, inverseColors: false, opacityFrom: 1, opacityTo: 1, stops: [100], colorStops: [{ offset: 0, color: "rgba(var(--theme-default),1)", opacity: 1 }] } },
    labels: ["Faktury"],
    responsive: [{ breakpoint: 1591, options: { chart: { height: 270 } } }, { breakpoint: 768, options: { chart: { height: 250 } } }]
  }).render();

  // ── 10. sale_report — orders + revenue + refunds ──────────────────
  new ApexCharts(document.querySelector("#sale_report"), {
    series: [
      { name: "Selhané platby", type: "column", data: {!! $saleReportRefunds->toJson() !!} },
      { name: "Tržby (Kč)", type: "line", data: {!! $saleReportRevenue->toJson() !!} },
      { name: "Objednávky", type: "line", data: {!! $saleReportOrders->toJson() !!} }
    ],
    chart: { height: 295, type: "line", stacked: false, toolbar: { show: false }, dropShadow: { enabled: true, enabledOnSeries: [2], top: 10, left: 0, blur: 4, color: "#7366FF", opacity: 0.2 } },
    stroke: { width: [0, 2, 3], curve: "smooth", dashArray: [0, 8, 0] },
    plotOptions: { bar: { columnWidth: "30%" } },
    colors: ["rgba(var(--chart-progress-light),1)", "#ffb829", "rgba(var(--theme-default),1)"],
    fill: { type: "solid" },
    grid: { borderColor: "rgba(var(--chart-border),1)", yaxis: { lines: { show: true } } },
    legend: { show: false },
    markers: { size: 0 },
    xaxis: { categories: {!! $chartLabels->toJson() !!}, labels: { style: { fontFamily: "Rubik, sans-serif", colors: ["#52526c"] } }, axisBorder: { show: false }, axisTicks: { show: false } },
    yaxis: { min: 0, title: { text: undefined } },
    responsive: [{ breakpoint: 576, options: { chart: { height: 250 } } }]
  }).render();
})();
</script>
@endpush
