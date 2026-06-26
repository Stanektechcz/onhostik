@props(['title' => null, 'subtitle' => null, 'headerRight' => null])
<div {{ $attributes->merge(['class' => 'card']) }}>
    @if($title)
        <div class="card-header card-no-border pb-0">
            <div class="header-top">
                <h5>{{ $title }}</h5>
                @if($headerRight)<div class="card-header-right-icon">{{ $headerRight }}</div>@endif
            </div>
            @if($subtitle)<span class="f-light f-14 f-w-500">{{ $subtitle }}</span>@endif
        </div>
    @endif
    <div class="card-body">
        {{ $slot }}
    </div>
</div>
