<?php

declare(strict_types=1);

namespace Onhost\Platform\Redaction;

/**
 * Removes credentials and secret-like fields from anything that is logged or
 * persisted (blueprint §5.4, §45.2, §6.1). Vendor payloads from aaPanel/WAPI may
 * echo secrets back, so redaction is applied to responses as well as requests.
 */
final class Redactor
{
    public const MASK = '[redacted]';

    /** @var list<string> lower-cased key fragments that are always masked */
    private const SECRET_KEYS = [
        'password', 'passwd', 'secret', 'token', 'auth', 'authorization', 'api_key', 'apikey',
        'request_token', 'session_id', 'auth_info', 'authinfo', 'private_key', 'privatekey',
        'cookie', 'set-cookie', 'client_secret', 'access_token', 'refresh_token', 'id_token',
        'ticket', 'csrfpreventiontoken', 'vncticket', 'dkim_private', 'signature', 'cvv',
        'ssh_private', 'root_password', 'db_password', 'mysql_password', 'totp', 'recovery_codes', 'ssl_key', 'privkey', 'ssid',
    ];

    /** @var list<string> keys that look secret-ish but are safe to keep */
    private const ALLOW_KEYS = [
        'keyset', 'nsset', 'key_name', 'keyid', 'token_id', 'tokenid', 'ticket_id', 'ticket_ref', 'ticket_number',
        'public_key', 'card_last4', 'card_brand', 'signature_ok', 'auth_method', 'authenticated', 'tokens_total',
        'author', 'author_type', 'author_id', 'author_name', // who wrote a message — "auth" is only how the word starts
    ];

    public function redact(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 12) {
            return self::MASK;
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[$key] = is_string($key) && $this->isSecretKey($key) ? self::MASK : $this->redact($item, $depth + 1);
            }

            return $out;
        }
        if (is_object($value)) {
            return $this->redact(json_decode(json_encode($value) ?: '{}', true) ?? [], $depth + 1);
        }
        if (is_string($value)) {
            return $this->redactString($value);
        }

        return $value;
    }

    public function isSecretKey(string $key): bool
    {
        $lower = strtolower($key);
        if (in_array($lower, self::ALLOW_KEYS, true)) {
            return false;
        }
        foreach (self::SECRET_KEYS as $fragment) {
            if ($lower === $fragment || str_contains($lower, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /** Masks bearer tokens, PVE/PBS tokens, SHA1 auth strings and URL credentials inside free text. */
    public function redactString(string $value): string
    {
        $patterns = [
            '/(Bearer\s+)[A-Za-z0-9._\-+\/=]{8,}/i' => '$1'.self::MASK,
            '/(PVEAPIToken=[^!\s]+![^=\s]+=)[A-Za-z0-9\-]+/i' => '$1'.self::MASK,
            '/(PBSAPIToken=[^:\s]+:)[A-Za-z0-9\-]+/i' => '$1'.self::MASK,
            '/(\b"?(?:auth|password|secret|token|session_id|request_token|api_key|auth_info)"?\s*[:=]\s*"?)[^",&\s]+/i' => '$1'.self::MASK,
            '/(https?:\/\/[^:\/\s]+:)[^@\/\s]+(@)/i' => '$1'.self::MASK.'$2',
            '/\b(ptl[ac]_)[A-Za-z0-9]{10,}/' => '$1'.self::MASK,
            '/\b(onh_(?:live|test)_)[A-Za-z0-9]{6,}/' => '$1'.self::MASK,
            '/\b(sk_(?:live|test)_)[A-Za-z0-9]{6,}/' => '$1'.self::MASK,
            // a one-time secret in a link: an invitation (?pozvanka=), a signed download, an OAuth code
            '/([?&](?:pozvanka|invite|invitation|invitation_token|code|signature|sig|expires_signature)=)[^&#\s"\'<>]+/i' => '$1'.self::MASK,
            // SOAP/XML bodies (Subreg): <password>…</password>, <ssid>…</ssid>, <authid>…</authid> carry no ":" or "=" for the rule above
            '/(<((?:[\w.-]+:)?(?:password|passwd|pass|pw|ssid|authid|auth_id|authinfo|auth_info|secret|token|api_key))\b[^>]*>)[^<]*(<\/\2>)/i' => '$1'.self::MASK.'$3',
            // key material, whatever the field is called (aaPanel sends a certificate's private key as `key`)
            '/-----BEGIN ((?:[A-Z0-9]+ )*PRIVATE KEY)-----.*?-----END \1-----/s' => '-----BEGIN $1----- '.self::MASK.' -----END $1-----',
            // a password passed to a program on the node: `wp config set DB_PASSWORD 'x'`, `--dbpass='x'`, `mysqldump -p'x'`
            '/((?<![\w-])(?:DB_PASSWORD|--dbpass|--admin_password|--user_pass|--password|--pass)(?:=|\s+))(\'[^\']*\'|"[^"]*"|\S+)/' => '$1'.self::MASK,
            '/(\s-p)(\'[^\']+\'|"[^"]+")/' => '$1'.self::MASK,
        ];

        return (string) preg_replace(array_keys($patterns), array_values($patterns), $value);
    }

    /** Short, safe fingerprint so two calls can be correlated without storing the payload. */
    public static function fingerprint(mixed $value): string
    {
        return substr(hash('sha256', json_encode($value, JSON_UNESCAPED_UNICODE) ?: ''), 0, 16);
    }
}
