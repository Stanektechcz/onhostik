@props(['title', 'subtitle' => null])
<section class="top-header sec-bg6 pb-150 bg-colorstyle">
    <div class="container">
        <div class="row">
            <div class="col-md-8">
                <div class="wrapper">
                    <h1 class="heading mergecolor pb-2" data-aos="fade-up" data-aos-duration="800">{{ $title }}</h1>
                    @if($subtitle)
                        <h2 class="subheading fw-normal lh-42 text-muted mb-5" data-aos="fade-up" data-aos-duration="1200">{{ $subtitle }}</h2>
                    @endif
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</section>
