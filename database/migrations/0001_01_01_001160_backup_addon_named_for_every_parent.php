<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Onhost\Domain\Catalog\Models\Product;

/**
 * Staging browser check 2026-10-13 (B2, TASK-0178): the backup add-on `backup-plus` is sold with web hosting as well as with a VPS
 * (`meta.addon_products`), but the catalogue called it "Zálohy VPS (PBS)" / "Daily backups to Proxmox Backup Server", so a web
 * hosting line in the cart, its checkout row and its invoice named a VPS product. A deploy runs migrations, not seeders: the name
 * and description the seeder wrote are replaced here — only while they are still the seeded words (a name staff set in the price
 * list is theirs). Prices, plans and plan versions are untouched.
 */
return new class extends Migration
{
    private const OLD = [
        'name' => ['cs' => 'Zálohy VPS (PBS)', 'en' => 'VPS backups (PBS)'],
        'description' => ['cs' => 'Denní zálohy na Proxmox Backup Server s offsite kopií a restore testem.', 'en' => 'Daily backups to Proxmox Backup Server with offsite copy and restore test.'],
    ];

    private const NEW = [
        'name' => ['cs' => 'Rozšířené zálohy', 'en' => 'Extended backups'],
        'description' => ['cs' => 'Denní zálohy s delší historií a kopií mimo server.', 'en' => 'Daily backups with a longer history and an off-site copy.'],
    ];

    public function up(): void
    {
        $this->swap(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->swap(self::NEW, self::OLD);
    }

    /**
     * @param  array{name: array<string,string>, description: array<string,string>}  $from
     * @param  array{name: array<string,string>, description: array<string,string>}  $to
     */
    private function swap(array $from, array $to): void
    {
        $product = Product::query()->where('key', 'backup-plus')->first();
        if ($product === null) {
            return; // a fresh database: the seeder writes the new words
        }
        $changes = [];
        foreach (['name', 'description'] as $field) {
            if ((array) $product->{$field} === $from[$field]) {
                $changes[$field] = $to[$field];
            }
        }
        if ($changes !== []) {
            $product->forceFill($changes)->save();
        }
    }
};
