<?php

declare(strict_types=1);

/* The prototype's sign-out buttons only touched localStorage; in production every one of them ends the server session. */

const SIGN_OUT_CALL = 'if (window.OnhostSession && window.OnhostSession.__onhostBridged) { window.OnhostSession.signOut(); return; }';

it('routes the public account box, the panel user menu and the admin user menu through the API sign-out', function () {
    $public = $this->get('/')->assertOk()->getContent();
    expect($public)->toContain('logout: (e) => { if (e && e.preventDefault) e.preventDefault(); '.SIGN_OUT_CALL);

    [$owner] = $this->customerWithOrganization();
    $panel = $this->actingAs($owner)->get('/panel')->assertOk()->getContent();
    expect($panel)->toContain("if (m[2] === 'logout') { this.setState({ userOpen: false }); ".SIGN_OUT_CALL);

    $admin = $this->actingAs($this->staff())->get('/sprava')->assertOk()->getContent();
    expect($admin)->toContain("[_('Odhlásit se', 'Sign out'), '', () => { this.setState({ userOpen: false }); ".SIGN_OUT_CALL);

    // the bridge itself: the shell's signOut posts /auth/logout and forgets a hand-off that belongs to another account
    $this->get('/surfaces/api/onhost-session-bridge.js')->assertOk();
    $bridge = (string) file_get_contents(base_path('apps/surfaces/api/onhost-session-bridge.js')); // BinaryFileResponse has no body in tests
    expect($bridge)->toContain("window.OnhostApi.post('/auth/logout')")->and($bridge)->toContain('dropForeignHandoff(B.user.email)');
});
