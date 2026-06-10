@props(['title' => null, 'subtitle' => null])
<div {{ $attributes->merge(['class' => 'card']) }}>
    @if($title)
        <div class="card-header card-no-border pb-0">
            <h4>{{ $title }}</h4>
            @if($subtitle)<span class="f-light f-14 f-w-500">{{ $subtitle }}</span>@endif
        </div>
    @endif
    <div class="card-body">
        {{ $slot }}
    </div>
</div>
