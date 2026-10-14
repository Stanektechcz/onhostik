<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Cache;
use Onhost\Domain\Catalog\Models\Product;

/*
 * Staging browser check 2026-10-13 (B2, docs/runbooks/staging-rehearsal-2026-10-13.md): a web hosting "Standard" card put a line
 * named "VPS Standard" in the cart drawer and the drawer offered "Zálohy VPS (PBS)" for it. The line name was fixed by L2
 * (TASK-0164, after the staging release: the card's cart line is the catalogue's "Webhosting Standard"); the add-on stayed — the
 * web hosting plans sell the backup add-on `backup-plus` (BackupScheduler keeps its policy on a web service), but its catalogue
 * name and description said "VPS" and "Proxmox Backup Server", so every web hosting line, its checkout row and its invoice named
 * a VPS product. The add-on is now named for what it is on every parent it is sold with.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class, ContentSeeder::class]);
    Cache::flush();
});

/** @return array<string, mixed> the `cs`/`en` data of the generated `onhost-data.js` */
function clanData(object $test): array
{
    $js = (string) $test->get('/surfaces/onhost-data.js')->assertOk()->getContent();
    preg_match('/var D = (\{.*\});\n  function L/s', $js, $m);

    return (array) json_decode($m[1] ?? '{}', true);
}

it('offers a web hosting line no add-on named after a VPS or the Proxmox Backup Server', function () {
    $data = clanData($this);

    foreach (['cs', 'en'] as $locale) {
        foreach (['web-hosting', 'web-custom'] as $parent) {
            $products = collect($data[$locale]['addons'][$parent]['products'] ?? []);
            expect($products->pluck('key')->all())->toContain('backup-plus'); // the web plans do sell extended backups
            foreach ($products as $addon) {
                $text = $addon['name'].' '.$addon['desc'].' '.collect($addon['plans'])->pluck('name')->implode(' ');
                expect($text)->not->toMatch('/VPS|PBS|Proxmox/i', "{$locale} {$parent}: {$text}");
            }
        }
        // the pricing card's cart line is the catalogue's product + plan, never "VPS <plan>" (L2 regression guard)
        $standard = collect($data[$locale]['plans'])->firstWhere('key', 'standard');
        expect($standard)->not->toBeNull()->and($standard['label'])->not->toStartWith('VPS ')->toContain('Standard');
    }
    expect(collect($data['cs']['addons']['web-hosting']['products'])->firstWhere('key', 'backup-plus')['name'])->toBe('Rozšířené zálohy')
        ->and(collect($data['en']['addons']['vps']['products'])->firstWhere('key', 'backup-plus')['name'])->toBe('Extended backups');
});

it('renames the add-on in a running catalogue, but keeps a name staff have changed', function () {
    $migration = require base_path('database/migrations/0001_01_01_001160_backup_addon_named_for_every_parent.php');
    $old = ['name' => ['cs' => 'Zálohy VPS (PBS)', 'en' => 'VPS backups (PBS)'],
        'description' => ['cs' => 'Denní zálohy na Proxmox Backup Server s offsite kopií a restore testem.', 'en' => 'Daily backups to Proxmox Backup Server with offsite copy and restore test.']];

    // a database seeded before the rename (a deploy runs migrations, not seeders)
    Product::query()->where('key', 'backup-plus')->firstOrFail()->forceFill($old)->save();
    $migration->up();
    $product = Product::query()->where('key', 'backup-plus')->firstOrFail();
    expect($product->name)->toBe(['cs' => 'Rozšířené zálohy', 'en' => 'Extended backups'])
        ->and($product->description['cs'])->not->toContain('Proxmox')->and($product->description['en'])->not->toContain('Proxmox');

    // a name staff set in the price list is theirs; a description still in the seeded words is not
    Product::query()->where('key', 'backup-plus')->firstOrFail()->forceFill(['name' => ['cs' => 'Zálohy Plus', 'en' => 'Backups Plus'], 'description' => $old['description']])->save();
    $migration->up();
    $kept = Product::query()->where('key', 'backup-plus')->firstOrFail();
    expect($kept->name)->toBe(['cs' => 'Zálohy Plus', 'en' => 'Backups Plus'])->and($kept->description['cs'])->toBe('Denní zálohy s delší historií a kopií mimo server.');

    $migration->down(); // back to the seeded words, still only where the new words stand
    expect(Product::query()->where('key', 'backup-plus')->firstOrFail()->description)->toBe($old['description'])
        ->and(Product::query()->where('key', 'backup-plus')->firstOrFail()->name)->toBe(['cs' => 'Zálohy Plus', 'en' => 'Backups Plus']);
});
