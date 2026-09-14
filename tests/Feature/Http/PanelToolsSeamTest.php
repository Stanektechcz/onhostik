<?php

declare(strict_types=1);

/* The service workbench gains the web & mail toolkit (docs/ui/data-seams.md #31): a second module extends the tab map and enhances or replaces panels; the surface itself stays byte-identical. */

it('loads the toolkit module after the workbench and wires it through the workbench build seam', function () {
    [$user] = $this->customerWithOrganization();
    $this->actingAs($user);
    $panel = $this->get('/panel')->assertOk()->getContent();
    $workbenchAt = strpos($panel, 'src="/surfaces/api/onhost-panel-workbench.api.js?v=');
    $toolsAt = strpos($panel, 'src="/surfaces/api/onhost-panel-tools.api.js?v=');
    expect($workbenchAt)->not->toBeFalse()->and($toolsAt)->not->toBeFalse()->and($toolsAt)->toBeGreaterThan($workbenchAt);

    $workbench = (string) file_get_contents((string) $this->get('/surfaces/api/onhost-panel-workbench.api.js')->assertOk()->baseResponse->getFile());
    expect($workbench)->toContain('TABS: TABS')->toContain('tools.enhance(cmp, sel, tab, _, core, helpers())')->toContain('function helpers() { return { act: act, resource: resource, features: features,')->toContain('function buildCore(cmp, sel, tab, _)');

    $tools = (string) file_get_contents((string) $this->get('/surfaces/api/onhost-panel-tools.api.js')->assertOk()->baseResponse->getFile());
    expect($tools)->toContain('window.OnhostPanelTools = { enhance: enhance')
        // every prototype tab id the toolkit fills, mapped to the feature that switches it on
        ->toContain("['terminal', 'terminal']")->toContain("['phpcli', 'php_settings']")->toContain("['addons', 'monitoring']")
        ->toContain("['catchall', 'catchall']")->toContain("['relay', 'forwards']")->toContain("['fetch', 'fetchmail']")->toContain("['bkp', 'mail_backups']")
        // actions and endpoints it drives
        ->toContain("'command.run'")->toContain("'php.settings'")->toContain("'security.set'")->toContain("'http3.set'")->toContain("'staging.push'")->toContain("'deploy.rollback'")->toContain("'wp.update'")->toContain("'cdn.enable'")->toContain("'import.run'")->toContain("'node.action'")
        ->toContain("'/monitoring'")->toContain("'/deploy'")->toContain("'/backups/schedule'")->toContain("'/uploads'")->toContain("'/files/upload'")->toContain('/downloads/')
        ->toContain("'autoresponder.set'")->toContain("'filter.create'")->toContain("'spam.list.add'")->toContain("'fetchmail.create'")->toContain("'mailbox.restore'")
        // vendor neutrality: the customer never sees the panel names
        ->not->toMatch('/aapanel|ispconfig|cloudflare/i');

    $bridge = (string) file_get_contents((string) $this->get('/surfaces/api/onhost-session-bridge.js')->assertOk()->baseResponse->getFile());
    expect($bridge)->toContain('upload: function (p, form)')->toContain('body instanceof FormData');
});
