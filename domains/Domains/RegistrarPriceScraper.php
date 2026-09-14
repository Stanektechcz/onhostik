<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains;

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Domains\Models\RegistrarTldCost;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\PublicPriceListScraper;

/**
 * Imports the public (retail) domain price lists of the registrars into the price book. Precedence per row:
 * `manual` (staff) and `api` (wholesale from the registrar's API) are never overwritten by a scrape; `scrape`
 * and `seed` rows are refreshed. Scraped rows carry `meta.retail = true` because a public list is the price the
 * registrar charges its own customers — the real wholesale cost is at most that.
 */
final class RegistrarPriceScraper
{
    public function __construct(private readonly AuditRecorder $audit, private readonly OutboxPublisher $outbox) {}

    /** @return array<string, class-string<PublicPriceListScraper>> registrar key → scraper */
    public static function scrapers(): array
    {
        return array_filter((array) config('onhost.domains.registrar.scrapers', []), fn ($class) => is_string($class) && is_a($class, PublicPriceListScraper::class, true));
    }

    /**
     * @param  string|null  $registrar  one registrar or all with a scraper
     * @param  array<string,string>  $htmlByRegistrar  pre-fetched HTML (files, tests) instead of a live fetch
     * @return array{imported:int, registrars:array<string, array{tlds:int, imported:int, skipped:int, url:string, error:?string}>}
     */
    public function scrape(?string $registrar = null, array $htmlByRegistrar = [], ?CommandContext $context = null): array
    {
        $scrapers = self::scrapers();
        if ($registrar !== null && ! isset($scrapers[$registrar])) {
            throw new DomainError('registrar_scraper_unknown', 'No public price list scraper for '.$registrar.'; available: '.implode(', ', array_keys($scrapers)).'.', 422, ['field' => 'registrar']);
        }
        $report = ['imported' => 0, 'registrars' => []];
        foreach ($scrapers as $key => $class) {
            if ($registrar !== null && $key !== $registrar) {
                continue;
            }
            /** @var PublicPriceListScraper $scraper */
            $scraper = app($class);
            $row = ['tlds' => 0, 'imported' => 0, 'skipped' => 0, 'url' => $scraper->url(), 'error' => null];
            try {
                $html = $htmlByRegistrar[$key] ?? $this->fetch($scraper->url());
                $prices = $scraper->parse($html);
                if ($prices === []) {
                    throw new DomainError('registrar_pricelist_empty', 'The price list page yielded no TLD rows (layout changed?).', 502);
                }
                $row['tlds'] = count($prices);
                $instanceId = ProviderInstance::query()->platform()->where('provider', $key)->where('state', 'active')->orderBy('key')->value('id');
                foreach ($prices as $tld => $price) {
                    $existing = RegistrarTldCost::query()->where('registrar_provider', $key)->where('tld', $tld)->first();
                    if ($existing !== null && in_array($existing->source, ['manual', 'api'], true)) {
                        $row['skipped']++;

                        continue;
                    }
                    RegistrarTldCost::query()->updateOrCreate(['registrar_provider' => $key, 'tld' => $tld], [
                        'provider_instance_id' => $instanceId, 'currency' => strtoupper((string) $price['currency']),
                        'register_minor' => RegistrarPricing::minor($price['register'] ?? null), 'renew_minor' => RegistrarPricing::minor($price['renew'] ?? null),
                        'transfer_minor' => RegistrarPricing::minor($price['transfer'] ?? null), 'restore_minor' => $existing?->restore_minor,
                        'source' => 'scrape', 'fetched_at' => now(),
                        'meta' => ['retail' => true, 'url' => $scraper->url(), 'promo' => (bool) ($price['promo'] ?? false), 'min_years' => $price['min_years'] ?? null],
                    ]);
                    $row['imported']++;
                }
                $report['imported'] += $row['imported'];
            } catch (\Throwable $e) {
                $row['error'] = mb_substr($e->getMessage(), 0, 250);
            }
            $report['registrars'][$key] = $row;
        }
        if ($context !== null) {
            $this->audit->record($context, 'registrar.costs.scrape', 'succeeded', $report, 'registrar', 'costs');
        }
        $this->outbox->publish(GenericEvent::of('registrar.costs.scraped', 'registrar', 'costs', $report));

        return $report;
    }

    private function fetch(string $url): string
    {
        $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; ONhost price book; +https://www.onhost.cz)', 'Accept' => 'text/html'])->timeout(40)->get($url);
        if (! $response->successful()) {
            throw new DomainError('registrar_pricelist_unreachable', "Price list {$url} answered HTTP {$response->status()}.", 502);
        }

        return (string) $response->body();
    }
}
