<?php

declare(strict_types=1);

use App\Http\Support\SurfaceRenderer;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * TASK-0070 (audit 2026-10 P3, package C11): accessibility and language of the served surfaces. The prototypes say `<html>` with
 * no language; the panel always started in Czech even for a person who chose English; a keyboard user saw no focus on most
 * controls; and every module spelled its own Czech plurals. Now: `<html lang>` is the language the surface starts in (the
 * person's locale on the panel and the staff console), every language switch keeps it in step, one `:focus-visible` outline is
 * injected for every control the design gave none, and the session bridge carries the shared plural helper.
 */

beforeEach(fn () => Cache::flush());

it('serves the panel in the person\'s language: <html lang>, the starting language and the switch that keeps it in step', function () {
    [$customer] = $this->customerWithOrganization(['locale' => 'en']);

    $html = $this->actingAs($customer)->get('/panel')->assertOk()->getContent();

    expect($html)->toContain('<html lang="en"')
        ->toContain("lang: (window.ONHOST && window.ONHOST.locale === 'en' ? 'en' : 'cs'), simple: false, ")
        ->toContain("toggleLang: () => this.setState(st => { const lang = st.lang === 'cs' ? 'en' : 'cs'; try { document.documentElement.lang = lang; } catch (x) {} return { lang }; }),")
        ->not->toContain("lang: 'cs', simple: false, ")
        ->toContain('<style id="onhost-focus-visible">:where(')
        ->and(substr_count($html, '<html'))->toBe(1);

    [$czech] = $this->customerWithOrganization(['locale' => 'cs']);
    expect($this->actingAs($czech)->get('/panel')->assertOk()->getContent())->toContain('<html lang="cs"');
});

it('starts the public site, the partner portal and the concepts in Czech whoever looks, with the focus outline', function () {
    $public = $this->get('/')->assertOk()->getContent();
    expect($public)->toContain('<html lang="cs"')->toContain('id="onhost-focus-visible"')
        ->toContain("setCs: () => { try { document.documentElement.lang = 'cs'; } catch (x) {} this.setState({ lang: 'cs' }, this.runSearch); }");

    $renderer = app(SurfaceRenderer::class);
    foreach (['public', 'partner', 'mobile', 'widgets'] as $surface) {
        $out = $renderer->render($surface, ['locale' => 'en', 'user' => null, 'demo' => false], false);
        expect($out)->toContain('<html lang="cs"')->toContain('id="onhost-focus-visible"');
    }
    $admin = $renderer->render('admin', ['locale' => 'en', 'user' => null, 'demo' => false], false);
    expect($admin)->toContain('<html lang="en"')->toContain("lang: (window.ONHOST && window.ONHOST.locale === 'en' ? 'en' : 'cs'), dark: false, ");
});

it('leaves the prototype files themselves untouched (the seams live in the renderer)', function () {
    foreach (SurfaceRenderer::SURFACES as $file) {
        $raw = (string) file_get_contents(base_path('apps/surfaces/'.$file));
        expect($raw)->toContain('<html>')->not->toContain('onhost-focus-visible')->not->toContain('<html lang=');
    }
});

it('carries one shared Czech plural helper in the session bridge', function () {
    $node = (new ExecutableFinder)->find('node');
    if ($node === null) {
        $this->markTestSkipped('node is not installed; the harness is tests/js/czech-plural.harness.mjs');
    }
    $process = new Process([$node, base_path('tests/js/czech-plural.harness.mjs')], base_path(), null, null, 60);
    $process->run();
    $out = json_decode($process->getOutput(), true);

    expect($out)->toBeArray('harness output: '.$process->getOutput().$process->getErrorOutput())
        ->and($out['failures'])->toBe([])
        ->and($process->getExitCode())->toBe(0);
});
