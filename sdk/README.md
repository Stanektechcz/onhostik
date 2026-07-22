# OnHost SDKs

Official client libraries for the [OnHost REST API](https://onhost.cz/api/docs).
Authenticate with a personal access token from the panel (**Účet → API tokeny**).

The API is versioned (`v1`, `v2`) and documented via OpenAPI at
`/api/openapi.json` (Swagger UI at `/api/docs`). Write endpoints accept an
`Idempotency-Key` header — the SDKs attach one automatically.

---

## PHP — `sdk/php`

```php
use OnHost\Sdk\Client;

$onhost = new Client('pat_your_token_here');

$services = $onhost->services();
$balance  = $onhost->creditBalance();

$onhost->createTicket('Server je pomalý', 'Od rána vysoká odezva.');
$onhost->topUpCredit(50000, 'CZK'); // 500,00 Kč
```

Install (once published): `composer require onhost/sdk-php`.
Requires PHP ≥ 8.2 and `guzzlehttp/guzzle`.

---

## JavaScript / TypeScript — `sdk/js`

```js
import { OnHost } from './sdk/js/onhost.js';

const onhost = new OnHost('pat_your_token_here');

const services = await onhost.services();
await onhost.topUpCredit(50000, 'CZK');
```

Zero dependencies — uses the platform `fetch`. Works in Node ≥ 18 and browsers.

---

## Generating from OpenAPI

Both clients are hand-written thin wrappers. For a fully generated client in any
language, feed `/api/openapi.json` to
[openapi-generator](https://openapi-generator.tech/).
