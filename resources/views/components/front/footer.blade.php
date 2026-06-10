{{-- Antler footer, converted from footer.html --}}
<footer class="footer">
    <img class="logo-bg logo-footer" src="{{ asset('front/img/symbol.svg') }}" alt="logo" width="600" height="290">
    <div class="container">
        <div class="footer-top">
            <div class="row">
                <div class="col-sm-6 col-md-3">
                    <div class="heading">{{ __('front.footer.hosting') }}</div>
                    <ul class="footer-menu">
                        <li class="menu-item"><a href="{{ route('front.webhosting') }}">{{ __('front.nav.webhosting') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.gamehosting') }}">{{ __('front.nav.gamehosting') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.vps') }}">{{ __('front.nav.vps') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.domains') }}">{{ __('front.nav.domains') }}</a></li>
                    </ul>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="heading">{{ __('front.footer.support') }}</div>
                    <ul class="footer-menu">
                        <li class="menu-item"><a href="{{ config('app.customer_panel_url') }}">{{ __('front.footer.client_zone') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.contact') }}">{{ __('front.nav.contact') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.faq') }}">FAQ</a></li>
                    </ul>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="heading">{{ __('front.footer.company') }}</div>
                    <ul class="footer-menu">
                        <li class="menu-item"><a href="{{ route('front.about') }}">{{ __('front.footer.about') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.legal') }}">{{ __('front.footer.legal') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.gdpr') }}">GDPR</a></li>
                    </ul>
                </div>
                <div class="col-sm-6 col-md-3">
                    <img class="svg logo-footer d-block" src="{{ asset('front/img/logo.svg') }}" alt="Onhost.cz" width="200" height="50">
                    <div class="copyright">© {{ date('Y') }} Onhost.cz — {{ __('front.footer.rights') }}</div>
                    <div class="soc-icons">
                        <a href="#" title="Facebook"><i class="fab fa-facebook-f withborder noshadow"></i></a>
                        <a href="#" title="X"><i class="fab fa-x-twitter withborder noshadow"></i></a>
                        <a href="#" title="Instagram"><i class="fab fa-instagram withborder noshadow"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>
