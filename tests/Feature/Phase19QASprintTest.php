<?php

declare(strict_types=1);

use App\Domains\Billing\Actions\CreateOrderAction;
use App\Domains\Billing\Actions\IssueProformaInvoiceAction;
use App\Domains\Billing\Actions\IssueTaxDocumentAction;
use App\Domains\Billing\Actions\ProcessMockPaymentAction;
use App\Domains\Customer\Models\Customer;
use App\Domains\Customer\Models\CustomerAddress;
use App\Domains\Products\Models\PricingPlan;
use App\Domains\Products\Models\Product;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Jobs\ProvisionHostingServiceJob;
use App\Models\BlogPost;
use App\Models\KbArticle;
use App\Models\SiteContent;
use Database\Seeders\BlogKbContentSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Support\Facades\Bus;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ─── A. Route Smoke Tests ──────────────────────────────────────────────────

it('all main public routes return 200', function (string $url): void {
    $this->get($url)->assertOk();
})->with([
    '/',
    '/webhosting',
    '/wordpress-hosting',
    '/vps',
    '/gamehosting',
    '/mailhosting',
    '/managed-hosting',
    '/dedikovane-servery',
    '/domeny',
    '/blog',
    '/znalostni-baze',
    '/faq',
    '/kontakt',
    '/login',
    '/register',
]);

it('gamehosting page shows coming-soon text when plans are inactive', function (): void {
    // Gamehosting is seeded with is_active=false for both product and plans
    $this->get('/gamehosting')
        ->assertOk()
        ->assertSee('Připravujeme');
});

it('gamehosting plan cannot be ordered — returns 404', function (): void {
    $gamePlan = PricingPlan::whereHas('product', fn ($q) => $q->where('slug', 'gamehosting'))
        ->first();

    if ($gamePlan === null) {
        $this->markTestSkipped('Gamehosting plans not seeded');
    }

    $this->get("/objednavka/{$gamePlan->id}")->assertNotFound();
});

it('blog and KB have published content after seeding', function (): void {
    $this->seed(BlogKbContentSeeder::class);

    $this->get('/blog')->assertOk()->assertDontSee('Brzy přidáme');
    $this->get('/znalostni-baze')->assertOk();

    expect(BlogPost::where('is_published', true)->count())->toBeGreaterThanOrEqual(2);
    expect(KbArticle::where('is_published', true)->count())->toBeGreaterThanOrEqual(5);
});

it('blog article detail returns 200', function (): void {
    $this->seed(BlogKbContentSeeder::class);
    $post = BlogPost::where('is_published', true)->first();
    expect($post)->not->toBeNull();
    $this->get("/blog/{$post->slug}")->assertOk();
});

it('KB article detail returns 200', function (): void {
    $this->seed(BlogKbContentSeeder::class);
    $article = KbArticle::where('is_published', true)->first();
    expect($article)->not->toBeNull();
    $this->get("/znalostni-baze/{$article->slug}")->assertOk();
});

// ─── B. Product Orderability Tests ────────────────────────────────────────

it('webhosting plans are orderable', function (): void {
    $plan = PricingPlan::whereHas('product', fn ($q) => $q->where('slug', 'webhosting'))
        ->where('is_active', true)->first();
    expect($plan)->not->toBeNull();
    $this->get("/objednavka/{$plan->id}")->assertOk();
});

it('VPS plans are orderable', function (): void {
    $plan = PricingPlan::whereHas('product', fn ($q) => $q->where('slug', 'vps'))
        ->where('is_active', true)->first();
    expect($plan)->not->toBeNull();
    $this->get("/objednavka/{$plan->id}")->assertOk();
});

it('mailhosting plans are orderable', function (): void {
    $plan = PricingPlan::whereHas('product', fn ($q) => $q->where('slug', 'mailhosting'))
        ->where('is_active', true)->first();
    expect($plan)->not->toBeNull();
    $this->get("/objednavka/{$plan->id}")->assertOk();
});

it('managed hosting plans are orderable', function (): void {
    $plan = PricingPlan::whereHas('product', fn ($q) => $q->where('slug', 'managed-hosting'))
        ->where('is_active', true)->first();
    expect($plan)->not->toBeNull();
    $this->get("/objednavka/{$plan->id}")->assertOk();
});

// ─── C. Billing / Tax Document Tests ──────────────────────────────────────

it('customer WITH billing address gets tax document after payment', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    // Add billing address
    CustomerAddress::create([
        'customer_id' => $customer->id,
        'type'        => 'billing',
        'street'      => 'Testovací 1',
        'city'        => 'Praha',
        'zip'         => '11000',
        'country_code' => 'CZ',
        'is_primary'  => true,
    ]);

    // Update customer with company info
    $customer->update([
        'type'         => 'company',
        'company_name' => 'Test Firma s.r.o.',
    ]);

    ['invoice' => $invoice] = placeOrder($user);
    app(ProcessMockPaymentAction::class)->execute($invoice);

    $invoice->refresh();
    expect($invoice->status->value)->toBe('paid');

    $taxDoc = app(IssueTaxDocumentAction::class)->execute($invoice);
    expect($taxDoc)->not->toBeNull()
        ->and($taxDoc->type->value)->toBe('invoice');
});

it('customer WITHOUT billing address cannot get tax document — no 500', function (): void {
    $user = customerUser();

    ['invoice' => $invoice] = placeOrder($user);
    app(ProcessMockPaymentAction::class)->execute($invoice);
    $invoice->refresh();

    expect(fn () => app(IssueTaxDocumentAction::class)->execute($invoice))
        ->toThrow(\App\Domains\Billing\Exceptions\IncompleteBillingDetailsException::class);
});

// ─── D. Provisioning Consistency Tests ────────────────────────────────────

it('webhosting order dispatches aaPanel provisioning job', function (): void {
    Bus::fake();

    $user = customerUser();
    ['order' => $order, 'invoice' => $invoice] = placeOrder($user);
    app(ProcessMockPaymentAction::class)->execute($invoice);

    Bus::assertDispatched(ProvisionHostingServiceJob::class);
});

it('VPS order dispatches provisioning job', function (): void {
    Bus::fake();

    $user   = customerUser();
    $plan   = PricingPlan::whereHas('product', fn ($q) => $q->where('slug', 'vps'))
        ->where('is_active', true)->first();
    $customer = $user->customer;

    $order   = app(CreateOrderAction::class)->execute($customer, $plan, []);
    $invoice = app(IssueProformaInvoiceAction::class)->execute($order);
    app(ProcessMockPaymentAction::class)->execute($invoice);

    Bus::assertDispatched(ProvisionHostingServiceJob::class);
});

it('mailhosting order creates service with correct product name', function (): void {
    $user    = customerUser();
    $plan    = PricingPlan::whereHas('product', fn ($q) => $q->where('slug', 'mailhosting'))
        ->where('is_active', true)->first();
    $customer = $user->customer;

    $order   = app(CreateOrderAction::class)->execute($customer, $plan, []);
    $invoice = app(IssueProformaInvoiceAction::class)->execute($order);
    app(ProcessMockPaymentAction::class)->execute($invoice);

    $service = $customer->services()->with('product')->latest('id')->first();
    expect($service)->not->toBeNull()
        ->and($service->product?->slug)->toBe('mailhosting');
});

it('managed hosting order creates service with correct product', function (): void {
    $user     = customerUser();
    $plan     = PricingPlan::whereHas('product', fn ($q) => $q->where('slug', 'managed-hosting'))
        ->where('is_active', true)->first();
    $customer = $user->customer;

    $order   = app(CreateOrderAction::class)->execute($customer, $plan, []);
    $invoice = app(IssueProformaInvoiceAction::class)->execute($order);
    app(ProcessMockPaymentAction::class)->execute($invoice);

    $service = $customer->services()->with('product')->latest('id')->first();
    expect($service)->not->toBeNull()
        ->and($service->product?->slug)->toBe('managed-hosting');
});

it('pending VPS service uses Proxmox driver', function (): void {
    Bus::fake();

    $user     = customerUser();
    $plan     = PricingPlan::whereHas('product', fn ($q) => $q->where('slug', 'vps'))
        ->where('is_active', true)->first();
    $customer = $user->customer;

    $order   = app(CreateOrderAction::class)->execute($customer, $plan, []);
    $invoice = app(IssueProformaInvoiceAction::class)->execute($order);
    app(ProcessMockPaymentAction::class)->execute($invoice);

    $service = $customer->services()->latest('id')->first();
    expect($service)->not->toBeNull()
        ->and($service->provisioning_driver->value)->toBe(ProvisioningDriver::Proxmox->value)
        ->and($service->status)->toBe(ServiceStatus::Pending);
});

// ─── E. CMS Content Tests ─────────────────────────────────────────────────

it('SiteContent::get returns seeded value after SiteContentSeeder', function (): void {
    $this->seed(SiteContentSeeder::class);

    $val = SiteContent::get('homepage.announcement.text');
    expect($val)->not->toBeEmpty();
});

it('SiteContent::get returns fallback when key missing', function (): void {
    $val = SiteContent::get('nonexistent.key.xyz', 'fallback-value');
    expect($val)->toBe('fallback-value');
});

it('homepage announcement bar is visible when SiteContent active=1', function (): void {
    $this->seed(SiteContentSeeder::class);

    $response = $this->get('/');
    $response->assertOk();

    $announcementText = SiteContent::get('homepage.announcement.text');
    if ($announcementText) {
        $response->assertSee($announcementText);
    }
});
