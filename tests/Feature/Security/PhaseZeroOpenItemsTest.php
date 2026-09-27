<?php

declare(strict_types=1);

/*
 * TASK-0041 — the P0-16 red team on the stacked wave-two chain (c59e08a) found P0-08 (IF-4, IF-8, IF-9), P0-09 (IF-5) and
 * P0-14 (IF-16) open there, because their fix, TASK-0039, was in neither wave. Each hole was pinned here by a test that passed
 * while the hole was there, and filed in the runbook's open list.
 *
 * TASK-0039 is now rebased onto that chain (0040 → 0041 → 0039, the final Phase-0 chain). All nine pins went red for the right
 * reason — PA-04 `token_organization_mismatch`, the staff token `Missing permission`, IF-4 and the archive restore the shadow
 * rows, the credit gate and the credit spend `false`, SS-14 `valid false`, EXPL-1 `grace_period_active`, SS-4 `reason required`
 * — and were deleted. The proofs that stand in their place are TASK-0039's:
 *
 *   PA-04 (both pins)            TokenPrincipalTest "refuses a token on another organization by header…", "…the resources of
 *                                another organization by id…" and "never lends a token the global reach of a staff role"
 *   IF-4                         StaffModeTest "counts a staff role on a customer key only as staff reach…"
 *   EXPL-1/2/3, SS-1             StaffModeTest "treats a member of staff on a customer route as the customer they are there…";
 *                                on the staff route (the P0-16 re-check: they had moved there, with the customer keys) StaffModeTest
 *                                "refuses the staff route to a member of staff without the staff key…", "takes a second person in
 *                                any organization of their own…"
 *   credit gate / credit spend   ServiceReinstatement::actorMay / actsForPlatform ask StaffActor::may with the staff billing key
 *                                (PayAndRestoreTest "undoes a refunded cancellation on the platform authority only for a staff
 *                                billing key…")
 *   SS-14                        StaffModeTest "passes the console pre-flight for whom the token was issued and for a member…"
 *   SE-3/SS-5, backup.delete     RiskFloorTest "makes staff reach on a customer CRITICAL key CRITICAL again…" and StaffModeTest
 *                                "makes a forced purge and a skipped archive CRITICAL staff.service.delete…"
 *   SS-4/PA-06                   StaffPanelLoginTest
 *
 * What stays is the register test: the runbook's open list names only what is still open after TASK-0039 — the parts it
 * ships as a shadow release behind a switch — and no longer the holes it closed.
 */

/**
 * What stays open after TASK-0039, with the program key(s) and the switch that closes each. They are written down (the shadow
 * log, the operator's list) but still allowed until the operator switches the rule on.
 *
 * @return array<string, list<string>>
 */
function pzoOpenItems(): array
{
    return [
        'IF-4' => ['P0-08', 'P0-15', 'ONHOST_STAFF_REACH_ENFORCED'],            // a staff role's customer keys: logged, not yet refused
        'archive.restore' => ['P0-08', 'IF-4', 'ONHOST_STAFF_REACH_ENFORCED'],  // a global backup role restores any archive: logged, not yet refused
        'PA-04' => ['P0-09', 'IF-5', 'ONHOST_TOKEN_ORGANIZATION_REQUIRED'],     // a token bound to NO organization keeps its person's memberships
    ];
}

/** The holes TASK-0039 closed: struck from the open list. @return list<string> */
function pzoClosedItems(): array
{
    return ['EXPL-1', 'EXPL-2', 'EXPL-3', 'SS-1', 'SS-14', 'credit.maySpend', 'SS-5', 'SE-3', 'backup.delete', 'SS-4', 'PA-06'];
}

it('records in the breach register only what TASK-0039 left open, with the switch that closes it, and strikes what it closed', function () {
    // The open list is where the operator and the next red team read what is still exploitable today: a line left for a closed
    // hole sends them after nothing, a missing one hides what is only logged.
    $runbook = (string) file_get_contents(base_path('docs/runbooks/breach-register.md'));
    expect(str_contains($runbook, '## Still open after Phase 0 wave 2'))->toBeTrue('breach-register.md has no "Still open after Phase 0 wave 2" list');
    expect(str_contains($runbook, 'Phase 0 of the permission program closed nine holes'))->toBeFalse('breach-register.md calls every hole closed');
    $section = explode('## ', explode('## Still open after Phase 0 wave 2', $runbook, 2)[1] ?? '', 2)[0];
    // one entry per bullet, its continuation lines included
    $entries = array_values(array_filter(array_map('trim', preg_split('/^\* /m', $section) ?: []), fn (string $entry) => $entry !== ''));
    array_shift($entries); // the paragraph before the first bullet
    $entryOf = fn (string $id): string => implode("\n", array_filter($entries, fn (string $entry) => str_starts_with($entry, "`{$id}`")));

    foreach (pzoOpenItems() as $id => $keys) {
        expect($entryOf($id))->not->toBe('', "{$id} is not in the open list");
        foreach ([...$keys, 'TASK-0039'] as $needed) {
            expect($entryOf($id))->toContain($needed);
        }
    }
    foreach (pzoClosedItems() as $id) {
        expect(array_filter($entries, fn (string $entry) => str_contains(strtok($entry, ':') ?: '', "`{$id}`")))->toBeEmpty("{$id} was closed by TASK-0039 and is still in the open list");
    }
});
