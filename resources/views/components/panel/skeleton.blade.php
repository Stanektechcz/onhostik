@props([
    'type' => 'text',   // text | card | table | list
    'rows' => 3,        // number of placeholder rows
])

{{--
    Loading placeholder (skeleton). Render while data is loading, e.g. before an
    async fetch resolves, then replace with the real content. Respects reduced
    motion. Styled in onhost.css (project layer).
--}}
<div {{ $attributes->merge(['class' => 'skeleton-wrap', 'aria-hidden' => 'true']) }}>
    @switch($type)
        @case('card')
            <div class="skeleton skeleton-title"></div>
            <div class="skeleton skeleton-block mb-3"></div>
            @for($i = 0; $i < (int) $rows; $i++)
                <div class="skeleton skeleton-line {{ $i === (int) $rows - 1 ? 'w-60' : '' }}"></div>
            @endfor
            @break

        @case('table')
            @for($i = 0; $i < (int) $rows; $i++)
                <div class="flex items-center gap-3 mb-3">
                    <div class="skeleton skeleton-line" style="flex:2;"></div>
                    <div class="skeleton skeleton-line" style="flex:1;"></div>
                    <div class="skeleton skeleton-line" style="flex:1;"></div>
                </div>
            @endfor
            @break

        @case('list')
            @for($i = 0; $i < (int) $rows; $i++)
                <div class="flex items-center gap-3 mb-3">
                    <div class="skeleton skeleton-avatar"></div>
                    <div style="flex:1;">
                        <div class="skeleton skeleton-line w-40"></div>
                        <div class="skeleton skeleton-line w-60"></div>
                    </div>
                </div>
            @endfor
            @break

        @default
            @for($i = 0; $i < (int) $rows; $i++)
                <div class="skeleton skeleton-line {{ $i === (int) $rows - 1 ? 'w-60' : '' }}"></div>
            @endfor
    @endswitch
</div>
