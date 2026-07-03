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
            <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
            <style nonce="{$nonce}">
                body { margin: 0; }
                .swagger-ui .topbar { display: none; }
            </style>
        </head>
        <body>
            <div id="swagger-ui"></div>
            <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
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
        $base = url('/api/v1');

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
                    "Endpointy označené `🔒` vyžadují příslušnou ability. Bez ní vrátí `403 Forbidden`.",
                'version'     => '1.0.0',
                'contact'     => ['email' => 'api@onhost.cz'],
            ],
            'servers' => [
                ['url' => $base, 'description' => 'API v1'],
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
            '/profile' => [
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
            '/services' => [
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
            '/services/{id}' => [
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
            '/invoices' => [
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
            '/domains' => [
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
            '/billing/credit' => [
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
            '/billing/credit/topup' => [
                'post' => [
                    'tags'        => ['Kredit'],
                    'summary'     => '🔒 Dobít kredit — vytvoří fakturu (vyžaduje write:credit)',
                    'operationId' => 'creditTopup',
                    'security'    => $auth,
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
            '/support/tickets' => [
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
            '/support/tickets/{id}' => [
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
            '/support/tickets/{id}/reply' => [
                'post' => [
                    'tags'        => ['Podpora'],
                    'summary'     => '🔒 Odpovědět na ticket (vyžaduje write:tickets)',
                    'operationId' => 'replyTicket',
                    'security'    => $auth,
                    'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
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
            '/support/tickets/{id}/close' => [
                'post' => [
                    'tags'        => ['Podpora'],
                    'summary'     => '🔒 Uzavřít ticket (vyžaduje write:tickets)',
                    'operationId' => 'closeTicket',
                    'security'    => $auth,
                    'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'responses'   => [
                        '200' => ['description' => 'Ticket uzavřen'],
                        '403' => ['description' => 'Chybějící ability write:tickets'],
                        '422' => ['description' => 'Ticket již uzavřen'],
                    ],
                ],
            ],
            '/monitors' => [
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
            '/tokens' => [
                'post' => [
                    'tags'        => ['Tokeny'],
                    'summary'     => 'Vytvořit nový API token',
                    'description' => 'Vyžaduje `read` nebo `manage:tokens` ability. Ability `read` je vždy zahrnuta. Limit: 5 tokenů na účet.',
                    'operationId' => 'createToken',
                    'security'    => $auth,
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
            '/tokens/{id}' => [
                'delete' => [
                    'tags'        => ['Tokeny'],
                    'summary'     => 'Smazat API token',
                    'operationId' => 'deleteToken',
                    'security'    => $auth,
                    'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'responses'   => [
                        '200' => ['description' => 'Token smazán'],
                        '404' => ['description' => 'Token nenalezen'],
                    ],
                ],
            ],
        ];
    }
}
