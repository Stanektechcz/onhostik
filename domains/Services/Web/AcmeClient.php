<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;

/**
 * A small ACME v2 client (RFC 8555) for the certificates the panels cannot issue themselves: wildcard names
 * and any name that must be validated by DNS-01 because the site is behind a CDN or not yet pointed at us.
 * One RSA account key for the platform lives in the secret store; every request is a JWS signed with it.
 */
final class AcmeClient
{
    private ?array $directory = null;

    private ?string $nonce = null;

    private ?string $accountUrl = null;

    public function __construct(private readonly SecretStore $secrets) {}

    public function directoryUrl(): string
    {
        return (string) config('onhost.acme.directory', 'https://acme-v02.api.letsencrypt.org/directory');
    }

    /** @return array{url:string, authorizations:list<string>, finalize:string, status:string} */
    public function newOrder(array $domains): array
    {
        $identifiers = array_map(fn (string $d) => ['type' => 'dns', 'value' => strtolower($d)], array_values(array_unique($domains)));
        $response = $this->signed($this->dir('newOrder'), ['identifiers' => $identifiers]);
        $body = (array) $response->json();

        return ['url' => (string) $response->header('Location'), 'authorizations' => array_map('strval', (array) ($body['authorizations'] ?? [])), 'finalize' => (string) ($body['finalize'] ?? ''), 'status' => (string) ($body['status'] ?? 'pending')];
    }

    /** @return array{status:string, certificate:?string, authorizations:list<string>, finalize:string} */
    public function order(string $url): array
    {
        $body = (array) $this->signed($url, null)->json();

        return ['status' => (string) ($body['status'] ?? ''), 'certificate' => isset($body['certificate']) ? (string) $body['certificate'] : null, 'authorizations' => array_map('strval', (array) ($body['authorizations'] ?? [])), 'finalize' => (string) ($body['finalize'] ?? '')];
    }

    /**
     * The DNS-01 challenge of an authorization: the TXT record the zone has to carry before we respond.
     *
     * @return array{domain:string, wildcard:bool, status:string, challenge_url:?string, record_name:string, record_value:?string}
     */
    public function dnsChallenge(string $authorizationUrl): array
    {
        $body = (array) $this->signed($authorizationUrl, null)->json();
        $domain = (string) data_get($body, 'identifier.value', '');
        $challenge = collect((array) ($body['challenges'] ?? []))->first(fn ($c) => ($c['type'] ?? '') === 'dns-01');
        $token = (string) ($challenge['token'] ?? '');
        $value = $token === '' ? null : self::base64url(hash('sha256', $token.'.'.$this->thumbprint(), true));

        return ['domain' => $domain, 'wildcard' => (bool) ($body['wildcard'] ?? false), 'status' => (string) ($body['status'] ?? ''), 'challenge_url' => isset($challenge['url']) ? (string) $challenge['url'] : null, 'record_name' => '_acme-challenge.'.$domain, 'record_value' => $value];
    }

    public function respond(string $challengeUrl): void
    {
        $this->signed($challengeUrl, []);
    }

    public function authorizationStatus(string $authorizationUrl): string
    {
        $body = (array) $this->signed($authorizationUrl, null)->json();
        $error = collect((array) ($body['challenges'] ?? []))->first(fn ($c) => ($c['type'] ?? '') === 'dns-01')['error']['detail'] ?? null;

        return (string) ($body['status'] ?? '').($error ? ' ('.$error.')' : '');
    }

    public function finalize(string $finalizeUrl, string $csrDer): string
    {
        $body = (array) $this->signed($finalizeUrl, ['csr' => self::base64url($csrDer)])->json();

        return (string) ($body['status'] ?? '');
    }

    public function certificate(string $certificateUrl): string
    {
        return (string) $this->signed($certificateUrl, null, 'application/pem-certificate-chain')->body();
    }

    /**
     * A fresh key pair and a CSR for the names (the first is the CN; all are SANs).
     *
     * @return array{key:string, csr_der:string, csr_pem:string}
     */
    public function csr(array $domains, string $keyType = 'ec'): array
    {
        $domains = array_values(array_unique(array_map('strtolower', $domains)));
        $config = tempnam(sys_get_temp_dir(), 'acme');
        file_put_contents($config, "[req]\ndefault_bits = 2048\ndefault_md = sha256\ndistinguished_name = dn\nreq_extensions = v3_req\n[dn]\n[v3_req]\nsubjectAltName = ".implode(',', array_map(fn ($d) => 'DNS:'.$d, $domains))."\n");
        try {
            $key = $keyType === 'rsa'
                ? openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048, 'config' => $config])
                : openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1', 'config' => $config]);
            if ($key === false) {
                throw new DomainError('acme_key_failed', 'Could not generate the certificate key: '.(string) openssl_error_string(), 500);
            }
            $csr = openssl_csr_new(['commonName' => $domains[0]], $key, ['config' => $config, 'req_extensions' => 'v3_req', 'digest_alg' => 'sha256']);
            if ($csr === false || ! openssl_csr_export($csr, $pem) || ! openssl_pkey_export($key, $keyPem, null, ['config' => $config])) {
                throw new DomainError('acme_csr_failed', 'Could not build the certificate request: '.(string) openssl_error_string(), 500);
            }
        } finally {
            @unlink($config);
        }
        $der = base64_decode(preg_replace('/-----[A-Z ]+-----|\s+/', '', $pem) ?? '', true);

        return ['key' => $keyPem, 'csr_der' => (string) $der, 'csr_pem' => $pem];
    }

    /** @return array{not_before:?int, not_after:?int, issuer:?string, domains:list<string>} */
    public static function inspect(string $pem): array
    {
        $first = preg_match('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $pem, $m) ? $m[0] : $pem;
        $info = @openssl_x509_parse($first);
        if (! is_array($info)) {
            return ['not_before' => null, 'not_after' => null, 'issuer' => null, 'domains' => []];
        }
        $san = (string) ($info['extensions']['subjectAltName'] ?? '');
        $domains = array_values(array_filter(array_map(fn ($p) => trim(str_replace('DNS:', '', trim($p))), explode(',', $san))));

        return ['not_before' => isset($info['validFrom_time_t']) ? (int) $info['validFrom_time_t'] : null, 'not_after' => isset($info['validTo_time_t']) ? (int) $info['validTo_time_t'] : null, 'issuer' => (string) ($info['issuer']['O'] ?? ($info['issuer']['CN'] ?? '')), 'domains' => $domains];
    }

    public function accountUrl(): string
    {
        if ($this->accountUrl !== null) {
            return $this->accountUrl;
        }
        $ref = SecretRef::parse('db://acme/account');
        $stored = $this->secrets->exists($ref) ? $this->secrets->read($ref) : [];
        if (! empty($stored['kid'])) {
            return $this->accountUrl = (string) $stored['kid'];
        }
        $payload = ['termsOfServiceAgreed' => true];
        if ((string) config('onhost.acme.contact', '') !== '') {
            $payload['contact'] = ['mailto:'.config('onhost.acme.contact')];
        }
        $response = $this->signed($this->dir('newAccount'), $payload, null, true);
        $kid = (string) $response->header('Location');
        if ($kid === '') {
            throw new DomainError('acme_account_failed', 'The certificate authority did not return an account URL.', 502);
        }
        $this->secrets->write($ref, array_merge($this->accountKey(), ['kid' => $kid]));

        return $this->accountUrl = $kid;
    }

    private function signed(string $url, ?array $payload, string $accept = 'application/json', bool $useJwk = false): Response
    {
        $attempt = 0;
        while (true) {
            $protected = ['alg' => 'RS256', 'nonce' => $this->nonce(), 'url' => $url];
            if ($useJwk) {
                $protected['jwk'] = $this->jwk();
            } else {
                $protected['kid'] = $this->accountUrl();
            }
            $p = self::base64url(json_encode($protected, JSON_UNESCAPED_SLASHES) ?: '');
            $b = $payload === null ? '' : self::base64url(json_encode($payload === [] ? new \stdClass : $payload, JSON_UNESCAPED_SLASHES) ?: '');
            openssl_sign($p.'.'.$b, $signature, $this->accountKey()['private'], OPENSSL_ALGO_SHA256);
            $response = Http::withHeaders(['Content-Type' => 'application/jose+json', 'Accept' => $accept])->timeout(30)->withBody((string) json_encode(['protected' => $p, 'payload' => $b, 'signature' => self::base64url($signature)]), 'application/jose+json')->post($url);
            $this->nonce = $response->header('Replay-Nonce') ?: null;
            if ($response->status() === 400 && str_contains((string) $response->json('type'), 'badNonce') && $attempt++ < 3) {
                $this->nonce = null;

                continue;
            }
            if ($response->failed()) {
                throw new DomainError('acme_request_failed', 'Certificate authority: '.((string) ($response->json('detail') ?? $response->status())), 502, ['url' => $url]);
            }

            return $response;
        }
    }

    private function nonce(): string
    {
        if ($this->nonce === null) {
            $this->nonce = Http::timeout(20)->head($this->dir('newNonce'))->header('Replay-Nonce') ?: null;
            if ($this->nonce === null) {
                throw new DomainError('acme_request_failed', 'Certificate authority did not provide a nonce.', 502);
            }
        }
        $n = $this->nonce;
        $this->nonce = null;

        return $n;
    }

    private function dir(string $key): string
    {
        if ($this->directory === null) {
            $this->directory = (array) Http::timeout(20)->get($this->directoryUrl())->json();
        }
        if (empty($this->directory[$key])) {
            throw new DomainError('acme_request_failed', "Certificate authority directory has no {$key} endpoint.", 502);
        }

        return (string) $this->directory[$key];
    }

    /** @return array{private:string, public:string} */
    private function accountKey(): array
    {
        static $key = null;
        if ($key !== null) {
            return $key;
        }
        $ref = SecretRef::parse('db://acme/account');
        $stored = $this->secrets->exists($ref) ? $this->secrets->read($ref) : [];
        if (! empty($stored['private'])) {
            return $key = ['private' => (string) $stored['private'], 'public' => (string) ($stored['public'] ?? '')];
        }
        $res = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        if ($res === false || ! openssl_pkey_export($res, $private)) {
            throw new DomainError('acme_key_failed', 'Could not generate the ACME account key.', 500);
        }
        $details = openssl_pkey_get_details($res) ?: [];
        $key = ['private' => (string) $private, 'public' => (string) ($details['key'] ?? '')];
        $this->secrets->write($ref, $key);

        return $key;
    }

    /** @return array{e:string, kty:string, n:string} */
    private function jwk(): array
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_private($this->accountKey()['private']) ?: throw new DomainError('acme_key_failed', 'ACME account key unreadable.', 500)) ?: [];

        return ['e' => self::base64url((string) ($details['rsa']['e'] ?? '')), 'kty' => 'RSA', 'n' => self::base64url((string) ($details['rsa']['n'] ?? ''))];
    }

    private function thumbprint(): string
    {
        return self::base64url(hash('sha256', json_encode($this->jwk(), JSON_UNESCAPED_SLASHES) ?: '', true));
    }

    public static function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
