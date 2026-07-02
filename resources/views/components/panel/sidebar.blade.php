{{-- ───────────────────────────────────────────────────────
     Cuba Panel Sidebar — Customer / Partner / Admin
     ALL icon names VERIFIED against feather.min.js:
     Non-hyphenated: stored as  name:'<svg>'
     Hyphenated:    stored as  "name":'<svg>'
──────────────────────────────────────────────────────── --}}
@php
    $p = fn(string $name) => request()->routeIs($name . '*');
    $canAdmin       = auth()->user()?->can('access-admin');
    $canPartner     = auth()->user()?->can('access-partner');
    $isImpersonating = session()->has('_impersonated_by');
@endphp

<div class="sidebar-wrapper" data-layout="stroke-svg">
<div>
    <div class="logo-wrapper">
        <a href="{{ $canAdmin ? route('admin.dashboard') : route('panel.dashboard') }}">
            <img class="max-w-full h-auto for-light" src="{{ asset('panel/images/logo/logo.png') }}" alt="OnHost.cz">
            <img class="max-w-full h-auto for-dark" src="{{ asset('panel/images/logo/logo_dark.png') }}" alt="OnHost.cz">
        </a>
        <div class="toggle-sidebar"><i class="status_toggle middle sidebar-toggle" data-feather="align-center"></i></div>
    </div>

    {{-- Impersonation banner --}}
    @if($isImpersonating)
    <div class="p-2 mx-2 mb-1 rounded" style="background:rgba(243,156,18,.15);border:1px solid rgba(243,156,18,.4);">
        <p class="f-11 f-light mb-1 text-center">
            <i data-feather="eye" style="width:11px;height:11px;"></i>
            Přihlášen za: <strong>{{ auth()->user()?->name }}</strong>
        </p>
        <a href="{{ route('admin.impersonate.stop') }}"
           class="btn btn-warning btn-xs w-full text-white"
           style="font-size:10px;padding:2px 6px;">
            Zpět na admin účet
        </a>
    </div>
    @endif

    <nav class="sidebar-main">
    <div class="left-arrow" id="left-arrow"><i data-feather="arrow-left"></i></div>
    <div id="sidebar-menu">
    <ul class="sidebar-links" id="simple-bar">
        <li class="back-btn">
            <div class="mobile-back text-end"><span>Zpět</span><i class="fa-solid fa-angle-right ps-2"></i></div>
        </li>

        {{-- Required by sidebar-pin.js --}}
        <li class="pin-title sidebar-main-title">
            <div><h6>Oblíbené</h6></div>
        </li>

{{-- ══════════════════════════════════════════════════════
     ZÁKAZNÍK
══════════════════════════════════════════════════════ --}}
        <li class="sidebar-main-title"><div><h6>Zákazník</h6></div></li>

        <li class="sidebar-list">
            <i class="fa-solid fa-thumbtack"></i>
            <a class="sidebar-link sidebar-title link-nav {{ $p('panel.dashboard') ? 'active' : '' }}"
               href="{{ route('panel.dashboard') }}">
                <i data-feather="home"></i><span>Přehled</span>
            </a>
        </li>

        <x-panel.sidebar-submenu icon="server" label="Moje služby"
            :active="$p('panel.services') || $p('panel.domains')">
            <x-panel.sidebar-link :href="route('panel.services.index')" icon="server" label="Hostingové služby" />
            <x-panel.sidebar-link :href="route('panel.domains.index')" icon="globe" label="Domény" />
        </x-panel.sidebar-submenu>

        <x-panel.sidebar-submenu icon="shopping-cart" label="Objednávky a nákup"
            :active="$p('panel.orders') || $p('panel.cart') || $p('panel.checkout') || $p('panel.wishlist')">
            <x-panel.sidebar-link :href="route('panel.orders.index')" icon="package" label="Moje objednávky" />
            <x-panel.sidebar-link :href="route('panel.orders.create')" icon="plus-circle" label="Nová objednávka" />
            <x-panel.sidebar-link :href="route('panel.cart.index')" icon="shopping-bag" label="Košík" />
            <x-panel.sidebar-link :href="route('panel.checkout.index')" icon="credit-card" label="Pokladna" />
            <x-panel.sidebar-link :href="route('panel.wishlist.index')" icon="heart" label="Oblíbené" />
        </x-panel.sidebar-submenu>

        <x-panel.sidebar-submenu icon="file-text" label="Fakturace"
            :active="$p('panel.billing')">
            <x-panel.sidebar-link :href="route('panel.billing.invoices')" icon="file-text" label="Faktury" />
            <x-panel.sidebar-link :href="route('panel.billing.payments')" icon="credit-card" label="Platby" />
            <x-panel.sidebar-link :href="route('panel.billing.credits')" icon="dollar-sign" label="Kredit" />
        </x-panel.sidebar-submenu>

        <x-panel.sidebar-submenu icon="book-open" label="Obsah a info"
            :active="$p('panel.blog') || $p('panel.kb') || $p('panel.faq')">
            <x-panel.sidebar-link :href="route('panel.faq.index')" icon="help-circle" label="FAQ" />
            <x-panel.sidebar-link :href="route('panel.kb.index')" icon="book-open" label="Znalostní báze" />
            <x-panel.sidebar-link :href="route('panel.blog.index')" icon="rss" label="Blog" />
        </x-panel.sidebar-submenu>

        <x-panel.sidebar-submenu icon="life-buoy" label="Podpora"
            :active="$p('panel.support') || $p('panel.ai')">
            <x-panel.sidebar-link :href="route('panel.support.index')" icon="message-square" label="Tickety" />
            <x-panel.sidebar-link :href="route('panel.ai.index')" icon="zap" label="AI asistent" />
        </x-panel.sidebar-submenu>

        <x-panel.sidebar-submenu icon="user" label="Muj ucet"
            :active="$p('panel.account') || $p('panel.notifications')">
            <x-panel.sidebar-link :href="route('panel.account.profile')" icon="user" label="Profil" />
            <x-panel.sidebar-link :href="route('panel.account.billing')" icon="dollar-sign" label="Fakturační údaje" />
            <x-panel.sidebar-link :href="route('panel.account.security')" icon="lock" label="Zabezpečení" />
            <x-panel.sidebar-link :href="route('panel.account.api-tokens')" icon="key" label="API tokeny" />
            <x-panel.sidebar-link :href="route('panel.notifications.index')" icon="bell" label="Notifikace" />
        </x-panel.sidebar-submenu>

{{-- ══════════════════════════════════════════════════════
     PARTNER
══════════════════════════════════════════════════════ --}}
        @if($canPartner)
        <li class="sidebar-main-title"><div><h6>Partner program</h6></div></li>

        <li class="sidebar-list">
            <i class="fa-solid fa-thumbtack"></i>
            <a class="sidebar-link sidebar-title link-nav {{ $p('partner.dashboard') ? 'active' : '' }}"
               href="{{ route('partner.dashboard') }}">
                <i data-feather="bar-chart"></i><span>Dashboard partnera</span>
            </a>
        </li>

        <x-panel.sidebar-submenu icon="trending-up" label="Referraly a provize"
            :active="$p('partner.referrals') || $p('partner.commissions')">
            <x-panel.sidebar-link :href="route('partner.referrals')" icon="user-plus" label="Referraly" />
            <x-panel.sidebar-link :href="route('partner.commissions')" icon="trending-up" label="Provize" />
        </x-panel.sidebar-submenu>

        <x-panel.sidebar-submenu icon="dollar-sign" label="Finance"
            :active="$p('partner.payouts')">
            <x-panel.sidebar-link :href="route('partner.payouts')" icon="dollar-sign" label="Výplaty" />
        </x-panel.sidebar-submenu>

        <x-panel.sidebar-submenu icon="image" label="Propagace"
            :active="$p('partner.assets') || $p('partner.profile')">
            <x-panel.sidebar-link :href="route('partner.assets')" icon="image" label="Materiály a bannery" />
            <x-panel.sidebar-link :href="route('partner.profile')" icon="settings" label="Nastavení profilu" />
        </x-panel.sidebar-submenu>
        @endif

{{-- ══════════════════════════════════════════════════════
     ADMIN
══════════════════════════════════════════════════════ --}}
        @if($canAdmin)
        <li class="sidebar-main-title"><div><h6>Administrace</h6></div></li>

        <li class="sidebar-list">
            <i class="fa-solid fa-thumbtack"></i>
            <a class="sidebar-link sidebar-title link-nav {{ $p('admin.dashboard') ? 'active' : '' }}"
               href="{{ route('admin.dashboard') }}">
                <i data-feather="pie-chart"></i><span>Admin Dashboard</span>
            </a>
        </li>

        <x-panel.sidebar-submenu icon="users" label="CRM — Uzivatele"
            :active="$p('admin.customers') || $p('admin.users') || $p('admin.roles') || $p('admin.contacts')">
            <x-panel.sidebar-link :href="route('admin.customers.index')" icon="users" label="Zákazníci" />
            <x-panel.sidebar-link :href="route('admin.users.index')" icon="user" label="Uživatelé" />
            <x-panel.sidebar-link :href="route('admin.user-cards')" icon="grid" label="Kartový pohled" />
            <x-panel.sidebar-link :href="route('admin.roles-permission')" icon="shield" label="Role a oprávnění" />
            <x-panel.sidebar-link :href="route('admin.contacts')" icon="book-open" label="Kontakty" />
        </x-panel.sidebar-submenu>

        <x-panel.sidebar-submenu icon="shopping-cart" label="Obchod a platby"
            :active="$p('admin.orders') || $p('admin.invoices') || $p('admin.payments') || $p('admin.credits')">
            <x-panel.sidebar-link :href="route('admin.orders.index')" icon="package" label="Objednávky" />
            <x-panel.sidebar-link :href="route('admin.invoices.index')" icon="file-text" label="Faktury" />
            <x-panel.sidebar-link :href="route('admin.payments.index')" icon="credit-card" label="Platby" />
            <x-panel.sidebar-link :href="route('admin.credits.index')" icon="dollar-sign" label="Kredit" />
            <x-panel.sidebar-link :href="route('admin.credits.transactions')" icon="list" label="Kreditní transakce" />
        </x-panel.sidebar-submenu>

        <x-panel.sidebar-submenu icon="box" label="Produkty a cenik"
            :active="$p('admin.products') || $p('admin.pricing') || $p('admin.reviews')">
            <x-panel.sidebar-link :href="route('admin.products.index')" icon="box" label="Produkty a tarify" />
            <x-panel.sidebar-link :href="route('admin.pricing')" icon="tag" label="Ceník" />
            <x-panel.sidebar-link :href="route('admin.reviews')" icon="star" label="Recenze" />
            <x-panel.sidebar-link :href="route('admin.discount-codes.index')" icon="tag" label="Slevové kódy" />
        </x-panel.sidebar-submenu>

        <x-panel.sidebar-submenu icon="hard-drive" label="Infrastruktura"
            :active="$p('admin.servers') || $p('admin.services') || $p('admin.domains') || $p('admin.provisioning') || $p('admin.monitoring') || $p('admin.backups')">
            <x-panel.sidebar-link :href="route('admin.servers.index')" icon="hard-drive" label="Servery" />
            <x-panel.sidebar-link :href="route('admin.services.index')" icon="server" label="Hostingové služby" />
            <x-panel.sidebar-link :href="route('admin.domains.index')" icon="globe" label="Domény" />
            <x-panel.sidebar-link :href="route('admin.provisioning.index')" icon="cpu" label="Provisioning" />
            <x-panel.sidebar-link :href="route('admin.monitoring.index')" icon="activity" label="Monitoring" />
            <x-panel.sidebar-link :href="route('admin.backups.index')" icon="archive" label="Zálohy" />
        </x-panel.sidebar-submenu>

        <x-panel.sidebar-submenu icon="edit" label="Obsah webu"
            :active="$p('admin.blog') || $p('admin.kb') || $p('admin.site-content')">
            <x-panel.sidebar-link :href="route('admin.blog.index')" icon="rss" label="Blog" />
            <x-panel.sidebar-link :href="route('admin.kb.index')" icon="book-open" label="Znalostní báze" />
            <x-panel.sidebar-link :href="route('admin.site-content.index')" icon="file-text" label="Obsah stránek" />
        </x-panel.sidebar-submenu>

        <x-panel.sidebar-submenu icon="share-2" label="Partnersky program"
            :active="$p('admin.partners') || $p('admin.partner-program')">
            <x-panel.sidebar-link :href="route('admin.partners.index')" icon="users" label="Partneři" />
            <x-panel.sidebar-link :href="route('admin.partner-program.settings')" icon="settings" label="Nastavení programu" />
        </x-panel.sidebar-submenu>

        <x-panel.sidebar-submenu icon="message-square" label="Komunikace"
            :active="$p('admin.support') || $p('admin.mailbox') || $p('admin.newsletter') || $p('admin.subscribers')">
            <x-panel.sidebar-link :href="route('admin.support.index')" icon="life-buoy" label="Podpora — Tickety" />
            <x-panel.sidebar-link :href="route('admin.mailbox')" icon="inbox" label="Pošta" />
            <x-panel.sidebar-link :href="route('admin.newsletter.index')" icon="mail" label="Newsletter kampaně" />
            <x-panel.sidebar-link :href="route('admin.subscribers.index')" icon="users" label="Odběratelé" />
            <x-panel.sidebar-link :href="route('admin.ai.index')" icon="zap" label="AI asistent" />
        </x-panel.sidebar-submenu>

        <x-panel.sidebar-submenu icon="settings" label="Pracovni nastroje"
            :active="$p('admin.kanban') || $p('admin.tasks') || $p('admin.calendar') || $p('admin.todo') || $p('admin.bookmarks') || $p('admin.file-manager') || $p('admin.social')">
            <x-panel.sidebar-link :href="route('admin.kanban')" icon="trello" label="Kanban board" />
            <x-panel.sidebar-link :href="route('admin.tasks')" icon="check-square" label="Úkoly" />
            <x-panel.sidebar-link :href="route('admin.calendar')" icon="calendar" label="Kalendář" />
            <x-panel.sidebar-link :href="route('admin.todo')" icon="list" label="To-Do seznam" />
            <x-panel.sidebar-link :href="route('admin.bookmarks')" icon="bookmark" label="Záložky" />
            <x-panel.sidebar-link :href="route('admin.file-manager')" icon="folder" label="Správce souborů" />
            <x-panel.sidebar-link :href="route('admin.social')" icon="user-check" label="Admin profil" />
        </x-panel.sidebar-submenu>

        <li class="sidebar-list">
            <i class="fa-solid fa-thumbtack"></i>
            <a class="sidebar-link sidebar-title link-nav {{ $p('admin.search') ? 'active' : '' }}"
               href="{{ route('admin.search') }}">
                <i data-feather="search"></i><span>Hledání</span>
            </a>
        </li>

        <x-panel.sidebar-submenu icon="sliders" label="System a nastaveni"
            :active="$p('admin.integrations') || $p('admin.settings') || $p('admin.system') || $p('admin.logs') || $p('admin.sitemap') || $p('admin.sample-page') || $p('admin.metrics')">
            <x-panel.sidebar-link :href="route('admin.metrics.index')" icon="bar-chart-2" label="Obchodní metriky" />
            <x-panel.sidebar-link :href="route('admin.integrations.index')" icon="link" label="Integrace" />
            <x-panel.sidebar-link :href="route('admin.settings.index')" icon="settings" label="Nastavení" />
            <x-panel.sidebar-link :href="route('admin.system.index')" icon="activity" label="System health" />
            <x-panel.sidebar-link :href="route('admin.logs.audit')" icon="shield" label="Audit log" />
            <x-panel.sidebar-link :href="route('admin.sitemap')" icon="map" label="Mapa webu" />
            <x-panel.sidebar-link :href="route('admin.sample-page')" icon="file-plus" label="Ukázková stránka" />
        </x-panel.sidebar-submenu>

        @endif

        <li class="sidebar-main-title"><div><h6>Relace</h6></div></li>
        <li class="sidebar-list">
            <i class="fa-solid fa-thumbtack"></i>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <a class="sidebar-link sidebar-title link-nav" href="{{ route('logout') }}"
                   onclick="event.preventDefault(); this.closest('form').submit();">
                    <i data-feather="log-out"></i><span>Odhlásit se</span>
                </a>
            </form>
        </li>

    </ul>
    </div>
    <div class="right-arrow" id="right-arrow"><i data-feather="arrow-right"></i></div>
    </nav>
</div>
</div>
