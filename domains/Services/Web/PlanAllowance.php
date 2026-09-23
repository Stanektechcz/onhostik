<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Errors\ProviderException;

/**
 * The counted numbers of a web hosting plan, read across every site the plan carries.
 *
 * A plan that sells „10 webů a 2 databáze“ sells two databases: they belong to the plan, not to each of its sites.
 * Every carried site is a service of its own (`ServiceSites`) and was given a copy of the plan's numbers, while the
 * limit was counted at one site's panel against that site's copy — so a plan with ten sites handed out ten times the
 * databases, mailboxes, FTP accounts, cron jobs and subdomains the customer paid for, each of them real space and
 * real load on a shared node.
 *
 * What stays with the single site is what is the site's own: its share of the plan's space (`ServiceSites::share`),
 * the one site it is, and its shell user — an ISPConfig shell account belongs to a site, and counting those across
 * the plan would leave nine of ten sites without SSH.
 */
final class PlanAllowance
{
    /** The numbers that belong to the plan as a whole, as `feature => the listing they are counted in`. */
    public const GROUP_WIDE = ['databases' => 'databases', 'mailboxes' => 'mailboxes', 'ftp' => 'ftp', 'cron' => 'cron', 'subdomains' => 'subdomains'];

    /** What each of them is called when the customer is told there are none left (plural, so the sentence agrees). */
    private const NOUNS = ['databases' => 'Databáze', 'mailboxes' => 'E-mailové schránky', 'ftp' => 'FTP účty', 'cron' => 'Naplánované úlohy', 'subdomains' => 'Subdomény'];

    public function __construct(private readonly ServiceFeatures $features) {}

    /** Whether this number is the plan's and the plan really carries more than the one site. */
    public static function shared(Service $service, string $feature): bool
    {
        return array_key_exists($feature, self::GROUP_WIDE) && ServiceSites::of(ServiceSites::ownerOf($service))->count() > 1;
    }

    /** The plan's number for a feature: always the paying service's, never the copy a carried site holds. */
    public function limit(Service $service, string $feature): ?int
    {
        $limit = $this->features->features(ServiceSites::ownerOf($service))[$feature]['limit'] ?? null;

        return is_numeric($limit) && (int) $limit > 0 ? (int) $limit : null;
    }

    /**
     * How many of a listing every site of the plan holds together. The site the request is about is read afresh; the
     * others are read from what the platform last saw, which their own writes clear (`ServiceFeatures::forget`). A
     * panel that cannot answer for the site being changed — or that asks to be tried again — stops the count, so the
     * caller can defer the limit to the step that runs when the panel is back (H02) instead of exceeding the plan
     * quietly. A site that does not offer the listing at all holds none of them.
     */
    public function count(Service $acting, string $kind): int
    {
        $total = 0;
        foreach (ServiceSites::of(ServiceSites::ownerOf($acting)) as $site) {
            try {
                $total += count($this->features->resources($site, $kind, $site->id === $acting->id));
            } catch (ProviderException $e) {
                if ($site->id === $acting->id || $e->errorCode->isRetryable()) {
                    throw $e;
                }
            } catch (DomainError) {
                // the listing is not offered for that site (it is still being provisioned, or its plan has none)
            }
        }

        return $total;
    }

    /** Why the request is refused, in words that say where the number went: to the other sites of the same plan. */
    public static function message(string $feature, int $limit, int $used): string
    {
        return (self::NOUNS[$feature] ?? 'Tyto položky').' jsou součástí tarifu, ne jednotlivého webu: tarif jich nabízí '.$limit.' a weby tarifu jich už mají '.$used.'. Uvolněte některou z nich, nebo zvolte vyšší tarif.';
    }
}
