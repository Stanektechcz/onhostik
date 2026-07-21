<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Notification catalogue (audit I125, I126, I132)
|--------------------------------------------------------------------------
|
| The single source of truth for what a user can be notified about.
|
| It previously lived as two hardcoded arrays inside AccountController, and
| they had already drifted from the code that reads them:
|
|   * `credit` was checked by CreditExpiryReminderNotification but was not in
|     the UI list — so it could never be switched off. The opt-out map is
|     built by diffing against the UI list, so a key absent from that list is
|     absent from the opt-out array, which reads as "wants it". A switch that
|     does not exist always says yes.
|   * `backup` was in the UI but no notification used it — a switch wired to
|     nothing.
|
| `mandatory` types cannot be switched off. They are the ones where silence is
| itself a harm: a login from an unrecognised IP, a service being suspended, a
| sign-in link. Marketing and digests are always optional.
|
*/

return [

    /*
     | Channels a user may tune per type. `slack` is operator-facing (I126) and
     | is deliberately not offered to customers — it is configured per webhook
     | in config/services.php, not per user.
     */
    'channels' => ['mail', 'database'],

    'types' => [

        // ── Účet a bezpečnost ────────────────────────────────────────────
        'security' => [
            'label'       => 'Změny zabezpečení',
            'description' => 'Zapnutí a vypnutí dvoufázového ověření, změny hesla.',
            'group'       => 'Účet a bezpečnost',
            // Always mailed. Someone turning OFF your 2FA is the single
            // clearest sign of a stolen account; that mail must always go out.
            'mandatory'   => true,
        ],
        'new_ip_login' => [
            'label'       => 'Přihlášení z neznámé adresy',
            'description' => 'Upozornění na přihlášení z IP adresy, ze které jste se dlouho nepřihlásili.',
            'group'       => 'Účet a bezpečnost',
            'mandatory'   => true,
            /*
             | Always in the bell, e-mail only if asked for (audit I125). It is
             | opt-in rather than opt-out because the alert is inherently a bit
             | noisy — a second home, a phone on mobile data and a VPN are all
             | "new addresses" — so defaulting it on would train people to
             | ignore exactly the mail they must not ignore.
             |
             | Kept separate from `security` for that reason: 2FA changes must
             | always mail, and folding both into one switch would force a
             | choice between mailing the noisy one or silencing the vital one.
             */
            'opt_in' => ['mail'],
        ],
        'account' => [
            'label'       => 'Účet',
            'description' => 'Přihlašovací odkazy a uvítací zprávy.',
            'group'       => 'Účet a bezpečnost',
            'mandatory'   => true,
        ],

        // ── Fakturace ────────────────────────────────────────────────────
        'invoice' => [
            'label'       => 'Faktury',
            'description' => 'Nově vystavené faktury, dobropisy a penále z prodlení.',
            'group'       => 'Fakturace',
            'mandatory'   => false,
        ],
        'payment' => [
            'label'       => 'Platby',
            'description' => 'Přijaté platby, blížící se splatnost a upomínky.',
            'group'       => 'Fakturace',
            'mandatory'   => false,
        ],
        'credit' => [
            'label'       => 'Kredit',
            'description' => 'Dobití kreditu a blížící se expirace.',
            'group'       => 'Fakturace',
            'mandatory'   => false,
        ],

        // ── Služby ───────────────────────────────────────────────────────
        'service' => [
            'label'       => 'Služby',
            'description' => 'Aktivace služby, dokončené objednávky a změny tarifu.',
            'group'       => 'Služby',
            'mandatory'   => false,
        ],
        'service_critical' => [
            'label'       => 'Kritické stavy služeb',
            'description' => 'Pozastavení, ukončení a selhání zřízení služby.',
            'group'       => 'Služby',
            'mandatory'   => true,
        ],
        'renewal' => [
            'label'       => 'Obnovy',
            'description' => 'Blížící se obnova služeb a domén, neúspěšné automatické platby.',
            'group'       => 'Služby',
            'mandatory'   => false,
        ],
        'monitor' => [
            'label'       => 'Monitoring',
            'description' => 'Výpadky dostupnosti a překročené prahové hodnoty.',
            'group'       => 'Služby',
            'mandatory'   => false,
        ],
        'backup' => [
            'label'       => 'Zálohy',
            'description' => 'Neúspěšné zálohy a překročení diskové kvóty.',
            'group'       => 'Služby',
            'mandatory'   => false,
        ],
        'maintenance' => [
            'label'       => 'Plánovaná údržba',
            'description' => 'Odstávky, které se dotknou vašich služeb.',
            'group'       => 'Služby',
            'mandatory'   => false,
        ],

        // ── Komunikace ───────────────────────────────────────────────────
        'support' => [
            'label'       => 'Podpora',
            'description' => 'Odpovědi na tickety a eskalace chatu.',
            'group'       => 'Komunikace',
            'mandatory'   => false,
        ],
        'digest' => [
            'label'       => 'Souhrnný přehled',
            'description' => 'Týdenní souhrn dění na účtu místo jednotlivých zpráv.',
            'group'       => 'Komunikace',
            'mandatory'   => false,
        ],
        'marketing' => [
            'label'       => 'Novinky a průzkumy',
            'description' => 'Dotazníky spokojenosti, nabídky a oznámení o novinkách.',
            'group'       => 'Komunikace',
            'mandatory'   => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Critical incident routing (I126)
    |--------------------------------------------------------------------------
    |
    | Operator-facing alerting for incidents that must not wait for someone to
    | read email. Both are off unless a destination is configured — an alerting
    | path that silently does nothing is worse than none, because it is trusted.
    |
    | Never commit the webhook URL: it is a credential.
    */
    'critical' => [
        'slack_webhook' => env('CRITICAL_ALERT_SLACK_WEBHOOK'),
        'sms_to'        => env('CRITICAL_ALERT_SMS_TO'),
    ],

];
