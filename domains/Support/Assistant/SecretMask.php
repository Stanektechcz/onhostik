<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Assistant;

use Onhost\Platform\Redaction\Redactor;

/**
 * What a person typed, with what looks like a credential masked. People paste passwords, card numbers and birth numbers
 * into a chat or a ticket; the text then leaves the platform (an AI provider sees it) and stays in a transcript. The
 * assistant never needs any of them — it proposes actions, it takes no passwords — so they are masked before anything
 * else sees the text. The ticket itself keeps what the customer wrote; only the copy that goes to a model is masked.
 */
final class SecretMask
{
    public const MASK = '[skryto]';

    public function __construct(private readonly Redactor $redactor) {}

    public function text(string $text): string
    {
        $text = $this->redactor->redactString($text);
        // "heslo: …", "heslo je …", "password is …", "PIN = …" — the word, a separator, the value
        $text = (string) preg_replace('/\b((?:hesl[oaeu]|heslem|passw(?:or)?d|pwd|pin|passphrase)\b[^\S\r\n]*(?::|=|\bje\b|\bis\b|\bbylo\b|\bwas\b)[^\S\r\n]*)(\S+)/iu', '$1'.self::MASK, $text);
        // payment card numbers: 13–19 digits in groups, checked by Luhn so that order and invoice numbers stay readable
        $text = (string) preg_replace_callback('/\b\d(?:[ -]?\d){12,18}\b/', fn (array $m): string => self::luhn((string) preg_replace('/\D/', '', $m[0])) ? self::MASK : $m[0], $text);

        // Czech birth numbers (rodné číslo): personal data no model needs
        return (string) preg_replace('/\b\d{6}\/\d{3,4}\b/', self::MASK, $text);
    }

    private static function luhn(string $digits): bool
    {
        $sum = 0;
        $double = false;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $d = (int) $digits[$i];
            if ($double) {
                $d *= 2;
                $d = $d > 9 ? $d - 9 : $d;
            }
            $sum += $d;
            $double = ! $double;
        }

        return strlen($digits) >= 13 && $sum % 10 === 0;
    }
}
