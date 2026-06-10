{{-- Domain availability search box (posts to rate-limited endpoint) --}}
<div class="domain-search-block" data-aos="fade-up">
    <form action="{{ route('front.domains.check') }}" method="POST" class="domain-search-form">
        @csrf
        <div class="general-input d-flex">
            <input class="fill-input flex-grow-1" type="text" name="domain"
                   placeholder="{{ __('front.domains.search_placeholder') }}"
                   value="{{ old('domain') }}" required
                   pattern="[a-zA-Z0-9][a-zA-Z0-9\-\.]{1,253}">
            <button type="submit" class="btn btn-default-yellow-fill initial-transform">
                {{ __('front.domains.search_button') }} <i class="fas fa-search ps-1"></i>
            </button>
        </div>
        @error('domain')
            <div class="text-danger mt-2">{{ $message }}</div>
        @enderror
    </form>
</div>
