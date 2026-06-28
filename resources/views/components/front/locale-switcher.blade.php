@php($current = app()->getLocale())
<div class="d-flex gap-1 align-items-center">
    @foreach(['cs' => '🇨🇿', 'en' => '🇬🇧'] as $locale => $flag)
    <a href="{{ route('locale.switch', $locale) }}"
       class="{{ $current === $locale ? 'btn btn-primary btn-xs text-white' : 'btn btn-outline-secondary btn-xs' }}"
       style="font-size:12px;padding:2px 8px;"
       title="{{ $locale === 'cs' ? 'Čeština' : 'English' }}">
        {{ $flag }} {{ strtoupper($locale) }}
    </a>
    @endforeach
</div>
