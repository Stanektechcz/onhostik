<?php

declare(strict_types=1);

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Identity\Authorization\TokenScopes;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Notifications\NotificationService;

/*
 * TASK-0067 (item 8): the content team's console asks for its staff inbox and got 403 — the internal inbox was read with
 * `staff.customer.read` (Customer 360), which the content team deliberately does not hold (AssistantScopeTest: staff without the
 * customer view do not reach a customer's account). `staff.inbox.read` is the narrow key: the internal notifications addressed to
 * the reader, nothing about customers. Whoever holds the customer view still reads the whole internal inbox.
 */

it('lets the content team read and mark its own staff inbox, and nothing of the internal feed about customers', function () {
    [, $org] = $this->customerWithOrganization();
    $writer = $this->staff('marketing_content');
    $colleague = $this->staff('marketing_content');
    $notify = app(NotificationService::class);
    $mine = $notify->notify('internal', 'content', 'Článek čeká na schválení', null, '/sprava/obsah', null, $writer->id);
    $theirs = $notify->notify('internal', 'content', 'Jiný článek', null, '/sprava/obsah', null, $colleague->id);
    $customer = $notify->notify('internal', 'security', 'Obnovy vlastníka opakovaně zastaveny: '.$org->name, 'kdo je zastavuje, může držet účet vlastníka', '/sprava/zakaznici', $org->id);

    $this->actingAs($writer, 'sanctum');
    $ids = array_column($this->getJson('/v1/notifications')->assertOk()->json('data'), 'id');
    expect($ids)->toBe([$mine->id]);

    // marking read reaches only the reader's own rows
    $this->postJson('/v1/notifications/read', ['audience' => 'internal', 'ids' => [$mine->id, $theirs->id, $customer->id]])->assertOk()->assertJsonPath('data.read', 1);
    expect(Notification::query()->whereKey($theirs->id)->value('read_at'))->toBeNull()
        ->and(Notification::query()->whereKey($customer->id)->value('read_at'))->toBeNull();

    // still no customer view
    $this->getJson('/v1/staff/customers')->assertForbidden();

    // whoever holds the customer view reads the whole internal inbox, as before
    $this->actingAs($this->staff('support_l1'), 'sanctum');
    expect(array_column($this->getJson('/v1/notifications')->assertOk()->json('data'), 'id'))->toContain($customer->id)->toContain($mine->id);
});

it('makes staff.inbox.read a narrow staff key held by the content team and the break-glass owner only', function () {
    $holders = array_keys(array_filter(RoleCatalog::all(), fn (array $role) => in_array('staff.inbox.read', $role['permissions'], true)));
    sort($holders);
    expect(PermissionCatalog::all()['staff.inbox.read'] ?? null)->toMatchArray(['audience' => 'staff', 'risk' => PermissionCatalog::NORMAL])
        ->and($holders)->toBe(['marketing_content', 'platform_owner'])
        ->and(TokenScopes::for('staff.inbox.read'))->toBeNull()
        ->and(RoleCatalog::all()['marketing_content']['permissions'])->not->toContain('staff.customer.read');
});
