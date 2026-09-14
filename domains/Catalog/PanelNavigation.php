<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog;

use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Settings\SettingsStore;

/**
 * The customer panel's sidebar follows the offer: a service category is listed when staff keep it switched on and
 * the catalogue sells something in it — and always when the organization already owns a service there (a switched-off
 * or sold-out category never hides what a customer runs). Staff edit the order, the labels and the switches in the
 * system settings (`panel.nav`); the panel and the order wizard read the effective result.
 */
final class PanelNavigation
{
    public const SETTING = 'panel.nav';

    /** category => [label cs, label en, crumb cs, crumb en] — the keys are the panel's service-desk categories */
    public const CATEGORIES = [
        'domain' => ['Domény a DNS', 'Domains and DNS', 'Domény', 'Domains'],
        'web' => ['Webhosting', 'Web hosting', 'Webhosting', 'Web hosting'],
        'game' => ['Herní servery', 'Game servers', 'Hry', 'Games'],
        'vps' => ['Servery a VPS', 'Servers and VPS', 'Servery', 'Servers'],
        'mail' => ['Emailing', 'Email', 'Pošta', 'Mail'],
        'bucket' => ['Objektové úložiště', 'Object storage', 'Úložiště', 'Storage'],
        'housing' => ['Housing a racky', 'Housing and racks', 'Housing', 'Housing'],
    ];

    /** optional sidebar links staff can switch off: key => [label cs, label en, default] */
    public const LINKS = [
        'kb' => ['Znalostní báze', 'Knowledge base', true],
        'status' => ['Stav služeb', 'Service status', true],
        'team' => ['Tým a práva', 'Team and roles', true],
        'api' => ['API klíče a webhooky', 'API keys and webhooks', true],
        'projects' => ['Projekty', 'Projects', true],
        'registrars' => ['Připojené registrátory (WEDOS API)', 'Connected registrars (WEDOS API)', true],
        'audit' => ['Oznámení a audit', 'Notifications and audit', true],
        'windows' => ['Servisní okna', 'Maintenance windows', true],
        'costs' => ['Náklady', 'Costs', true],
        'privacy' => ['Osobní údaje a odchod', 'Personal data and leaving', true],
        'monitoring' => ['Monitoring', 'Monitoring', true],
        'backups' => ['Zálohy', 'Backups', true],
    ];

    public function __construct(private readonly SettingsStore $settings, private readonly CatalogService $catalog) {}

    /** The sidebar category of a product or service; null for add-ons, which live inside their parent service. */
    public static function categoryFor(string $family, ?string $productKey = null, ?string $executor = null): ?string
    {
        return match ($family) {
            'web', 'managed', 'apps' => 'web',
            'game' => 'game',
            'cloud' => 'vps',
            'data' => $productKey === 'object-storage' ? 'bucket' : 'vps',
            'mail' => 'mail',
            'storage' => 'bucket',
            'housing' => 'housing',
            'addon' => match ($executor) {
                'ispconfig', 'aapanel' => 'web',
                'proxmox', 'pbs' => 'vps',
                default => null,
            },
            default => null,
        };
    }

    /**
     * Stored configuration merged over the defaults, categories in their configured order.
     *
     * @return array{categories: array<string, array{enabled: bool, order: int, label: array{cs: string, en: string}}>, links: array<string, bool>}
     */
    public function config(): array
    {
        $stored = (array) $this->settings->get(self::SETTING, []);
        $categories = [];
        $position = 0;
        foreach (self::CATEGORIES as $key => [$cs, $en]) {
            $row = (array) (($stored['categories'] ?? [])[$key] ?? []);
            $label = (array) ($row['label'] ?? []);
            $categories[$key] = [
                'enabled' => array_key_exists('enabled', $row) ? (bool) $row['enabled'] : true,
                'order' => isset($row['order']) ? (int) $row['order'] : $position,
                'label' => ['cs' => trim((string) ($label['cs'] ?? '')) ?: $cs, 'en' => trim((string) ($label['en'] ?? '')) ?: $en],
            ];
            $position++;
        }
        uasort($categories, fn (array $a, array $b) => $a['order'] <=> $b['order']);
        $links = [];
        foreach (self::LINKS as $key => [, , $default]) {
            $links[$key] = array_key_exists($key, (array) ($stored['links'] ?? [])) ? (bool) $stored['links'][$key] : $default;
        }

        return ['categories' => $categories, 'links' => $links];
    }

    /**
     * Staff configuration write (through the catalogue command). Unknown keys are ignored, labels are capped.
     *
     * @param  array<string,mixed>  $input
     * @return array{categories: array<string, array{enabled: bool, order: int, label: array{cs: string, en: string}}>, links: array<string, bool>}
     */
    public function save(array $input, ?string $by = null): array
    {
        $categories = [];
        foreach (self::CATEGORIES as $key => $defaults) {
            $row = (array) (($input['categories'] ?? [])[$key] ?? []);
            $label = (array) ($row['label'] ?? []);
            foreach (['cs', 'en'] as $locale) {
                if (mb_strlen((string) ($label[$locale] ?? '')) > 40) {
                    throw new DomainError('label_too_long', "The {$locale} label of {$key} may have 40 characters at most.", 422, ['field' => "categories.{$key}.label.{$locale}"]);
                }
            }
            $categories[$key] = [
                'enabled' => array_key_exists('enabled', $row) ? (bool) $row['enabled'] : true,
                'order' => (int) ($row['order'] ?? array_search($key, array_keys(self::CATEGORIES), true)),
                'label' => ['cs' => trim((string) ($label['cs'] ?? '')), 'en' => trim((string) ($label['en'] ?? ''))],
            ];
        }
        $links = [];
        foreach (self::LINKS as $key => [, , $default]) {
            $links[$key] = array_key_exists($key, (array) ($input['links'] ?? [])) ? (bool) $input['links'][$key] : $default;
        }
        $this->settings->set(self::SETTING, ['categories' => $categories, 'links' => $links], $by);

        return $this->config();
    }

    /**
     * Categories the catalogue sells something in right now (active products; domains when a TLD is on offer).
     *
     * @return array<string,bool>
     */
    public function offered(): array
    {
        $offered = array_fill_keys(array_keys(self::CATEGORIES), false);
        foreach (Product::query()->where('state', 'active')->get(['key', 'family', 'executor']) as $product) {
            if ($product->family === 'addon') {
                continue;
            }
            $category = self::categoryFor((string) $product->family, (string) $product->key, $product->executor);
            if ($category !== null) {
                $offered[$category] = true;
            }
        }
        $offered['domain'] = $this->catalog->tlds()->isNotEmpty();

        return $offered;
    }

    /**
     * How many services (domains for `domain`) the organization runs per category.
     *
     * @return array<string,int>
     */
    public function owned(?string $organizationId): array
    {
        $owned = array_fill_keys(array_keys(self::CATEGORIES), 0);
        if ($organizationId === null) {
            return $owned;
        }
        $owned['domain'] = Domain::query()->where('organization_id', $organizationId)->count();
        $executors = Product::query()->pluck('executor', 'key')->all();
        $services = Service::query()->where('organization_id', $organizationId)->whereNotIn('state', [ServiceStateMachine::TERMINATED])->get(['family', 'product_key']);
        foreach ($services as $service) {
            $category = self::categoryFor((string) $service->family, (string) $service->product_key, $executors[$service->product_key] ?? null);
            if ($category !== null) {
                $owned[$category]++;
            }
        }

        return $owned;
    }

    /**
     * The sidebar one organization sees: every category with its visibility and whether new orders are possible.
     *
     * @return array{categories: list<array{key: string, label: array{cs: string, en: string}, crumb: array{cs: string, en: string}, enabled: bool, offered: bool, owned: int, visible: bool, orderable: bool}>, links: array<string, bool>}
     */
    public function effective(?string $organizationId): array
    {
        $config = $this->config();
        $offered = $this->offered();
        $owned = $this->owned($organizationId);
        $categories = [];
        foreach ($config['categories'] as $key => $row) {
            [, , $crumbCs, $crumbEn] = self::CATEGORIES[$key];
            $categories[] = [
                'key' => $key, 'label' => $row['label'], 'crumb' => ['cs' => $crumbCs, 'en' => $crumbEn],
                'enabled' => $row['enabled'], 'offered' => $offered[$key], 'owned' => $owned[$key],
                'visible' => $owned[$key] > 0 || ($row['enabled'] && $offered[$key]),
                'orderable' => $row['enabled'] && $offered[$key],
            ];
        }

        return ['categories' => $categories, 'links' => $config['links']];
    }

    /**
     * Staff view: the configuration plus what the catalogue offers and how many organizations own something per category.
     *
     * @return array{config: array<string,mixed>, categories: list<array<string,mixed>>, links: list<array{key: string, label: array{cs: string, en: string}, enabled: bool}>}
     */
    public function overview(): array
    {
        $config = $this->config();
        $offered = $this->offered();
        $owners = array_fill_keys(array_keys(self::CATEGORIES), []);
        $executors = Product::query()->pluck('executor', 'key')->all();
        foreach (Service::query()->whereNotIn('state', [ServiceStateMachine::TERMINATED])->get(['organization_id', 'family', 'product_key']) as $service) {
            $category = self::categoryFor((string) $service->family, (string) $service->product_key, $executors[$service->product_key] ?? null);
            if ($category !== null) {
                $owners[$category][$service->organization_id] = true;
            }
        }
        foreach (Domain::query()->pluck('organization_id') as $organizationId) {
            $owners['domain'][$organizationId] = true;
        }
        $categories = [];
        foreach ($config['categories'] as $key => $row) {
            [$cs, $en] = self::CATEGORIES[$key];
            $categories[] = ['key' => $key, 'default_label' => ['cs' => $cs, 'en' => $en], 'label' => $row['label'], 'enabled' => $row['enabled'], 'order' => $row['order'], 'offered' => $offered[$key], 'owners' => count($owners[$key])];
        }
        $links = [];
        foreach (self::LINKS as $key => [$cs, $en]) {
            $links[] = ['key' => $key, 'label' => ['cs' => $cs, 'en' => $en], 'enabled' => $config['links'][$key]];
        }

        return ['config' => $config, 'categories' => $categories, 'links' => $links];
    }
}
