@props(['label', 'value', 'icon' => 'activity', 'color' => 'primary', 'trend' => null])
<div class="card widget-1">
    <div class="card-body">
        <div class="widget-content">
            <div class="widget-round {{ $color }}">
                <div class="bg-round">
                    <i data-feather="{{ $icon }}"></i>
                </div>
            </div>
            <div>
                <h4>{{ $value }}</h4>
                <span class="f-light">{{ $label }}</span>
            </div>
        </div>
        @if($trend !== null)
            <div class="font-{{ $trend >= 0 ? 'success' : 'danger' }} f-w-500">
                <i class="icon-arrow-{{ $trend >= 0 ? 'up' : 'down' }} icon-rotate me-1"></i>
                <span>{{ $trend >= 0 ? '+' : '' }}{{ $trend }}%</span>
            </div>
        @endif
    </div>
</div>
