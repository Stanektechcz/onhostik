<?php

declare(strict_types=1);

namespace Onhost\Providers\Subreg;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Onhost\Platform\ProviderHttp\ProviderRequest;
use Onhost\Providers\Contracts\TlsOptions;

/**
 * The only path to the Subreg.CZ API (https://subreg.cz/manual/). Document/literal SOAP
 * as described by https://subreg.cz/wsdl (endpoint `…/soap/cmd.php?soap_format=1`,
 * types namespace `http://subreg.cz/types`, every response wrapped in
 * `<Function_Container><response>{status,data,error}</response>`).
 *
 *  - only the domain-management functions are allow-listed: Subreg is a registrar for
 *    ONhost, never a hosting backend (the Web/World hosting families are refused),
 *  - one `Login` per session, the `ssid` is cached per instance and re-issued once on
 *    "You are not logged" (500/101),
 *  - envelopes are hand-built (no ext/soap dependency) so the HTTP fakes of the test
 *    suite cover the wire format; the response XML is folded into arrays,
 *  - `major/minor` error codes are normalised through SubregErrorMap; password, ssid and
 *    AUTH-IDs are never logged (the provider HTTP client only records a summary).
 */
final class SubregSoapGateway
{
    public const PRODUCTION_ENDPOINT = 'https://subreg.cz/soap/cmd.php?soap_format=1';

    public const DEMO_ENDPOINT = 'https://demoreg.net/soap/cmd.php?soap_format=1';

    private const TYPES_NS = 'http://subreg.cz/types';

    private const ACTION_NS = 'http://subreg.cz/wsdl';

    private const SOAP_NS = 'http://schemas.xmlsoap.org/soap/envelope/';

    /** Functions the platform may call: domain registration, editing and management only. */
    public const FUNCTIONS = [
        'Login', 'Check_Domain', 'Info_Domain', 'Info_Domain_CZ', 'Domains_List', 'Set_Autorenew', 'In_Subreg',
        'Make_Order', 'Info_Order', 'Cancel_Order',
        'Create_Contact', 'Update_Contact', 'Info_Contact', 'Contacts_List', 'Check_Object', 'Info_Object',
        'Get_Pricelist', 'Pricelist', 'Prices', 'Get_TLD_Info', 'TLD_List',
        'Get_Credit', 'Get_Accountings', 'POLL_Get', 'POLL_Ack',
    ];

    /** Order types accepted by Make_Order. */
    public const ORDER_TYPES = [
        'Create_Domain', 'Renew_Domain', 'Transfer_Domain', 'Modify_Domain', 'ModifyNS_Domain', 'Restore_Domain', 'Delete_Domain',
        'TransferApprove_Domain', 'TransferDeny_Domain', 'TransferCancel_Domain', 'Create_Object', 'Update_Object', 'Transfer_Object',
    ];

    public function __construct(
        private readonly ProviderInstance $instance,
        private readonly array $credentials,
        private readonly ProviderHttpClient $http,
        private readonly CacheRepository $cache,
    ) {
        $limits = (array) config('onhost.subreg.limits');
        $this->http->configureBucket('subreg:'.$instance->key, (int) ($limits['per_hour'] ?? 2000), 3600, (float) ($limits['reserve'] ?? 0.1));
    }

    public function instance(): ProviderInstance
    {
        return $this->instance;
    }

    /** Sandbox accounts live at demoreg.net; the instance option `demo` (or a demoreg base URL) selects it. */
    public function isDemo(): bool
    {
        $options = (array) ($this->instance->options ?? []);

        return (bool) ($options['demo'] ?? false) || str_contains((string) $this->instance->base_url, 'demoreg.net');
    }

    public function endpoint(): string
    {
        $options = (array) ($this->instance->options ?? []);
        if (! empty($options['endpoint'])) {
            return (string) $options['endpoint'];
        }
        if ($this->isDemo()) {
            return self::DEMO_ENDPOINT;
        }
        $base = rtrim((string) $this->instance->base_url, '/');
        if ($base !== '' && ! str_contains($base, 'subreg.cz')) {
            return $base.'/soap/cmd.php?soap_format=1';
        }

        return (string) config('onhost.subreg.endpoint', self::PRODUCTION_ENDPOINT);
    }

    /**
     * Call an API function with a valid session. Returns the `data` element of a successful
     * response (`[]` when the function returns none).
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public function call(string $function, array $params = [], bool $critical = false, ?string $operationId = null): array
    {
        $this->assertAllowed($function);
        try {
            return $this->send($function, ['ssid' => $this->ssid()] + $params, $critical, $operationId);
        } catch (ProviderException $e) {
            if (($e->context['normalized'] ?? null) !== SubregErrorMap::NOT_LOGGED) {
                throw $e;
            }
            $this->cache->forget($this->sessionKey());

            return $this->send($function, ['ssid' => $this->ssid()] + $params, $critical, $operationId);
        }
    }

    /**
     * Make_Order wrapper. @param array<string,mixed> $params order params (period, registrant, contacts, ns, params …)
     *
     * @return array{orderid:string}
     */
    public function order(string $type, ?string $domain, array $params, ?string $object = null, bool $critical = true, ?string $operationId = null): array
    {
        if (! in_array($type, self::ORDER_TYPES, true)) {
            throw new ProviderException('subreg', ProviderErrorCode::VALIDATION, "Subreg order type {$type} is not allow-listed");
        }
        $order = array_filter(['domain' => $domain, 'object' => $object, 'type' => $type, 'params' => $params === [] ? null : $params], fn ($v) => $v !== null);
        $data = $this->call('Make_Order', ['order' => $order], $critical, $operationId);

        return ['orderid' => (string) ($data['orderid'] ?? '')];
    }

    /** Fresh login (used by health checks); the resulting ssid replaces the cached session. */
    public function login(): string
    {
        $login = (string) ($this->credentials['login'] ?? '');
        $password = (string) ($this->credentials['password'] ?? '');
        if ($login === '' || $password === '') {
            throw new ProviderException('subreg', ProviderErrorCode::AUTH, 'Subreg credentials are not configured');
        }
        $data = $this->send('Login', ['login' => $login, 'password' => $password], true);
        $ssid = (string) ($data['ssid'] ?? '');
        if ($ssid === '') {
            throw new ProviderException('subreg', ProviderErrorCode::PROVIDER_BUG, 'Subreg Login returned no session id');
        }
        $this->cache->put($this->sessionKey(), $ssid, (int) config('onhost.subreg.session_ttl_seconds', 1500));

        return $ssid;
    }

    public function forgetSession(): void
    {
        $this->cache->forget($this->sessionKey());
    }

    /** @return array{used:int, remaining:int, reset_in:int} */
    public function quota(): array
    {
        $bucket = $this->http->bucket('subreg:'.$this->instance->key);

        return ['used' => $bucket?->used() ?? 0, 'remaining' => $bucket?->remaining() ?? 0, 'reset_in' => $bucket?->secondsUntilReset() ?? 0];
    }

    /** Builds the document/literal request body for a function (exposed for the contract tests). @param array<string,mixed> $params */
    public function envelope(string $function, array $params): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $envelope = $doc->createElementNS(self::SOAP_NS, 'SOAP-ENV:Envelope');
        $envelope->setAttribute('xmlns:ns', self::TYPES_NS);
        $doc->appendChild($envelope);
        $body = $doc->createElementNS(self::SOAP_NS, 'SOAP-ENV:Body');
        $envelope->appendChild($body);
        $call = $doc->createElementNS(self::TYPES_NS, 'ns:'.$function);
        $body->appendChild($call);
        $this->appendParams($doc, $call, $params);

        return (string) $doc->saveXML();
    }

    /**
     * Folds a response XML into `{status, data, error}` (exposed for the contract tests).
     * Repeated sibling elements become lists; single elements stay scalars/maps — callers
     * normalise with `listOf()`.
     *
     * @return array{status:string, data:array<string,mixed>, error:?array{errormsg:string, major:int, minor:int}}
     */
    public static function parse(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument;
        $loaded = $xml !== '' && $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOENT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            throw new ProviderException('subreg', ProviderErrorCode::PROVIDER_BUG, 'Subreg returned a non-XML response');
        }
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('s', self::SOAP_NS);
        $fault = $xpath->query('//s:Body/s:Fault')->item(0);
        if ($fault instanceof DOMElement) {
            $text = trim((string) ($xpath->query('faultstring', $fault)->item(0)?->textContent ?? 'SOAP fault'));

            throw new ProviderException('subreg', ProviderErrorCode::TRANSIENT, "Subreg SOAP fault: {$text}", null, ['normalized' => SubregErrorMap::REGISTRY_TEMPORARILY_UNAVAILABLE], 120);
        }
        $response = $xpath->query('//s:Body/*/*[local-name()="response"]')->item(0) ?? $xpath->query('//s:Body/*/*[local-name()="return"]')->item(0);
        if (! $response instanceof DOMElement) {
            throw new ProviderException('subreg', ProviderErrorCode::PROVIDER_BUG, 'Subreg response has no <response> container');
        }
        $folded = self::fold($response);
        $folded = is_array($folded) ? $folded : [];
        $error = null;
        if (isset($folded['error']) && is_array($folded['error'])) {
            $code = $folded['error']['errorcode'] ?? [];
            $error = [
                'errormsg' => (string) ($folded['error']['errormsg'] ?? ''),
                'major' => (int) (is_array($code) ? ($code['major'] ?? 0) : $code),
                'minor' => (int) (is_array($code) ? ($code['minor'] ?? 0) : 0),
            ];
        }
        $data = $folded['data'] ?? [];

        return ['status' => (string) ($folded['status'] ?? ''), 'data' => is_array($data) ? $data : ($data === '' ? [] : ['value' => $data]), 'error' => $error];
    }

    /** @return list<mixed> */
    public static function listOf(mixed $value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }
        if (is_array($value)) {
            return array_is_list($value) ? array_values($value) : [$value];
        }

        return [$value];
    }

    // ── internals ────────────────────────────────────────────────────────────

    private function ssid(): string
    {
        $cached = $this->cache->get($this->sessionKey());
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->login();
    }

    private function sessionKey(): string
    {
        return 'onhost:subreg:ssid:'.$this->instance->key;
    }

    private function assertAllowed(string $function): void
    {
        if (! in_array($function, self::FUNCTIONS, true)) {
            throw new ProviderException('subreg', ProviderErrorCode::VALIDATION, "Subreg function {$function} is not allow-listed (only domain management functions are used)");
        }
    }

    /** @param array<string,mixed> $params @return array<string,mixed> */
    private function send(string $function, array $params, bool $critical = false, ?string $operationId = null): array
    {
        $response = $this->http->send(new ProviderRequest(
            provider: 'subreg', instanceKey: $this->instance->key, method: 'POST', url: $this->endpoint(), action: $function,
            headers: ['Content-Type' => 'text/xml; charset=utf-8', 'SOAPAction' => '"'.self::ACTION_NS.'#'.$function.'"', 'Accept' => 'text/xml'],
            body: $this->envelope($function, $params), bodyType: 'raw', timeoutSeconds: 40, critical: $critical, operationId: $operationId,
            bucket: 'subreg:'.$this->instance->key, options: TlsOptions::verify($this->instance, 'subreg'),
            judgedByCaller: true, // Subreg reports its own failures inside an HTTP 200 envelope; an HTTP 5xx used to be counted twice
        ));
        if ($response->status >= 500) {
            $this->http->recordFailure($this->instance->key);
            throw new ProviderException('subreg', ProviderErrorCode::TRANSIENT, "Subreg HTTP {$response->status}", (string) $response->status, ['normalized' => SubregErrorMap::REGISTRY_TEMPORARILY_UNAVAILABLE], 120);
        }
        if ($response->status === 401 || $response->status === 403) {
            $this->http->recordSuccess($this->instance->key); // the registrar answered: it refuses us, it is not down

            throw new ProviderException('subreg', ProviderErrorCode::AUTH, "Subreg HTTP {$response->status}", (string) $response->status, ['normalized' => SubregErrorMap::PROVIDER_AUTH_ERROR]);
        }
        try {
            $parsed = self::parse($response->rawBody);
        } catch (ProviderException $e) {
            $this->http->recordFailure($this->instance->key); // a SOAP fault, or no answer of the API at all

            throw $e;
        }
        if ($parsed['status'] === 'ok') {
            $this->http->recordSuccess($this->instance->key);

            return $parsed['data'];
        }
        $error = $parsed['error'] ?? ['errormsg' => 'unknown error', 'major' => 0, 'minor' => 0];
        $mapped = SubregErrorMap::map($error['major'], $error['minor'], $error['errormsg']);
        // the registry's or Subreg's own trouble counts against it; a refused request is an answer
        if ($mapped['code'] === ProviderErrorCode::TRANSIENT) {
            $this->http->recordFailure($this->instance->key);
        } elseif ($mapped['code'] !== ProviderErrorCode::RATE_LIMIT) {
            $this->http->recordSuccess($this->instance->key);
        }
        $vendorCode = $error['major'].'.'.$error['minor'];

        throw new ProviderException('subreg', $mapped['code'], "Subreg {$function} failed: [{$vendorCode}] {$error['errormsg']}", $vendorCode, ['normalized' => $mapped['normalized'], 'function' => $function], $mapped['retry_after']);
    }

    /** @param array<string,mixed> $params */
    private function appendParams(DOMDocument $doc, DOMElement $parent, array $params): void
    {
        foreach ($params as $name => $value) {
            if ($value === null) {
                continue;
            }
            if (is_array($value) && array_is_list($value)) {
                foreach ($value as $item) {
                    $this->appendOne($doc, $parent, (string) $name, $item);
                }

                continue;
            }
            $this->appendOne($doc, $parent, (string) $name, $value);
        }
    }

    private function appendOne(DOMDocument $doc, DOMElement $parent, string $name, mixed $value): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new ProviderException('subreg', ProviderErrorCode::VALIDATION, "Subreg parameter name {$name} is not a valid element name");
        }
        $element = $doc->createElement($name);
        if (is_array($value)) {
            $this->appendParams($doc, $element, $value);
        } else {
            $text = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            $element->appendChild($doc->createTextNode($text));
        }
        $parent->appendChild($element);
    }

    private static function fold(DOMElement $element): array|string
    {
        $children = [];
        $hasElements = false;
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $hasElements = true;
                $children[$child->localName][] = self::fold($child);
            }
        }
        if (! $hasElements) {
            return trim($element->textContent);
        }
        $out = [];
        foreach ($children as $name => $values) {
            $out[$name] = count($values) === 1 ? $values[0] : $values;
        }

        return $out;
    }
}
