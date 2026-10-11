<?php

declare(strict_types=1);

namespace Onhost\Domain\Content;

/**
 * Public content is plain text (I-R17, I-R18): whatever a member of staff types into the blog or reference editor is stored without
 * markup, so neither the public site, the data script nor the administration ever has HTML to render. The prototype's templates
 * already render text as text; this is the second wall, applied before anything is stored.
 */
final class ContentText
{
    /** One line (title, category, name, role): tags and control characters out, whitespace collapsed, cut to `$max` characters. */
    public static function line(mixed $value, int $max): string
    {
        $text = preg_replace('/\s+/u', ' ', self::strip((string) (is_scalar($value) ? $value : ''))) ?? '';

        return mb_substr(trim($text), 0, $max);
    }

    /** A paragraph: like a line, but single line breaks survive (they render as spaces; nothing else is kept). */
    public static function paragraph(mixed $value, int $max): string
    {
        $text = self::strip((string) (is_scalar($value) ? $value : ''));
        $text = preg_replace(["/[ \t]+/u", "/ *\n */u", "/\n{3,}/u"], [' ', "\n", "\n\n"], $text) ?? '';

        return mb_substr(trim($text), 0, $max);
    }

    /**
     * Per-locale map of lines: `{cs: …, en: …}`; locales other than cs/en are dropped, empty values too.
     *
     * @return array<string, string>
     */
    public static function locales(mixed $value, int $max, bool $paragraph = false): array
    {
        $out = [];
        foreach (['cs', 'en'] as $locale) {
            $text = is_array($value) ? ($value[$locale] ?? null) : null;
            $clean = $paragraph ? self::paragraph($text, $max) : self::line($text, $max);
            if ($clean !== '') {
                $out[$locale] = $clean;
            }
        }

        return $out;
    }

    private static function strip(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text); // an entity-encoded tag ("&lt;script&gt;") is a tag after decoding
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $text) ?? '';
    }
}
