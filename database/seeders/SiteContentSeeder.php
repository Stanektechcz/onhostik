<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SiteContent;
use Illuminate\Database\Seeder;

/**
 * Seeds editable site-wide content items.
 * Idempotent — uses firstOrCreate so manual admin edits are never overwritten.
 */
class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // ── Homepage announcement bar ──────────────────────────────────────
            [
                'key'        => 'homepage.announcement.text',
                'label'      => 'Oznamovací lišta — text',
                'type'       => 'text',
                'group'      => 'homepage',
                'value'      => 'Spouštíme nové NVMe servery s PHP 8.3 — vyšší výkon, stejná cena.',
                'sort_order' => 1,
            ],
            [
                'key'        => 'homepage.announcement.link',
                'label'      => 'Oznamovací lišta — odkaz',
                'type'       => 'text',
                'group'      => 'homepage',
                'value'      => '/webhosting',
                'sort_order' => 2,
            ],
            [
                'key'        => 'homepage.announcement.active',
                'label'      => 'Oznamovací lišta — zobrazit?',
                'type'       => 'boolean',
                'group'      => 'homepage',
                'value'      => '1',
                'sort_order' => 3,
            ],

            // ── Kontaktní údaje ────────────────────────────────────────────────
            [
                'key'        => 'contact.email.sales',
                'label'      => 'Obchodní e-mail',
                'type'       => 'text',
                'group'      => 'kontakt',
                'value'      => 'info@onhost.cz',
                'sort_order' => 1,
            ],
            [
                'key'        => 'contact.email.support',
                'label'      => 'Podpora e-mail',
                'type'       => 'text',
                'group'      => 'kontakt',
                'value'      => 'podpora@onhost.cz',
                'sort_order' => 2,
            ],
            [
                'key'        => 'contact.phone',
                'label'      => 'Telefon',
                'type'       => 'text',
                'group'      => 'kontakt',
                'value'      => '',
                'sort_order' => 3,
            ],
            [
                'key'        => 'contact.address',
                'label'      => 'Adresa (HTML)',
                'type'       => 'html',
                'group'      => 'kontakt',
                'value'      => 'Adrian Staněk<br>IČO: 08094616',
                'sort_order' => 4,
            ],

            // ── Footer ─────────────────────────────────────────────────────────
            [
                'key'        => 'footer.tagline',
                'label'      => 'Footer — tagline',
                'type'       => 'text',
                'group'      => 'footer',
                'value'      => 'Spolehlivý hosting pro vaše projekty',
                'sort_order' => 1,
            ],
        ];

        foreach ($items as $item) {
            SiteContent::firstOrCreate(['key' => $item['key']], $item);
        }
    }
}
