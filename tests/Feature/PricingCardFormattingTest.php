<?php

declare(strict_types=1);

use App\Domains\Shared\Support\ResourceFormatter;
use Database\Seeders\ProductCatalogSeeder;

// ── ResourceFormatter unit behaviour ─────────────────────────────────────────

it('formats mb values to GB above the 1024 threshold', function (): void {
    expect(ResourceFormatter::format('disk_mb', 5_120))->toBe('5 GB')
        ->and(ResourceFormatter::format('disk_mb', 512))->toBe('512 MB')
        ->and(ResourceFormatter::format('ram_mb', 1_536))->toBe('1,5 GB');
});

it('appends units for gb and tb keys and passes counts through', function (): void {
    expect(ResourceFormatter::format('bandwidth_gb', 50))->toBe('50 GB')
        ->and(ResourceFormatter::format('bandwidth_tb', 2))->toBe('2 TB')
        ->and(ResourceFormatter::format('emails', 25))->toBe('25')
        ->and(ResourceFormatter::format('databases', 1))->toBe('1');
});

it('passes pre-formatted strings through untouched', function (): void {
    expect(ResourceFormatter::format('disk', 'NVMe SSD'))->toBe('NVMe SSD')
        ->and(ResourceFormatter::format('cpu', '1–8 jader'))->toBe('1–8 jader');
});

// ── Rendered pages use formatted values ──────────────────────────────────────

it('homepage pricing cards show formatted resource values', function (): void {
    $this->seed(ProductCatalogSeeder::class);

    $this->get('/')
        ->assertOk()
        ->assertSee('5 GB')
        ->assertDontSee('DISK<br><span>5120<', escape: false);
});

it('webhosting page pricing shows formatted values', function (): void {
    $this->seed(ProductCatalogSeeder::class);

    $this->get('/webhosting')
        ->assertOk()
        ->assertSee('50 GB');
});
