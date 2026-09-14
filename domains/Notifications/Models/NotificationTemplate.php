<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications\Models;

use Onhost\Platform\Eloquent\Model;

/** Versioned, localized template. Placeholders are `{{name}}`; unknown placeholders render empty and are reported by the test render. */
final class NotificationTemplate extends Model
{
    protected static string $idPrefix = 'ntpl';

    protected $table = 'notification_templates';

    protected function casts(): array
    {
        return ['variables' => 'array', 'mandatory' => 'boolean', 'version' => 'integer'];
    }

    public static function current(string $key, string $channel, string $locale): ?self
    {
        return self::query()->where('key', $key)->where('channel', $channel)->where('state', 'active')->where('locale', $locale)->orderByDesc('version')->first()
            ?? self::query()->where('key', $key)->where('channel', $channel)->where('state', 'active')->where('locale', 'cs')->orderByDesc('version')->first();
    }

    /** @param array<string,mixed> $vars @return array{subject:string, body:string, missing:list<string>} */
    public function render(array $vars): array
    {
        $missing = [];
        $fill = function (string $text) use ($vars, &$missing): string {
            return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function ($m) use ($vars, &$missing) {
                $value = data_get($vars, $m[1]);
                if ($value === null) {
                    $missing[] = $m[1];

                    return '';
                }

                return is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
            }, $text) ?? $text;
        };

        return ['subject' => $fill((string) $this->subject), 'body' => $fill($this->body), 'missing' => array_values(array_unique($missing))];
    }
}
