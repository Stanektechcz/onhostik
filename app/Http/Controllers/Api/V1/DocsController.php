<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DocsController extends Controller
{
    public function spec(): JsonResponse
    {
        return response()->json($this->buildSpec());
    }

    public function ui(\Illuminate\Http\Request $request): Response
    {
        $specUrl = url('/api/openapi.json');
        $nonce   = $request->attributes->get('csp_nonce', '');

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="cs">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>OnHost API — Dokumentace</title>
            <!--
                Audit C25: pinned to an exact version, not a floating @5 tag.
                A floating tag lets the CDN serve different bytes on any request
                (a supply-chain foothold on the docs page); an exact version is
                immutable on unpkg. crossorigin=anonymous is the prerequisite
                for adding a Subresource-Integrity hash once the version is
                locked for a release.
            -->
            <link rel="stylesheet" crossorigin="anonymous"
                  href="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui.css">
            <style nonce="{$nonce}">
                body { margin: 0; }
                .swagger-ui .topbar { display: none; }
            </style>
        </head>
        <body>
            <div id="swagger-ui"></div>
            <script crossorigin="anonymous"
                    src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
            <script nonce="{$nonce}">
                SwaggerUIBundle({
                    url: "{$specUrl}",
                    dom_id: '#swagger-ui',
                    presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
                    layout: 'BaseLayout',
                    deepLinking: true,
                });
            </script>
        </body>
        </html>
        HTML;

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /** @return array<string, mixed> */
    private function buildSpec(): array
    {
        $base = url('/api');

        return [
            'openapi' => '3.1.0',
            'info'    => [
                'title'       => 'OnHost API',
                'description' => "REST API pro klientský portál OnHost. Autentizace pomocí Personal Access Tokenů (Bearer).\n\n" .
                    "Token lze vytvořit na `/panel/ucet/api-tokeny`. Každý token má přiřazená **oprávnění (abilities)**:\n\n" .
                    "| Ability | Popis |\n|---|---|\n" .
                    "| `read` | Přístup ke čtení — vždy povinné |\n" .
                    "| `write:tickets` | Vytvořit, odpovědět, uzavřít ticket |\n" .
                    "| `write:credit` | Dobíjení kreditu |\n" .
                    "| `write:orders` | Vytvořit nové objednávky |\n" .
                    "| `manage:tokens` | Správa vlastních API tokenů |\n\n" .
                    "Endpointy označené `🔒` vyžadují příslušnou ability. Bez ní vrátí `403 Forbidden`.\n\n" .
                    "## Opakování požadavků (idempotence)\n\n" .
                    "Zápisové operace přijímají hlavičku `Idempotency-Key`. Pokud vám spadne spojení a nevíte, " .
                    "jestli požadavek prošel, zopakujte ho **se stejným klíčem** — server vrátí původní odpověď " .
                    "a akci neprovede podruhé (odpověď pak nese `Idempotent-Replay: true`).\n\n" .
                    "Stejný klíč s **jiným tělem** vrátí `422` — to je chyba na straně klienta a tiché přehrání " .
                    "by ji zamaskovalo. Klíč rozpracovaného požadavku vrátí `409`. Neúspěšné požadavky se neukládají, " .
                    "takže po opravě lze stejný klíč použít znovu.\n\n" .
                    "## Limity\n\n" .
                    "Limit je **per token**, ne per účet — jedna splašená integrace tedy nevyhladoví ostatní. " .
                    "Zbývající rozpočet najdete v hlavičkách `X-RateLimit-Limit` a `X-RateLimit-Remaining`; " .
                    "při vyčerpání přijde `429`.",
                'version'     => '1.1.0',
                'contact'     => ['email' => 'api@onhost.cz'],
            ],
            'servers' => [
                // A single /api base with versioned path keys. Two version-prefixed
                // servers would make every path resolve under BOTH of them, which
                // is not what is deployed.
                ['url' => $base, 'description' => 'OnHost API'],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type'   => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'PAT',
                        'description'  => 'Personal Access Token — vytvořte na /panel/ucet/api-tokeny',
                    ],
                ],
                'parameters' => [
                    'IdempotencyKey' => [
                        'name'        => 'Idempotency-Key',
                        'in'          => 'header',
                        'required'    => false,
                        'schema'      => ['type' => 'string', 'maxLength' => 128],
                        'description' => 'Volitelný klíč pro bezpečné opakování zápisu po výpadku spojení.',
                    ],
                ],
                'headers' => [
                    'X-RateLimit-Limit' => [
                        'schema'      => ['type' => 'integer'],
                        'description' => 'Počet požadavků povolených tomuto tokenu za minutu.',
                    ],
                    'X-RateLimit-Remaining' => [
                        'schema'      => ['type' => 'integer'],
                        'description' => 'Kolik požadavků v aktuálním okně ještě zbývá.',
                    ],
                ],
                'schemas' => $this->schemas(),
            ],
            'security' => [['bearerAuth' => []]],
            'paths'    => $this->paths(),
            'tags'     => [
                ['name' => 'Profil',    'description' => 'Informace o přihlášeném uživateli'],
                ['name' => 'Služby',    'description' => 'Hosting služby zákazníka'],
                ['name' => 'Faktury',   'description' => 'Fakturace'],
                ['name' => 'Domény',    'description' => 'Správa domén'],
                ['name' => 'Kredit',    'description' => 'Kreditní zůstatek a dobíjení'],
                ['name' => 'Podpora',   'description' => 'Helpdesk — tickety'],
                ['name' => 'Monitoring','description' => 'Monitory dostupnosti služeb'],
                ['name' => 'Tokeny',    'description' => 'Správa API tokenů'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function schemas(): array
    {
        return [
            'Error' => [
                'type' => 'object',
                'properties' => [
                    'error' => ['type' => 'string', 'example' => 'Validation failed.'],
                    'errors' => ['type' => 'object'],
                ],
            ],
            'Profile' => [
                'type' => 'object',
                'properties' => [
                    'id'       => ['type' => 'integer'],
                    'name'     => ['type' => 'string'],
                    'email'    => ['type' => 'string', 'format' => 'email'],
                    'locale'   => ['type' => 'string', 'example' => 'cs'],
                    'customer' => [
                        'type' => 'object',
                        'nullable' => true,
                        'properties' => [
                            'type'               => ['type' => 'string', 'enum' => ['individual', 'company']],
                            'company_name'       => ['type' => 'string', 'nullable' => true],
                            'preferred_currency' => ['type' => 'string', 'example' => 'CZK'],
                            'country_code'       => ['type' => 'string', 'example' => 'CZ'],
                        ],
                    ],
                ],
            ],
            'Service' => [
                'type' => 'object',
                'properties' => [
                    'id'           => ['type' => 'integer'],
                    'label'        => ['type' => 'string'],
                    'status'       => ['type' => 'string', 'enum' => ['active', 'suspended', 'terminated']],
                    'next_due_date' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                ],
            ],
            'Invoice' => [
                'type' => 'object',
                'properties' => [
                    'id'              => ['type' => 'integer'],
                    'number'          => ['type' => 'string'],
                    'status'          => ['type' => 'string'],
                    'total_amount'    => ['type' => 'integer', 'description' => 'Minor units'],
                    'total_currency'  => ['type' => 'string'],
                    'due_date'        => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                    'issued_at'       => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'SupportTicket' => [
                'type' => 'object',
                'properties' => [
                    'id'         => ['type' => 'integer'],
                    'subject'    => ['type' => 'string'],
                    'status'     => ['type' => 'string', 'enum' => ['open', 'answered', 'closed']],
                    'priority'   => ['type' => 'string', 'enum' => ['low', 'normal', 'high', 'urgent']],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'CreditBalance' => [
                'type' => 'object',
                'properties' => [
                    'amount'    => ['type' => 'integer', 'description' => 'Minor units (haléře/centy)'],
                    'currency'  => ['type' => 'string', 'example' => 'CZK'],
                    'formatted' => ['type' => 'string', 'example' => '1 000,00 Kč'],
                ],
            ],
            'Monitor' => [
                'type' => 'object',
                'properties' => [
                    'id'             => ['type' => 'integer'],
                    'name'           => ['type' => 'string'],
                    'type'           => ['type' => 'string'],
                    'target'         => ['type' => 'string'],
                    'status'         => ['type' => 'string', 'enum' => ['up', 'down', 'unknown', 'paused']],
                    'uptime_percent' => ['type' => 'number', 'nullable' => true],
                    'ssl_expires_at' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                    'last_check_at'  => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function paths(): array
    {
        $auth = [['bearerAuth' => []]];

        return [
            '/up' => [
                'get' => [
                    'tags'        => ['Provoz'],
                    'summary'     => 'Health check',
                    'description' => 'Stav aplikace a databáze. Bez autentizace — health probe, která potřebuje '
                        . 'credentials, je jen další věc, co se rozbije ve tři ráno. Vrací **503**, pokud aplikace '
                        . 'běží, ale nevidí databázi: takový proces patří z rotace ven, ne mezi zdravé.',
                    'operationId' => 'health',
                    'security'    => [],
                    'responses'   => [
                        '200' => [
                            'description' => 'Vše v pořádku',
                            'content'     => ['application/json' => ['schema' => [
                                'type'       => 'object',
                                'properties' => [
                                    'status' => ['type' => 'string', 'example' => 'ok'],
                                    'checks' => ['type' => 'object'],
                                    'time'   => ['type' => 'string', 'format' => 'date-time'],
                                ],
                            ]]],
                        ],
                        '503' => ['description' => 'Databáze nedostupná — vyřadit z rotace'],
                    ],
                ],
            ],

            '/ready' => [
                'get' => [
                    'tags'        => ['Provoz'],
                    'summary'     => 'Readiness probe',
                    'description' => 'Hlubší kontrola než /up (liveness): ověří databázi, cache, frontu a úložiště '
                        . 'reálným round-tripem. Bez autentizace, neúnikuje žádné detaily. **503**, dokud aplikace '
                        . 'není připravena obsluhovat provoz — vhodné pro k8s readiness / load balancer.',
                    'operationId' => 'readiness',
                    'security'    => [],
                    'responses'   => [
                        '200' => [
                            'description' => 'Připraveno',
                            'content'     => ['application/json' => ['schema' => [
                                'type'       => 'object',
                                'properties' => [
                                    'status' => ['type' => 'string', 'example' => 'ready'],
                                    'checks' => ['type' => 'object'],
                                    'time'   => ['type' => 'string', 'format' => 'date-time'],
                                ],
                            ]]],
                        ],
                        '503' => ['description' => 'Některá závislost nedostupná — ještě neposílat provoz'],
                    ],
                ],
            ],

            '/v2/services' => [
                'get' => [
                    'tags'        => ['Služby'],
                    'summary'     => 'Služby včetně dat monitoringu (v2)',
                    'description' => 'Jako v1, ale s vloženými daty monitoru a vyšším výchozím limitem.',
                    'operationId' => 'listServicesV2',
                    'security'    => $auth,
                    'responses'   => [
                        '200' => ['description' => 'OK'],
                        '429' => ['description' => 'Vyčerpaný limit tokenu'],
                    ],
                ],
            ],

            '/v2/services/{id}' => [
                'get' => [
                    'tags'        => ['Služby'],
                    'summary'     => 'Detail služby včetně monitoringu (v2)',
                    'operationId' => 'getServiceV2',
                    'security'    => $auth,
                    'parameters'  => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses'   => [
                        '200' => ['description' => 'OK'],
                        '404' => ['description' => 'Služba nenalezena, nebo nepatří tomuto zákazníkovi'],
                    ],
                ],
            ],

            '/v2/webhooks' => [
                'get' => [
                    'tags'        => ['Webhooky'],
                    'summary'     => 'Seznam odběrů webhooků',
                    'operationId' => 'listWebhooks',
                    'security'    => $auth,
                    'responses'   => ['200' => ['description' => 'OK']],
                ],
                'post' => [
                    'tags'        => ['Webhooky'],
                    'summary'     => 'Vytvořit odběr webhooku',
                    'operationId' => 'createWebhook',
                    'security'    => $auth,
                    'parameters'  => [['$ref' => '#/components/parameters/IdempotencyKey']],
                    'responses'   => [
                        '201' => ['description' => 'Vytvořeno'],
                        '409' => ['description' => 'Požadavek se stejným Idempotency-Key se právě zpracovává'],
                        '422' => ['description' => 'Chyba validace, nebo stejný klíč s jiným tělem'],
                    ],
                ],
            ],

            '/v2/webhooks/{id}' => [
                'delete' => [
                    'tags'        => ['Webhooky'],
                    'summary'     => 'Zrušit odběr webhooku',
                    'operationId' => 'deleteWebhook',
                    'security'    => $auth,
                    'parameters'  => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                        ['$ref' => '#/components/parameters/IdempotencyKey'],
                    ],
                    'responses'   => ['204' => ['description' => 'Zrušeno']],
                ],
            ],

            '/v2/webhooks/{id}/deliveries' => [
                'get' => [
                    'tags'        => ['Webhooky'],
                    'summary'     => 'Historie doručení webhooku',
                    'description' => 'Pokusy o doručení včetně stavového kódu a případné chyby — '
                        . 'sem se dívejte, když endpoint „nic nedostal“.',
                    'operationId' => 'listWebhookDeliveries',
                    'security'    => $auth,
                    'parameters'  => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses'   => ['200' => ['description' => 'OK']],
                ],
            ],

            '/v1/profile' => [
                'get' => [
                    'tags'        => ['Profil'],
                    'summary'     => 'Informace o přihlášeném uživateli',
                    'operationId' => 'getProfile',
                    'security'    => $auth,
                    'responses'   => [
                        '200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['data' => ['$ref' => '#/components/schemas/Profile']]]]]],
                        '401' => ['description' => 'Unauthorized'],
                    ],
                ],
            ],
            '/v1/services' => [
                'get' => [
                    'tags'        => ['Služby'],
                    'summary'     => 'Seznam hosting služeb',
                    'operationId' => 'listServices',
                    'security'    => $auth,
                    'responses'   => [
                        '200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Service']]]]]]],
                    ],
                ],
            ],
            '/v1/services/{id}' => [
                'get' => [
                    'tags'        => ['Služby'],
                    'summary'     => 'Detail služby',
                    'operationId' => 'getService',
                    'security'    => $auth,
                    'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'responses'   => [
                        '200' => ['description' => 'OK'],
                        '403' => ['description' => 'Forbidden'],
                        '404' => ['description' => 'Not Found'],
                    ],
                ],
            ],
            '/v1/invoices' => [
                'get' => [
                    'tags'        => ['Faktury'],
                    'summary'     => 'Seznam faktur zákazníka',
                    'operationId' => 'listInvoices',
                    'security'    => $auth,
                    'responses'   => [
                        '200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Invoice']]]]]]],
                    ],
                ],
            ],
            '/v1/domains' => [
                'get' => [
                    'tags'        => ['Domény'],
                    'summary'     => 'Seznam domén zákazníka',
                    'operationId' => 'listDomains',
                    'security'    => $auth,
                    'responses'   => [
                        '200' => ['description' => 'OK'],
                    ],
                ],
            ],
            '/v1/billing/credit' => [
                'get' => [
                    'tags'        => ['Kredit'],
                    'summary'     => 'Kreditní zůstatek',
                    'operationId' => 'getCreditBalance',
                    'security'    => $auth,
                    'responses'   => [
                        '200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['data' => ['$ref' => '#/components/schemas/CreditBalance']]]]]],
                        '403' => ['description' => 'No customer account'],
                    ],
                ],
            ],
            '/v1/billing/credit/topup' => [
                'post' => [
                    'tags'        => ['Kredit'],
                    'summary'     => '🔒 Dobít kredit — vytvoří fakturu (vyžaduje write:credit)',
                    'operationId' => 'creditTopup',
                    'security'    => $auth,
                    'parameters'  => [['$ref' => '#/components/parameters/IdempotencyKey']],
                    'requestBody' => [
                        'required' => true,
                        'content'  => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['amount'], 'properties' => ['amount' => ['type' => 'number', 'minimum' => 1, 'example' => 500]]]]],
                    ],
                    'responses'   => [
                        '201' => ['description' => 'Faktura vytvořena'],
                        '403' => ['description' => 'Chybějící ability write:credit'],
                        '422' => ['description' => 'Chybný vstup'],
                    ],
                ],
            ],
            '/v1/support/tickets' => [
                'get' => [
                    'tags'        => ['Podpora'],
                    'summary'     => 'Seznam support ticketů',
                    'operationId' => 'listTickets',
                    'security'    => $auth,
                    'responses'   => [
                        '200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/SupportTicket']]]]]]],
                    ],
                ],
                'post' => [
                    'tags'        => ['Podpora'],
                    'summary'     => '🔒 Vytvořit ticket (vyžaduje write:tickets)',
                    'operationId' => 'createTicket',
                    'security'    => $auth,
                    'parameters'  => [['$ref' => '#/components/parameters/IdempotencyKey']],
                    'requestBody' => [
                        'required' => true,
                        'content'  => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['subject', 'message'], 'properties' => [
                            'subject'  => ['type' => 'string', 'minLength' => 3, 'maxLength' => 150],
                            'message'  => ['type' => 'string', 'minLength' => 10],
                            'priority' => ['type' => 'string', 'enum' => ['low', 'normal', 'high', 'urgent'], 'default' => 'normal'],
                        ]]]],
                    ],
                    'responses' => [
                        '201' => ['description' => 'Ticket vytvořen'],
                        '403' => ['description' => 'Chybějící ability write:tickets'],
                        '422' => ['description' => 'Chybný vstup'],
                    ],
                ],
            ],
            '/v1/support/tickets/{id}' => [
                'get' => [
                    'tags'        => ['Podpora'],
                    'summary'     => 'Detail ticketu včetně zpráv',
                    'operationId' => 'getTicket',
                    'security'    => $auth,
                    'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'responses'   => [
                        '200' => ['description' => 'OK'],
                        '403' => ['description' => 'Forbidden'],
                        '404' => ['description' => 'Not Found'],
                    ],
                ],
            ],
            '/v1/support/tickets/{id}/reply' => [
                'post' => [
                    'tags'        => ['Podpora'],
                    'summary'     => '🔒 Odpovědět na ticket (vyžaduje write:tickets)',
                    'operationId' => 'replyTicket',
                    'security'    => $auth,
                    'parameters'  => [['$ref' => '#/components/parameters/IdempotencyKey'], ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'requestBody' => [
                        'required' => true,
                        'content'  => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['message'], 'properties' => ['message' => ['type' => 'string', 'minLength' => 1]]]]],
                    ],
                    'responses'   => [
                        '201' => ['description' => 'Odpověď přidána'],
                        '403' => ['description' => 'Chybějící ability write:tickets'],
                        '422' => ['description' => 'Ticket je uzavřen'],
                    ],
                ],
            ],
            '/v1/support/tickets/{id}/close' => [
                'post' => [
                    'tags'        => ['Podpora'],
                    'summary'     => '🔒 Uzavřít ticket (vyžaduje write:tickets)',
                    'operationId' => 'closeTicket',
                    'security'    => $auth,
                    'parameters'  => [['$ref' => '#/components/parameters/IdempotencyKey'], ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'responses'   => [
                        '200' => ['description' => 'Ticket uzavřen'],
                        '403' => ['description' => 'Chybějící ability write:tickets'],
                        '422' => ['description' => 'Ticket již uzavřen'],
                    ],
                ],
            ],
            '/v1/monitors' => [
                'get' => [
                    'tags'        => ['Monitoring'],
                    'summary'     => 'Monitory dostupnosti pro vaše služby',
                    'operationId' => 'listMonitors',
                    'security'    => $auth,
                    'responses'   => [
                        '200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Monitor']]]]]]],
                    ],
                ],
            ],
            '/v1/tokens' => [
                'post' => [
                    'tags'        => ['Tokeny'],
                    'summary'     => 'Vytvořit nový API token',
                    'description' => 'Vyžaduje `read` nebo `manage:tokens` ability. Ability `read` je vždy zahrnuta. Limit: 5 tokenů na účet.',
                    'operationId' => 'createToken',
                    'security'    => $auth,
                    'parameters'  => [['$ref' => '#/components/parameters/IdempotencyKey']],
                    'requestBody' => [
                        'required' => true,
                        'content'  => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['name'], 'properties' => [
                            'name'      => ['type' => 'string', 'example' => 'My integration', 'maxLength' => 80],
                            'abilities' => [
                                'type'        => 'array',
                                'description' => 'Volitelné ability. read je vždy přidána automaticky.',
                                'items'       => ['type' => 'string', 'enum' => ['read', 'write:tickets', 'write:credit', 'write:orders', 'manage:tokens']],
                                'example'     => ['read', 'write:tickets'],
                            ],
                        ]]]],
                    ],
                    'responses' => [
                        '201' => ['description' => 'Token vytvořen — plain-text token je zobrazen pouze jednou'],
                        '422' => ['description' => 'Chybný vstup nebo dosažen limit 5 tokenů'],
                    ],
                ],
            ],
            '/v1/tokens/{id}' => [
                'delete' => [
                    'tags'        => ['Tokeny'],
                    'summary'     => 'Smazat API token',
                    'operationId' => 'deleteToken',
                    'security'    => $auth,
                    'parameters'  => [['$ref' => '#/components/parameters/IdempotencyKey'], ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'responses'   => [
                        '200' => ['description' => 'Token smazán'],
                        '404' => ['description' => 'Token nenalezen'],
                    ],
                ],
            ],
        ];
    }
}
