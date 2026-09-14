<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Partner/reseller programme (handoff: Onhost-partner.dc.html) and public content (blog, changelog, locations, stock, leads). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->unique();   // the partner's own organization
            $table->string('code', 24)->unique();              // referral code used in links / at registration
            $table->string('model', 8)->default('share');      // share | oneoff
            $table->string('tier', 12)->default('bronze');     // bronze | silver | gold | platinum
            $table->unsignedTinyInteger('rate_pct')->default(15);
            $table->timestamp('rate_locked_until')->nullable(); // dropped a tier: old rate kept for 3 months
            $table->bigInteger('volume_3m_minor')->default(0);  // trailing 3-month average monthly paid base
            $table->string('currency', 3)->default('CZK');
            $table->json('whitelabel')->nullable();            // {domain, hide_brand, own_mail, own_prices, own_support, verified_at}
            $table->string('iban', 34)->nullable();            // last payout account
            $table->string('state', 12)->default('applied');   // applied | active | suspended | closed
            $table->json('application')->nullable();           // {company, clients, site, note}
            $table->string('approved_by', 40)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('tier_recomputed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('partner_commissions', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('partner_id', 40)->index();
            $table->string('organization_id', 40)->index();    // client
            $table->string('invoice_id', 40)->nullable()->index();
            $table->string('period', 7)->index();              // YYYY-MM of the paid invoice
            $table->string('kind', 12)->default('share');      // share | oneoff | tail | reversal
            $table->bigInteger('base_minor');                  // invoice net (subtotal − discount)
            $table->unsignedSmallInteger('rate_pct');
            $table->bigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('state', 12)->default('payable');   // payable | allocated | paid | reversed
            $table->string('payout_id', 40)->nullable()->index();
            $table->timestamp('invoice_paid_at')->nullable();
            $table->timestamps();
            $table->index(['invoice_id', 'kind']);
        });

        Schema::create('partner_payouts', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('partner_id', 40)->index();
            $table->string('number', 20)->unique();            // PO-2026-09 (-2 when more than one in a month)
            $table->bigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('method', 16)->default('bank_transfer'); // bank_transfer | offset
            $table->string('iban', 34)->nullable();
            $table->string('state', 12)->default('requested'); // requested | approved | paid | rejected
            $table->json('self_billing');                      // self-billed invoice snapshot: number, seller (partner), buyer (ONhost), lines per client, totals
            $table->string('ledger_transaction_id', 40)->nullable();
            $table->string('payment_reference', 120)->nullable();
            $table->string('decided_by', 40)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('slug', 190)->unique();
            $table->string('kind', 8)->default('blog');        // blog | page
            $table->json('category');                          // {cs, en}
            $table->json('title');
            $table->json('excerpt')->nullable();
            $table->json('body');                              // {cs: [[heading, paragraph]…], en: …}
            $table->string('author', 120)->nullable();
            $table->unsignedSmallInteger('read_minutes')->default(5);
            $table->boolean('featured')->default(false);
            $table->string('state', 12)->default('published'); // draft | published | archived
            $table->date('published_on')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('changelog_entries', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->date('entry_date')->index();
            $table->string('tag', 12)->index();                // panel | fix | infra | api | game
            $table->json('title');                             // {cs, en}
            $table->json('body');
            $table->string('state', 12)->default('published');
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table): void {
            $table->string('code', 8)->primary();              // PRG | BRQ | FRA …
            $table->string('city', 80);
            $table->json('country');                           // {cs, en}
            $table->unsignedSmallInteger('ping_ms')->nullable();
            $table->boolean('live')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('stock_items', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('sku', 40)->unique();
            $table->string('name', 120);
            $table->json('spec');                              // {cs, en}
            $table->bigInteger('price_minor');
            $table->string('currency', 3)->default('CZK');
            $table->string('unit', 24)->default('month');      // rental per month
            $table->integer('available')->default(0);
            $table->unsignedSmallInteger('lead_days')->default(0);
            $table->string('availability', 8)->default('ok'); // ok | warn | off
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('kind', 16)->index();               // migration | consultation | enterprise | contact | tender | reseller
            $table->string('name', 120);
            $table->string('email', 190)->index();
            $table->string('phone', 40)->nullable();
            $table->string('company', 190)->nullable();
            $table->text('message')->nullable();
            $table->json('meta')->nullable();                  // form-specific fields (current provider, sizes, tender deadline, documents sent…)
            $table->string('source', 40)->default('web');
            $table->string('organization_id', 40)->nullable()->index();
            $table->string('state', 12)->default('new');       // new | contacted | qualified | won | lost
            $table->string('owner_id', 40)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['leads', 'stock_items', 'locations', 'changelog_entries', 'posts', 'partner_payouts', 'partner_commissions', 'partners'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
