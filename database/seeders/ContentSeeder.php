<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Onhost\Domain\Content\Models\ChangelogEntry;
use Onhost\Domain\Content\Models\Location;
use Onhost\Domain\Content\Models\Post;
use Onhost\Domain\Content\Models\StockItem;
use Onhost\Domain\Support\Models\KnowledgeArticle;

/**
 * Public content imported from the prototype (`database/seeders/data/prototype-content.json`, produced by
 * `node database/seeders/data/export-prototype-content.mjs` from onhost-content.js / onhost-data.js).
 * The hardware stock rows mirror `stock` on `#/technika` in Onhost.dc.html.
 */
final class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $path = __DIR__.'/data/prototype-content.json';
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (! is_array($data) || ! isset($data['cs'], $data['en'])) {
            throw new \RuntimeException('prototype-content.json is missing; run the export script first.');
        }
        $this->posts($data);
        $this->knowledgeBase($data);
        $this->changelog($data);
        $this->locations($data);
        $this->stock();
    }

    private function posts(array $data): void
    {
        $en = collect($data['en']['blog'])->keyBy('slug');
        foreach ($data['cs']['blog'] as $cs) {
            $e = $en->get($cs['slug'], $cs);
            Post::query()->updateOrCreate(['slug' => $cs['slug']], [
                'kind' => 'blog', 'category' => ['cs' => $cs['cat'], 'en' => $e['cat']], 'title' => ['cs' => $cs['title'], 'en' => $e['title']], 'excerpt' => ['cs' => $cs['excerpt'], 'en' => $e['excerpt']],
                'body' => ['cs' => $cs['body'], 'en' => $e['body']], 'author' => $cs['author'] ?? null, 'read_minutes' => self::minutes($cs['read'] ?? '5 min'), 'featured' => (bool) ($cs['featured'] ?? false),
                'state' => 'published', 'published_on' => self::date($cs['date'] ?? null),
            ]);
        }
    }

    private function knowledgeBase(array $data): void
    {
        $en = collect($data['en']['kb'])->keyBy('slug');
        foreach ($data['cs']['kb'] as $cs) {
            $e = $en->get($cs['slug'], $cs);
            KnowledgeArticle::query()->updateOrCreate(['slug' => $cs['slug']], [
                'category' => $cs['cat'], 'title' => ['cs' => $cs['title'], 'en' => $e['title']], 'excerpt' => ['cs' => $cs['excerpt'], 'en' => $e['excerpt']], 'body' => ['cs' => $cs['body'], 'en' => $e['body']],
                'tags' => [], 'state' => 'published', 'author' => null, 'read_minutes' => self::minutes($cs['read'] ?? '3 min'), 'published_at' => self::date($cs['updated'] ?? null),
            ]);
        }
    }

    private function changelog(array $data): void
    {
        $year = (int) config('onhost.content.changelog_year', 2026);
        foreach ($data['cs']['changelog'] as $i => $cs) {
            $e = $data['en']['changelog'][$i] ?? $cs;
            $date = self::date($cs[0], $year);
            ChangelogEntry::query()->updateOrCreate(['entry_date' => $date, 'tag' => $cs[1], 'title->cs' => $cs[3]], [
                'title' => ['cs' => $cs[3], 'en' => $e[3]], 'body' => ['cs' => $cs[4], 'en' => $e[4]], 'state' => 'published',
            ]);
        }
    }

    private function locations(array $data): void
    {
        foreach ($data['cs']['locations'] as $i => $cs) {
            $e = $data['en']['locations'][$i] ?? $cs;
            Location::query()->updateOrCreate(['code' => $cs[0]], [
                'city' => $cs[1], 'country' => ['cs' => $cs[2], 'en' => $e[2]], 'ping_ms' => (int) preg_replace('/\D/', '', (string) $cs[3]) ?: null, 'live' => (bool) $cs[4], 'sort' => $i + 1,
            ]);
        }
    }

    private function stock(): void
    {
        $rows = [
            ['epyc-9354', 'EPYC 9354', ['cs' => '32 jader / 256 GB / 4× 3,84 TB NVMe', 'en' => '32 cores / 256 GB / 4× 3.84 TB NVMe'], 890000, 24, 0, 'ok'],
            ['epyc-9124', 'EPYC 9124', ['cs' => '16 jader / 128 GB / 2× 1,92 TB NVMe', 'en' => '16 cores / 128 GB / 2× 1.92 TB NVMe'], 490000, 41, 0, 'ok'],
            ['l40s', 'L40S', ['cs' => '48 GB VRAM · vLLM předinstalované', 'en' => '48 GB VRAM · vLLM preinstalled'], 1290000, 6, 0, 'warn'],
            ['h100', 'H100', ['cs' => '80 GB VRAM · rezervace od 3 měsíců', 'en' => '80 GB VRAM · reservation from 3 months'], 3890000, 0, 5, 'off'],
            ['storage-240', 'Storage 240 TB', ['cs' => '12× 20 TB HDD · Toshiba MG10', 'en' => '12× 20 TB HDD · Toshiba MG10'], 640000, 0, 0, 'off'],
        ];
        foreach ($rows as $i => [$sku, $name, $spec, $price, $available, $lead, $state]) {
            StockItem::query()->updateOrCreate(['sku' => $sku], ['name' => $name, 'spec' => $spec, 'price_minor' => $price, 'currency' => 'CZK', 'unit' => 'month', 'available' => $available, 'lead_days' => $lead, 'availability' => $state, 'sort' => $i + 1]);
        }
    }

    private static function minutes(string $read): int
    {
        return max(1, (int) preg_replace('/\D/', '', $read));
    }

    /** "18. 8. 2026", "25. 8." (+ year), "25 Aug" → date; null when unparseable. */
    private static function date(?string $value, ?int $year = null): ?string
    {
        if ($value === null) {
            return null;
        }
        if (preg_match('/^(\d{1,2})\.\s*(\d{1,2})\.(?:\s*(\d{4}))?$/u', trim($value), $m)) {
            return Carbon::create((int) ($m[3] ?? $year ?? now()->year), (int) $m[2], (int) $m[1])->toDateString();
        }
        try {
            return Carbon::parse($value.($year !== null && ! preg_match('/\d{4}/', $value) ? " {$year}" : ''))->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
