<?php

declare(strict_types=1);

use App\Http\Navigation\StaffNavigation;
use Illuminate\Support\Facades\Process;

require_once __DIR__.'/NavigationHelpers.php';

/*
 * Audit 2026-10 B3: the console modules read only what the navigation offers, a refused read (403) shows "Nemáte přístup"
 * instead of an empty list, a ticket transition sends `to`, and the reply box can write an internal note. The modules run in
 * Node against a scripted API (admin-nav-harness.cjs); the test is skipped where Node is not installed.
 */

function navScriptRun(array $nav): array
{
    $result = Process::path(base_path())->timeout(60)->run(['node', __DIR__.'/admin-nav-harness.cjs', base_path(), json_encode($nav, JSON_THROW_ON_ERROR)]);
    expect($result->successful())->toBeTrue($result->errorOutput());

    return json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
}

beforeEach(function () {
    if (! Process::run(['node', '--version'])->successful()) {
        $this->markTestSkipped('Node is not installed.');
    }
});

it('shows "Nemáte přístup" for a refused read, sends `to` on a transition and writes an internal note', function () {
    $user = navApiStaff(['staff.support.ticket.read', 'support.ticket.manage', 'staff.customer.read', 'billing.dunning.manage']);
    $out = navScriptRun(app(StaffNavigation::class)->for($user));

    expect($out['role'])->toBe('lead')
        ->and($out['allows'])->toBe(['dash' => true, 'queue' => true, 'customers' => true, 'invoices' => true, 'fleet' => false])
        ->and($out['label'])->toBe('Upomínky a pohledávky')
        // the scripted API refuses the customer list and the dunning list: the console says so
        ->and($out['customers'][0][0])->toBe('Nemáte přístup')
        ->and($out['invoices']['title'])->toBe('Upomínky a pohledávky')
        ->and($out['invoices']['rows'][0][0])->toBe('Nemáte přístup')
        ->and($out['ticketsDenied'])->toBeFalse()
        // the navigation offers no orders to this person: the store does not even ask
        ->and($out['ordersDenied'])->toBeTrue()
        ->and(collect($out['calls'])->filter(fn ($c) => $c[0] === 'GET' && str_starts_with($c[1], '/staff/orders'))->all())->toBe([])
        ->and(collect($out['calls'])->filter(fn ($c) => $c[0] === 'GET' && str_starts_with($c[1], '/staff/outbox'))->all())->toBe([])
        ->and(collect($out['thread'])->pluck(1)->all())->toContain('[interní poznámka] Kolega se dívá');

    $posts = collect($out['calls'])->filter(fn ($c) => $c[0] === 'POST')->keyBy(1);
    expect($posts['/staff/tickets/t1/messages'][2])->toBe(['body' => 'Jen pro nás', 'visibility' => 'internal'])
        ->and($posts['/staff/tickets/t1/transition'][2])->toBe(['to' => 'RESOLVED'])
        ->and($out['view'])->toBe('invoices'); // "Doklady" opens the receivables the person may read
});

it('runs a member of staff without permissions as the console role none, with the overview only', function () {
    $out = navScriptRun(app(StaffNavigation::class)->for(navApiStaff([])));

    expect($out['role'])->toBe('none')
        ->and($out['allows'])->toBe(['dash' => true, 'queue' => false, 'customers' => false, 'invoices' => false, 'fleet' => false])
        ->and(collect($out['calls'])->where(0, 'GET')->pluck(1)->all())->toBe([]) // nothing is read
        ->and($out['flashes'][0][0] ?? null)->toBe('Nemáte přístup') // "Doklady" answers instead of opening an empty view
        ->and($out['view'])->toBe('customers');
});
