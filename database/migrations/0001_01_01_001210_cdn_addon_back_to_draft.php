<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Onhost\Domain\Catalog\Models\Product;

/**
 * Phase U, U2 (CD-3, SEZNAM default "ano"): the add-on "CDN a WAF" (`cdn`) was on sale with no executor of its own. The cart
 * refused it without a Cloudflare account (K7-5), but its plans still promised a monthly transfer (`traffic_tb`) and bot management
 * (`bot_management`) that nothing on the platform keeps — the last two keys of `PlanPromises::KNOWN_GAPS` that only this product sold.
 * A deploy runs migrations, not seeders: the product the seeder wrote is taken off sale here (state `draft`, as the seeder now writes
 * it) — only while it still stands as the seeder wrote it (state `active`, the seeded name and description); a product staff rewrote
 * is theirs, and the doctor then names its gaps. Plans, versions and prices stay; a customer who holds a CDN subscription keeps it
 * (renewals price the held version, not the product's state). `down()` puts back on sale only the draft this migration made.
 */
return new class extends Migration
{
    private const KEY = 'cdn';

    private const NAME = ['cs' => 'CDN a WAF', 'en' => 'CDN and WAF'];

    private const DESCRIPTION = ['cs' => 'Anycast CDN s HTTP/3, WAF a ochranou proti botům před vaším webem.', 'en' => 'Anycast CDN with HTTP/3, WAF and bot protection in front of your site.'];

    public function up(): void
    {
        $product = $this->seeded('active');
        $product?->forceFill(['state' => 'draft'])->save();
    }

    public function down(): void
    {
        $product = $this->seeded('draft');
        $product?->forceFill(['state' => 'active'])->save();
    }

    /** The cdn product in the given state, only while it carries the seeded name and description. */
    private function seeded(string $state): ?Product
    {
        $product = Product::query()->where('key', self::KEY)->first();
        if ($product === null || $product->state !== $state) {
            return null; // a fresh database (the seeder writes it as a draft) or a product staff took elsewhere
        }

        return self::same((array) $product->name, self::NAME) && self::same((array) $product->description, self::DESCRIPTION) ? $product : null;
    }

    /**
     * @param  array<mixed>  $stored
     * @param  array<string, string>  $seeded
     */
    private static function same(array $stored, array $seeded): bool
    {
        if (count($stored) !== count($seeded)) {
            return false;
        }
        foreach ($seeded as $locale => $text) {
            if (($stored[$locale] ?? null) !== $text) {
                return false;
            }
        }

        return true;
    }
};
