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
                        <li class="menu-item"><a href="{{ route('front.wordpress') }}">{{ __('front.pages.wordpress.title') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.domains') }}">{{ __('front.nav.domains') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.vps') }}">{{ __('front.nav.vps') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.gamehosting') }}">{{ __('front.nav.gamehosting') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.mailhosting') }}">{{ __('front.pages.mailhosting.title') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.dedicated') }}">{{ __('front.pages.dedicated.title') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.ssl') }}">{{ __('front.pages.ssl.title') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.reseller') }}">{{ __('front.pages.reseller.title') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.colocation') }}">{{ __('front.pages.colocation.title') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.email-security') }}">E-mailová bezpečnost</a></li>
                        <li class="menu-item"><a href="{{ route('front.database') }}">Databáze (DBaaS)</a></li>
                    </ul>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="heading">{{ __('front.footer.support') }}</div>
                    <ul class="footer-menu">
                        <li class="menu-item"><a href="{{ config('app.customer_panel_url') }}">{{ __('front.footer.client_zone') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.support') }}">{{ __('front.pages.support.title') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.kb') }}">{{ __('front.pages.kb.title') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.blog.index') }}">Blog</a></li>
                        <li class="menu-item"><a href="{{ route('front.contact') }}">{{ __('front.nav.contact') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.faq') }}">FAQ</a></li>
                        <li class="menu-item"><a href="{{ route('front.sla') }}">SLA</a></li>
                        <li class="menu-item"><a href="{{ route('front.ddos') }}">{{ __('front.pages.ddos.title') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.developer') }}">{{ __('front.pages.developer.title') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.datacenter') }}">Datacenter</a></li>
                    </ul>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="heading">{{ __('front.footer.company') }}</div>
                    <ul class="footer-menu">
                        <li class="menu-item"><a href="{{ route('front.about') }}">{{ __('front.footer.about') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.legal') }}">{{ __('front.footer.legal') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.gdpr') }}">GDPR</a></li>
                        <li class="menu-item"><a href="{{ route('front.cookies') }}">{{ __('front.pages.cookies.title') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.refund-policy') }}">{{ __('front.pages.refund.title') }}</a></li>
                        <li class="menu-item"><a href="{{ route('front.builder') }}">{{ __('front.pages.builder.title') }}</a></li>
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
