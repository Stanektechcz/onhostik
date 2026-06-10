@php($current = app()->getLocale())
<a href="{{ route('locale.switch', $current === 'cs' ? 'en' : 'cs') }}" class="iconews" title="Language">
    {{ $current === 'cs' ? 'EN' : 'CZ' }}
</a>
