<div class="sidebar-wrapper" data-layout="stroke-svg">
    <div>
        <div class="logo-wrapper">
            <a href="{{ route('panel.dashboard') }}">
                <img class="max-w-full h-auto for-light" src="{{ asset('panel/images/logo/logo.png') }}" alt="Onhost.cz">
                <img class="max-w-full h-auto for-dark" src="{{ asset('panel/images/logo/logo_dark.png') }}" alt="Onhost.cz">
            </a>
            <div class="toggle-sidebar"><i class="status_toggle middle sidebar-toggle" data-feather="align-center"></i></div>
        </div>
        <nav class="sidebar-main">
            <div id="sidebar-menu">
                <ul class="sidebar-links" id="simple-bar">
                    <li class="back-btn">
                        <div class="mobile-back text-end"><span>{{ __('panel.nav.back') }}</span><i class="fa-solid fa-angle-right ps-2"></i></div>
                    </li>

                    {{-- ====== CUSTOMER SECTION ====== --}}
                    <x-panel.sidebar-title :label="__('panel.nav.section_services')" />
                    <x-panel.sidebar-link :href="route('panel.dashboard')" icon="home" :label="__('panel.nav.dashboard')" />
                    <x-panel.sidebar-link :href="route('panel.services.index')" icon="server" :label="__('panel.nav.services')" />
                    <x-panel.sidebar-link :href="route('panel.domains.index')" icon="globe" :label="__('panel.nav.domains')" />
                    <x-panel.sidebar-link :href="route('panel.orders.index')" icon="package" :label="__('panel.nav.orders')" />
                    <x-panel.sidebar-link :href="route('panel.orders.create')" icon="shopping-cart" :label="__('panel.nav.new_order')" />

                    <x-panel.sidebar-title :label="__('panel.nav.section_billing')" />
                    <x-panel.sidebar-link :href="route('panel.billing.invoices')" icon="file-text" :label="__('panel.nav.invoices')" />
                    <x-panel.sidebar-link :href="route('panel.billing.payments')" icon="credit-card" :label="__('panel.nav.payments')" />
                    <x-panel.sidebar-link :href="route('panel.billing.credits')" icon="dollar-sign" :label="__('panel.nav.credits')" />

                    <x-panel.sidebar-title :label="__('panel.nav.section_help')" />
                    <x-panel.sidebar-link :href="route('panel.support.index')" icon="life-buoy" :label="__('panel.nav.support')" />
                    <x-panel.sidebar-link :href="route('panel.ai.index')" icon="zap" :label="__('panel.nav.ai')" />

                    <x-panel.sidebar-title :label="__('panel.nav.section_account')" />
                    <x-panel.sidebar-link :href="route('panel.account.profile')" icon="user" :label="__('panel.nav.profile')" />
                    <x-panel.sidebar-link :href="route('panel.account.billing')" icon="briefcase" :label="__('panel.nav.billing_details')" />
                    <x-panel.sidebar-link :href="route('panel.account.security')" icon="lock" :label="__('panel.nav.security')" />

                    {{-- ====== ADMIN SECTION (admin role only) ====== --}}
                    @can('access-admin')
                        <x-panel.sidebar-title label="ADMIN" />
                        <x-panel.sidebar-link :href="route('admin.dashboard')" icon="activity" :label="__('panel.nav.admin_dashboard')" />
                        <x-panel.sidebar-link :href="route('admin.customers.index')" icon="users" :label="__('panel.nav.admin_customers')" />
                        <x-panel.sidebar-link :href="route('admin.orders.index')" icon="package" :label="__('panel.nav.admin_orders')" />
                        <x-panel.sidebar-link :href="route('admin.invoices.index')" icon="file-text" :label="__('panel.nav.admin_invoices')" />
                        <x-panel.sidebar-link :href="route('admin.payments.index')" icon="credit-card" :label="__('panel.nav.admin_payments')" />
                        <x-panel.sidebar-link :href="route('admin.credits.index')" icon="dollar-sign" :label="__('panel.nav.admin_wallets')" />
                        <x-panel.sidebar-link :href="route('admin.products.index')" icon="box" :label="__('panel.nav.admin_products')" />
                        <x-panel.sidebar-link :href="route('admin.services.index')" icon="server" :label="__('panel.nav.admin_services')" />
                        <x-panel.sidebar-link :href="route('admin.servers.index')" icon="hard-drive" :label="__('panel.nav.admin_servers')" />
                        <x-panel.sidebar-link :href="route('admin.provisioning.index')" icon="cpu" :label="__('panel.nav.admin_provisioning')" />
                        <x-panel.sidebar-link :href="route('admin.domains.index')" icon="globe" :label="__('panel.nav.admin_domains')" />
                        <x-panel.sidebar-link :href="route('admin.monitoring.index')" icon="activity" :label="__('panel.nav.admin_monitoring')" />
                        <x-panel.sidebar-link :href="route('admin.backups.index')" icon="archive" :label="__('panel.nav.admin_backups')" />
                        <x-panel.sidebar-link :href="route('admin.support.index')" icon="life-buoy" :label="__('panel.nav.admin_support')" />
                        <x-panel.sidebar-link :href="route('admin.ai.index')" icon="zap" :label="__('panel.nav.admin_ai')" />
                        <x-panel.sidebar-link :href="route('admin.integrations.index')" icon="link" :label="__('panel.nav.admin_integrations')" />
                        <x-panel.sidebar-link :href="route('admin.system.index')" icon="heart" :label="__('panel.nav.admin_system')" />
                        <x-panel.sidebar-link :href="route('admin.logs.audit')" icon="shield" :label="__('panel.nav.admin_audit')" />
                        <x-panel.sidebar-link :href="route('admin.settings.index')" icon="settings" :label="__('panel.nav.admin_settings')" />
                        <x-panel.sidebar-link :href="route('admin.site-content.index')" icon="edit" label="Obsah webu" />
                        <x-panel.sidebar-link :href="route('admin.blog.index')" icon="rss" label="Blog" />
                        <x-panel.sidebar-link :href="route('admin.kb.index')" icon="book" label="Znalostní báze" />
                    @endcan
                </ul>
            </div>
        </nav>
    </div>
</div>
