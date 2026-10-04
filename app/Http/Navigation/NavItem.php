<?php

declare(strict_types=1);

namespace App\Http\Navigation;

use InvalidArgumentException;

/**
 * One entry of the staff console's navigation (audit 2026-10 B2): where it sits, what it opens and which staff API it reads.
 *
 *  - `screen`: a console view of the admin surface (`view`, the prototype's view id) or a server-rendered page (`page`, a path).
 *  - `api`: the endpoints the screen calls, each with the permission the controller asks for — one key, or a list where any
 *    one suffices (the chargeback queue is read by support and by finance). `null` marks a public read.
 *  - `required`: who sees the item at all — `any` of these and `all` of those, at the global scope. Both empty: every member
 *    of staff (the overview, the four-eyes requests a person opened).
 */
final readonly class NavItem
{
    public const SCREEN_VIEW = 'view';

    public const SCREEN_PAGE = 'page';

    /**
     * @param  array{cs:string, en:string}  $label
     * @param  list<array{method:string, path:string, permission:string|list<string>|null}>  $api
     * @param  list<string>  $any
     * @param  list<string>  $all
     */
    public function __construct(
        public string $key,
        public string $section,
        public int $order,
        public string $icon,
        public array $label,
        public string $screenType,
        public string $target,
        public array $api = [],
        public array $any = [],
        public array $all = [],
    ) {
        if (! in_array($screenType, [self::SCREEN_VIEW, self::SCREEN_PAGE], true)) {
            throw new InvalidArgumentException("Unknown screen type {$screenType} for {$key}.");
        }
    }

    /** An API entry: method + path relative to /v1 (a route URI with its {placeholders}) + the permission(s) it is read with. */
    public static function get(string $path, string|array|null $permission): array
    {
        return ['method' => 'GET', 'path' => $path, 'permission' => $permission];
    }

    public static function write(string $method, string $path, string|array|null $permission): array
    {
        return ['method' => strtoupper($method), 'path' => $path, 'permission' => $permission];
    }

    /** @return list<string> the permissions an API entry accepts (any one of them); empty for a public read */
    public static function accepts(array $api): array
    {
        $permission = $api['permission'] ?? null;

        return $permission === null ? [] : array_values((array) $permission);
    }

    /**
     * The boot shape (`ONHOST_BOOT.user.nav[]`). `$api` is the list already narrowed to what the person may call.
     *
     * @param  list<array{method:string, path:string, permission:string|list<string>|null}>|null  $api
     */
    public function toArray(?array $api = null): array
    {
        return [
            'key' => $this->key,
            'section' => $this->section,
            'order' => $this->order,
            'icon' => $this->icon,
            'label' => $this->label,
            'screen' => ['type' => $this->screenType, 'target' => $this->target],
            'api' => array_values($api ?? $this->api),
            'required_permissions' => ['any' => $this->any, 'all' => $this->all],
        ];
    }
}
