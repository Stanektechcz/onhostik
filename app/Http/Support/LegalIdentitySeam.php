<?php

declare(strict_types=1);

namespace App\Http\Support;

use Onhost\Domain\Invoicing\Models\LegalEntity;

/**
 * Puts the operator's real identity where the prototype shows invented contact data (I-R11, owner decision of 2026-10-08): the
 * footer of the public site names the company (name, IČO, seat, register entry) instead of "Onhost s.r.o. · Praha", and the
 * prototype telephone number +420 210 000 111 — in the footer, the support rows, the NOC card of the panel and the widgets gallery —
 * is the operator's number (§ 1820 OZ: a consumer is given a telephone number before the contract). The prototype files stay
 * byte-identical; like `CreditClaimsSeam` this corrects the served TEMPLATE on its way out, after the cache, so a change of the
 * legal entity or of the configuration shows at once and a customer's own text is never rewritten.
 *
 * The prototype also promised "Telefon 24/7" and "u P1 zvedáme do dvou zazvonění". The operator has not documented a round-the-clock
 * line (L-03 took "Podpora 24/7 · odpověď do 30 minut" out of the footer), so those labels name the telephone without a promise.
 */
final class LegalIdentitySeam
{
    /** The invented number of the prototype. */
    public const PROTOTYPE_PHONE = '+420 210 000 111';

    /** @return array{name:string, ico:string, address:string, phone:string, email:string, registry:string, registry_en:string} */
    public static function identity(): array
    {
        $cfg = fn (string $key): string => trim((string) config("onhost.legal_entity.{$key}", ''));
        $row = LegalEntity::query()->where('key', (string) config('onhost.billing.legal_entity', 'onhost-cz'))->first();
        $entity = $row instanceof LegalEntity ? $row : null;
        $address = $entity === null ? [] : (array) $entity->address;
        $zipCity = trim(((string) ($address['postal_code'] ?? $cfg('zip'))).' '.((string) ($address['city'] ?? $cfg('city'))));
        $email = $cfg('email') !== '' ? $cfg('email') : trim((string) config('mail.from.address', ''));
        $meta = $entity === null ? [] : (array) $entity->meta;
        $registry = trim((string) ($meta['registry'] ?? '')) ?: $cfg('registry');

        return [
            'name' => trim((string) ($entity->name ?? '')) ?: $cfg('name'),
            'ico' => trim((string) ($entity->ico ?? '')) ?: $cfg('ico'),
            'address' => trim(implode(', ', array_filter([(string) ($address['street'] ?? $cfg('street')), $zipCity]))),
            'phone' => $cfg('phone'),
            'email' => $email,
            'registry' => $registry,
            'registry_en' => trim((string) ($meta['registry_en'] ?? '')) ?: $cfg('registry_en') ?: $registry,
        ];
    }

    public static function apply(string $html): string
    {
        if (! str_contains($html, self::PROTOTYPE_PHONE) && ! str_contains($html, 'Onhost s.r.o.')) {
            return $html;
        }
        $id = self::identity();
        $phone = $id['phone'] !== '' ? $id['phone'] : '—';
        $year = now((string) config('onhost.billing.timezone', 'Europe/Prague'))->year;
        $footer = fn (string $registry): string => self::js(implode(' · ', array_filter(["© {$year} {$id['name']}", $id['ico'] !== '' ? "IČO {$id['ico']}" : '', $id['address'], $registry])));
        $protoPhone = self::PROTOTYPE_PHONE;

        return strtr($html, [
            "footCopy: '© 2026 Onhost s.r.o. · Praha'" => "footCopy: '".$footer($id['registry'])."'",
            "footCopy: '© 2026 Onhost s.r.o. · Prague'" => "footCopy: '".$footer($id['registry_en'])."'",
            "tbPhone: '{$protoPhone}'," => "tbPhone: '".self::js($id['email'] !== '' ? "{$phone} · {$id['email']}" : $phone)."',",
            // the NOC card and the support rows: the number, without the promise of an answer in two rings or round the clock
            "_('Nepřetržitě, u P1 zvedáme do dvou zazvonění.', 'Around the clock; for P1 we answer within two rings.')" => "_('Telefonní kontakt provozovatele.', 'The operator’s telephone contact.')",
            "_('{$protoPhone} · P1 řešíme okamžitě.', '{$protoPhone} · P1 handled immediately.')" => "_('".self::js($phone)."', '".self::js($phone)."')",
            "_('Telefon 24/7', 'Phone, 24/7')" => "_('Telefon', 'Phone')",
            $protoPhone => self::js($phone),
        ]);
    }

    /** The inside of a single-quoted JavaScript string literal. */
    private static function js(string $value): string
    {
        return substr((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), 1, -1);
    }
}
