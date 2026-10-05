<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\Web\SurfaceDataController;
use App\Http\Navigation\NavItem;
use App\Http\Navigation\StaffNavigation;
use Illuminate\Console\Command;
use Onhost\Domain\Catalog\PanelNavigation;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RoleResolver;

/**
 * Generates `docs/manual-tests/09-role.md` (Czech) from the code, so the per-role manual test sheet can never drift from
 * what the roles really hold: the staff sidebar comes from StaffNavigation + the role definitions (RoleResolver), the customer sidebar from
 * SurfaceDataController::NAV_REQUIRES + PanelNavigation, and the allowed/refused actions from the permission each action asks.
 * `--check` writes nothing and exits 1 when the committed file differs (the CI runs it next to `onhost:openapi --check`).
 */
final class GenerateRoleMatrix extends Command
{
    protected $signature = 'onhost:docs:roles {--out=docs/manual-tests/09-role.md} {--check : Write nothing; exit 1 when the file differs from what the code generates}';

    protected $description = 'Write the per-role manual test sheet (docs/manual-tests/09-role.md) from the role definitions, StaffNavigation and PanelNavigation';

    /** How many allowed and refused actions one role's checklist lists at most. */
    private const SAMPLE = 5;

    private const STAFF_SECTIONS = [
        'overview' => 'Přehled', 'support' => 'Podpora', 'customers' => 'Zákazníci', 'operations' => 'Provoz', 'infrastructure' => 'Infrastruktura',
        'commerce' => 'Obchod', 'finance' => 'Finance', 'product' => 'Produkt', 'security' => 'Bezpečnost a compliance', 'iam' => 'Identita a schvalování',
    ];

    /** Fixed areas of the customer sidebar (SurfaceDataController::NAV_AREAS); the optional links are labelled by PanelNavigation::LINKS. */
    private const CUSTOMER_AREAS = [
        'overview' => 'Přehled', 'services' => 'Služby', 'settings' => 'Nastavení účtu', 'order' => 'Objednat', 'tickets' => 'Tikety',
        'billing' => 'Fakturace', 'topup' => 'Dobití peněženky', 'domains' => 'Domény', 'calendar' => 'Kalendář',
    ];

    /**
     * Staff actions of the manual test: label, request, the permission it asks. Refused with 403 for staff without it.
     *
     * @var list<array{0:string,1:string,2:string}>
     */
    private const STAFF_ACTIONS = [
        ['Otevřít frontu tiketů', 'GET /v1/staff/tickets', 'staff.support.ticket.read'],
        ['Odpovědět na tiket zákazníka', 'POST /v1/staff/tickets/{ticket}/messages', 'support.ticket.manage'],
        ['Přiřadit tiket kolegovi', 'POST /v1/staff/tickets/{ticket}/assign', 'support.ticket.assign'],
        ['Upravit fronty, makra a SLA politiky podpory', 'POST /v1/staff/support/queues', 'support.queue.manage'],
        ['Otevřít přehled zákazníků (Customer 360)', 'GET /v1/staff/customers', 'staff.customer.read'],
        ['Přejít objednávku do dalšího stavu', 'POST /v1/staff/orders/{order}/transition', 'staff.order.manage'],
        ['Pozastavit nebo obnovit zákaznickou službu', 'POST /v1/staff/services/{service}/actions', 'staff.service.manage'],
        ['Otevřít serverovou konzoli zákaznické služby', 'GET /sprava/konzole/{service}', 'staff.console'],
        ['Zopakovat operaci z fronty provisioningu', 'POST /v1/staff/provisioning/jobs/{operation}/retry', 'provisioning.operation.retry'],
        ['Zobrazit přehled infrastruktury', 'GET /v1/staff/provisioning/board', 'provisioning.operation.read'],
        ['Zobrazit incidenty a pohotovost', 'GET /v1/staff/incidents', 'incident.manage'],
        ['Připsat kredit zákazníkovi', 'POST /v1/staff/customers/{organization}/wallet/credit', 'billing.credit.adjust'],
        ['Spustit upomínky', 'POST /v1/staff/dunning/run', 'billing.dunning.manage'],
        ['Zobrazit bankovní platby k párování', 'GET /v1/staff/payments/bank', 'billing.reconcile'],
        ['Vrátit platbu objednávky na kartu nebo účet při odstoupení (G6)', 'POST /v1/staff/payments/{payment}/refund', 'billing.refund.execute'],
        ['Potvrdit odeslání bankovní vratky (G6)', 'POST /v1/staff/payments/refunds/{refund}/confirm', 'billing.refund.execute'],
        ['Přečíst reporty (MRR, churn)', 'GET /v1/staff/reports/mrr', 'report.read'],
        ['Zobrazit ceník a verze tarifů', 'GET /v1/staff/pricing', 'catalog.manage'],
        ['Upravit šablony zpráv', 'GET /v1/staff/templates', 'notification.template.manage'],
        ['Upravit obsah webu (příspěvky, changelog)', 'PUT /v1/staff/content/posts', 'content.manage'],
        ['Spravovat partnery a výplaty', 'GET /v1/staff/partners', 'partner.manage'],
        ['Rozhodnout čtyři oči (schválit žádost)', 'POST /v1/staff/approvals/{approval}/decision', 'iam.approval.decide'],
        ['Resetovat MFA jiného uživatele', 'POST /v1/staff/users/{user}/mfa-reset', 'iam.mfa.reset'],
        ['Vést bezpečnostní incident', 'GET /v1/staff/security/incidents', 'security.incident.manage'],
        ['Zpracovat případ zneužití (DSA)', 'GET /v1/staff/abuse-cases', 'abuse.case.manage'],
        ['Nastavit heslo účtu herního panelu zákazníka (jen vlastník organizace)', 'POST /v1/services/{service}/actions (akce panel.password)', 'service.panel_account.manage'],
    ];

    /**
     * Customer actions of the manual test, in the organization scope. Refused with 403 for a member without the permission,
     * with 404 for somebody who is not a member of the organization at all.
     *
     * @var list<array{0:string,1:string,2:string}>
     */
    private const CUSTOMER_ACTIONS = [
        ['Zobrazit seznam služeb', 'GET /v1/services', 'service.read'],
        ['Zobrazit domény a DNS zóny', 'GET /v1/domains', 'domain.read'],
        ['Zobrazit faktury', 'GET /v1/invoices', 'billing.invoice.read'],
        ['Zobrazit zůstatek peněženky', 'GET /v1/wallet', 'billing.wallet.read'],
        ['Zobrazit tikety organizace', 'GET /v1/tickets', 'support.ticket.read'],
        ['Otevřít tiket a odpovídat', 'POST /v1/tickets', 'support.ticket.write'],
        ['Použít AI asistenta nebo živý chat', 'POST /v1/assistant/chat', 'support.chat.use'],
        ['Objednat službu', 'POST /v1/orders', 'catalog.order.create'],
        ['Dobít peněženku', 'POST /v1/wallet/topup', 'billing.wallet.topup'],
        ['Zaplatit z kreditu organizace', 'POST /v1/orders (platba z kreditu)', 'billing.wallet.spend'],
        ['Spravovat platební metody', 'DELETE /v1/payment-methods/{method}', 'billing.payment_method.manage'],
        ['Restartovat službu', 'POST /v1/services/{service}/actions', 'service.operate'],
        ['Měnit nastavení služby (PHP, cron, databáze)', 'POST /v1/services/{service}/actions', 'service.manage'],
        ['Otevřít konzoli nebo shell služby', 'POST /v1/services/{service}/actions', 'service.console'],
        ['Obnovit službu ze zálohy', 'POST /v1/services/{service}/restore', 'backup.restore'],
        ['Stáhnout zálohu', 'GET /v1/services/{service}/backups/{backup}/download', 'backup.download'],
        ['Upravit DNS záznam', 'POST /v1/dns/zones/{zone}/changes', 'dns.zone.write'],
        ['Spravovat doménu (obnova, kontakty)', 'POST /v1/domains/{domain}/renew', 'domain.manage'],
        ['Spravovat poštovní schránky', 'POST /v1/services/{service}/actions', 'mail.manage'],
        ['Vytvořit API klíč', 'POST /v1/tokens', 'api_token.manage'],
        ['Pozvat člena do organizace', 'POST /v1/organizations/{organization}/invitations', 'organization.members.manage'],
        ['Přečíst audit organizace', 'GET /v1/organizations/{organization}/audit', 'audit.read'],
        ['Zobrazit partnerský portál', 'GET /v1/partner/overview', 'partner.portal.read'],
        ['Předat vlastnictví organizace (uzavření a předání jsou jen vlastníka)', 'POST /v1/organizations/{organization}/ownership-transfer', 'organization.close'],
        ['Nastavit výplatní účet partnera (jen vlastník)', 'PUT /v1/partner/payout-account', 'partner.payout_account.manage'],
    ];

    /**
     * What one shared-service capability (svc_*) lets a guest do on that one service: label of the check.
     *
     * @var array<string, string>
     */
    private const RESOURCE_CHECKS = [
        'svc_view' => 'Otevřít detail sdílené služby, metriky a seznam záloh; restart je odmítnut (403)',
        'svc_operate' => 'Restartovat službu, změnit verzi PHP, smazat cache; nahrát soubor nebo upravit cron je odmítnuto (403)',
        'svc_manage' => 'Měnit nastavení, databáze, cron a soubory služby; otevřít konzoli je odmítnuto (403)',
        'svc_console' => 'Otevřít konzoli, nastavit SSH klíče; smazat zálohu je odmítnuto (403)',
        'svc_data_delete' => 'Smazat web, databázi nebo schránku uvnitř služby; restart je odmítnut (403)',
        'svc_backups' => 'Stáhnout zálohu sdílené služby; obnova ze zálohy je odmítnuta (403)',
        'svc_restore' => 'Obnovit službu ze zálohy; stažení zálohy je odmítnuto (403)',
        'svc_assistant' => 'Použít AI asistenta pro sdílenou službu; restart je odmítnut (403)',
    ];

    public function handle(): int
    {
        $generated = $this->render();
        $path = base_path((string) $this->option('out'));

        if ($this->option('check')) {
            $current = is_file($path) ? str_replace("\r\n", "\n", (string) file_get_contents($path)) : null;
            if ($current !== $generated) {
                $this->error((string) $this->option('out').' differs from what the code generates; run: php artisan onhost:docs:roles');

                return self::FAILURE;
            }
            $this->info((string) $this->option('out').' is up to date.');

            return self::SUCCESS;
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, $generated);
        $this->info('Wrote '.$this->option('out').' ('.strlen($generated).' bytes).');

        return self::SUCCESS;
    }

    /** The whole document, deterministic (no dates, no database): the test compares it with the committed file. */
    public function render(): string
    {
        $roles = RoleResolver::definitions();
        $staff = array_filter($roles, fn (array $r) => $r['staff']);
        $customer = array_filter($roles, fn (array $r) => ! $r['staff'] && $r['scope'] !== 'resource');
        $resource = array_filter($roles, fn (array $r) => $r['scope'] === 'resource');

        $out = [
            '# 09 Ruční testy podle rolí',
            '',
            '> Tento soubor je **generovaný z kódu** příkazem `php artisan onhost:docs:roles` (zdroje: `RoleCatalog`, `StaffNavigation`, `PanelNavigation`, `SurfaceDataController::NAV_REQUIRES`). Ručně se needituje; CI hlídá shodu příkazem `php artisan onhost:docs:roles --check`.',
            '',
            'Automatický protějšek bez prohlížeče (sdílení služby a role): `tests/Feature/E2E/SharingFlowTest.php`.',
            '',
            '## Jak testovat',
            '',
            '1. Přihlaste se jako uživatel dané role. Demo účty a jejich hesla si nastavuje vlastník platformy sám, tady žádná hesla nejsou.',
            '2. Porovnejte postranní menu s očekávaným seznamem u role.',
            '3. Provedte povolené akce, každá musí uspět (u rizika HIGH po čerstvém ověření step-up, u CRITICAL se čtyřma očima).',
            '4. Provedte odmítnuté akce, každá musí skončit uvedeným stavem. Člen organizace bez oprávnění dostane **403**, cizí uživatel mimo organizaci **404**.',
            '5. Testujte jen na testovacím nebo lokálním prostředí, nikdy zápisem proti živým panelům.',
            '',
            '## Obsah',
            '',
            '- [Role zaměstnanců (staff)](#role-zaměstnanců-staff)',
            '- [Role zákazníka v organizaci](#role-zákazníka-v-organizaci)',
            '- [Přístup ke sdílené službě (svc_*)](#přístup-ke-sdílené-službě-svc_)',
            '',
            '## Role zaměstnanců (staff)',
            '',
            'Postranní menu konzole vzniká z `StaffNavigation`: položka se zobrazí, když role drží její oprávnění v globálním rozsahu. Položka „Schvalování“ je vidět všem zaměstnancům.',
            '',
            '| Role (klíč) | Název | Počet oprávnění | Položek menu |',
            '|---|---|---|---|',
        ];
        foreach ($staff as $key => $role) {
            $out[] = '| `'.$key.'` | '.$this->cell($role['name']).' | '.count($role['permissions']).' | '.count($this->staffItems($role['permissions'])).' |';
        }
        $out[] = '';
        foreach ($staff as $key => $role) {
            array_push($out, ...$this->staffRole($key, $role));
        }

        array_push($out,
            '## Role zákazníka v organizaci',
            '',
            'Postranní menu klientského panelu vzniká z `PanelNavigation` (kategorie služeb a volitelné odkazy, které personál zapíná v nastavení `panel.nav`) a z `SurfaceDataController::NAV_REQUIRES` (oprávnění, která potřebují koncové body daného pohledu). Odkaz, který by člen otevřel jen do odmítnutí, se nenabízí.',
            '',
            '| Role (klíč) | Název | Počet oprávnění | Oblastí menu |',
            '|---|---|---|---|',
        );
        foreach ($customer as $key => $role) {
            $out[] = '| `'.$key.'` | '.$this->cell($role['name']).' | '.count($role['permissions']).' | '.count($this->customerAreas($role['permissions'])).' |';
        }
        $out[] = '';
        foreach ($customer as $key => $role) {
            array_push($out, ...$this->customerRole($key, $role));
        }

        array_push($out,
            '## Přístup ke sdílené službě (svc_*)',
            '',
            'Schopnosti na jedné službě (`scope: resource`) se nepřidělují jako role organizace; dává je pozvánka ke sdílení služby (`ServiceAccessService`). Host (`guest`) nevidí nic z organizace, jen sdílené služby. Cizí služba, která mu nebyla sdílena, vrací **404**; sdílená služba s chybějící schopností **403**.',
            '',
        );
        foreach ($resource as $key => $role) {
            array_push($out, ...$this->resourceRole($key, $role));
        }

        return rtrim(implode("\n", $out))."\n";
    }

    /**
     * @param  array{name:string, description:string, scope:string, staff:bool, permissions:list<string>}  $role
     * @return list<string>
     */
    private function staffRole(string $key, array $role): array
    {
        $items = $this->staffItems($role['permissions']);
        $lines = ['### `'.$key.'` '.$this->cell($role['name']), '', $this->cell($role['description']).'.', '', '**Očekávané menu konzole** (položek: '.count($items).'):', ''];
        if ($items === []) {
            $lines[] = '_Žádná položka kromě „Schvalování“ (ta je vidět všem zaměstnancům)._';
        }
        $current = null;
        foreach ($items as $item) {
            if ($item->section !== $current) {
                $current = $item->section;
                $lines[] = '- **'.self::STAFF_SECTIONS[$current].'**: '.implode(', ', array_map(fn (NavItem $i) => $i->label['cs'], array_values(array_filter($items, fn (NavItem $i) => $i->section === $current))));
            }
        }
        $lines[] = '';
        $lines[] = '**Oprávnění** ('.count($role['permissions']).'): '.$this->permissionList($role['permissions']);
        $lines[] = '';
        array_push($lines, ...$this->checklist(self::STAFF_ACTIONS, $role['permissions'], '403', 'Člen zaměstnanců bez oprávnění'));

        return $lines;
    }

    /**
     * @param  array{name:string, description:string, scope:string, staff:bool, permissions:list<string>}  $role
     * @return list<string>
     */
    private function customerRole(string $key, array $role): array
    {
        $areas = $this->customerAreas($role['permissions']);
        $lines = ['### `'.$key.'` '.$this->cell($role['name']), '', $this->cell($role['description']).'.', ''];
        if ($key === 'guest') {
            $lines[] = '**Očekávané menu panelu:** jen přehled, služby sdílené s hostem a nastavení vlastního účtu. Žádné faktury, tým, domény ani objednávky organizace.';
        } else {
            $lines[] = '**Očekávané menu panelu** (oblastí: '.count($areas).'): '.implode(', ', $areas).'.';
        }
        $lines[] = '';
        $lines[] = '**Kategorie služeb v menu:** '.implode(', ', array_map(fn (array $c) => $c[0], array_values(PanelNavigation::CATEGORIES))).' (zobrazí se ty, které personál zapnul a katalog prodává, a vždy ty, kde organizace už službu má; '.($this->holds($role['permissions'], 'service.read') ? 'role vidí všechny služby organizace' : 'role vidí jen služby sdílené s ní nebo z projektů, kde má roli').').';
        $lines[] = '';
        $lines[] = '**Oprávnění** ('.count($role['permissions']).'): '.($role['permissions'] === [] ? '_žádné na úrovni organizace; co smí, dávají sdílení služeb (svc_*)_' : $this->permissionList($role['permissions']));
        $lines[] = '';
        array_push($lines, ...$this->checklist(self::CUSTOMER_ACTIONS, $role['permissions'], '403', 'Člen organizace bez oprávnění'));
        $lines[] = '- Cizí uživatel mimo organizaci otevře `GET /v1/organizations/{organization}` a dostane **404**; kdo organizaci nezná, nesmí poznat, že existuje.';
        $lines[] = '';

        return $lines;
    }

    /**
     * @param  array{name:string, description:string, scope:string, staff:bool, permissions:list<string>}  $role
     * @return list<string>
     */
    private function resourceRole(string $key, array $role): array
    {
        return [
            '### `'.$key.'` '.$this->cell($role['name']),
            '',
            $this->cell($role['description']).'.',
            '',
            '**Oprávnění** ('.count($role['permissions']).'): '.$this->permissionList($role['permissions']),
            '',
            '- [ ] Přihlaste se jako host, kterému byla sdílena jedna služba s touto schopností.',
            '- [ ] '.(self::RESOURCE_CHECKS[$key] ?? 'Ověřte, že schopnost dělá jen to, co popisuje její oprávnění.'),
            '- [ ] Otevřete jinou službu téže organizace, která hostu sdílena nebyla: **404**.',
            '- [ ] Otevřete faktury nebo tým organizace: **403**.',
            '',
        ];
    }

    /**
     * @param  list<array{0:string,1:string,2:string}>  $actions
     * @param  list<string>  $held
     * @return list<string>
     */
    private function checklist(array $actions, array $held, string $refusal, string $who): array
    {
        $allowed = array_values(array_filter($actions, fn (array $a) => $this->holds($held, $a[2])));
        $refused = array_values(array_filter($actions, fn (array $a) => ! $this->holds($held, $a[2])));
        $lines = ['**Kontrolní seznam**', '', 'Povolené akce (musí uspět):', ''];
        foreach (array_slice($allowed, 0, self::SAMPLE) as [$label, $request, $permission]) {
            $lines[] = '- [ ] '.$label.' (`'.$request.'`, `'.$permission.'`'.$this->riskNote($permission).'): úspěch.';
        }
        if ($allowed === []) {
            $lines[] = '- _Žádná z akcí tohoto seznamu; role nic z nich nesmí._';
        }
        $lines[] = '';
        $lines[] = 'Odmítnuté akce (musí skončit chybou):';
        $lines[] = '';
        foreach (array_slice($refused, 0, self::SAMPLE) as [$label, $request, $permission]) {
            $lines[] = '- [ ] '.$label.' (`'.$request.'`, chybí `'.$permission.'`): **'.$refusal.'** ('.$who.').';
        }
        if ($refused === []) {
            $lines[] = '- _Role smí vše z tohoto seznamu._';
        }
        $lines[] = '';

        return $lines;
    }

    /** @param list<string> $permissions @return list<NavItem> */
    private function staffItems(array $permissions): array
    {
        return array_values(array_filter(StaffNavigation::sorted(), fn (NavItem $item) => StaffNavigation::visibleWith($item, $permissions)));
    }

    /**
     * Sidebar areas of the customer panel the role is offered, in the panel's order (fixed areas, then the optional links).
     *
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function customerAreas(array $permissions): array
    {
        $areas = [];
        foreach (self::CUSTOMER_AREAS as $key => $label) {
            if ($this->holdsAll($permissions, SurfaceDataController::NAV_REQUIRES[$key])) {
                $areas[] = $label;
            }
        }
        foreach (PanelNavigation::LINKS as $key => [$cs]) {
            if ($this->holdsAll($permissions, SurfaceDataController::NAV_REQUIRES[$key])) {
                $areas[] = $cs;
            }
        }

        return $areas;
    }

    /** @param list<string> $held @param list<string> $needed */
    private function holdsAll(array $held, array $needed): bool
    {
        return array_diff($needed, $held) === [];
    }

    /** @param list<string> $held */
    private function holds(array $held, string $permission): bool
    {
        return in_array($permission, $held, true);
    }

    private function riskNote(string $permission): string
    {
        $risk = PermissionCatalog::all()[$permission]['risk'] ?? PermissionCatalog::NORMAL;

        return match ($risk) {
            PermissionCatalog::HIGH => ', riziko HIGH: čerstvý step-up',
            PermissionCatalog::CRITICAL => ', riziko CRITICAL: step-up a čtyři oči',
            default => '',
        };
    }

    /** @param list<string> $permissions */
    private function permissionList(array $permissions): string
    {
        $sorted = $permissions;
        sort($sorted);

        return $sorted === [] ? '_žádná_' : implode(', ', array_map(fn (string $p) => '`'.$p.'`', $sorted));
    }

    private function cell(string $text): string
    {
        return str_replace(['|', "\n"], ['\\|', ' '], rtrim(trim($text), '.'));
    }
}
