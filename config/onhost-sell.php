<?php

declare(strict_types=1);

/*
 * TASK-0229 (plan W3): the launch sell-switch matrix. FAIL-CLOSED: nothing is for sale until the owner turns it on.
 *
 * `default` answers for every product family that has no entry of its own; a family entry is null (follow the default)
 * or a bool. Unset or empty env values mean "off". The switch only decides NEW sales (listing and ordering); existing
 * orders, renewals, invoices and the running services of existing customers never read it.
 * Domains are not products: they are sold through TldPolicy and are outside this matrix.
 */
$flag = static function (string $key): ?bool {
    $value = env($key);

    return $value === null || $value === '' ? null : filter_var($value, FILTER_VALIDATE_BOOLEAN);
};

return [
    'default' => (bool) $flag('ONHOST_SELL_DEFAULT'),

    'families' => [
        'web' => $flag('ONHOST_SELL_WEB'),
        'managed' => $flag('ONHOST_SELL_MANAGED'),
        'apps' => $flag('ONHOST_SELL_APPS'),
        'cloud' => $flag('ONHOST_SELL_CLOUD'),
        'data' => $flag('ONHOST_SELL_DATA'),
        'game' => $flag('ONHOST_SELL_GAME'),
        'mail' => $flag('ONHOST_SELL_MAIL'),
        'ai' => $flag('ONHOST_SELL_AI'),
        'penpot' => $flag('ONHOST_SELL_PENPOT'),
        'addon' => $flag('ONHOST_SELL_ADDON'),
    ],
];
