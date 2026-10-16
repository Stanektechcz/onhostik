// Performance budget, report only (W7-5). Skipped unless PERF_BUDGET is set, so the ordinary smoke is unchanged.
// For '/', '/ceny' and '/webhosting' it records the uncompressed size of the HTML document and of
// /surfaces/onhost-data.js (the fixed metrics) plus TTFB, LCP and CLS (trend only, never asserted) into
// test-results/perf.json. GET requests only: no cart writes, no sign-in, never a dev panel wired to live panels.
//
// Compression note: W7-2 (aaPanel gzip/brotli) does not show under `php artisan serve`. The byte counts from CI are
// uncompressed; compression is verified only by the manual curl in the W7-2 runbook.
import { execSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { test } from '@playwright/test';

test.skip(!process.env.PERF_BUDGET, 'set PERF_BUDGET=1 to run the performance budget report');

const PERF_PATHS = ['/', '/ceny', '/webhosting'];
const perfResults = [];

function perfCommit() {
  if (process.env.GITHUB_SHA) return process.env.GITHUB_SHA;
  try {
    return execSync('git rev-parse HEAD', { stdio: ['ignore', 'pipe', 'ignore'] }).toString().trim();
  } catch {
    return null;
  }
}

test.afterAll(() => {
  if (!process.env.PERF_BUDGET) return;
  mkdirSync('test-results', { recursive: true });
  writeFileSync('test-results/perf.json', `${JSON.stringify(perfResults, null, 2)}\n`);
});

for (const path of PERF_PATHS) {
  test(`perf budget ${path}`, async ({ page }) => {
    await page.addInitScript(() => {
      window.__perf = { lcp: null, cls: 0 };
      new PerformanceObserver((list) => {
        const entries = list.getEntries();
        const last = entries[entries.length - 1];
        if (last) window.__perf.lcp = last.renderTime || last.loadTime || last.startTime;
      }).observe({ type: 'largest-contentful-paint', buffered: true });
      new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
          if (!entry.hadRecentInput) window.__perf.cls += entry.value;
        }
      }).observe({ type: 'layout-shift', buffered: true });
    });

    const dataJs = page
      .waitForResponse((r) => new URL(r.url()).pathname === '/surfaces/onhost-data.js', { timeout: 30_000 })
      .then(async (r) => (await r.body()).length)
      .catch(() => null);

    const response = await page.goto(path, { waitUntil: 'load' });
    const htmlBytes = response ? (await response.body()).length : null;
    const dataJsBytes = await dataJs;
    await page.waitForTimeout(1_000);

    const metrics = await page.evaluate(() => {
      const nav = performance.getEntriesByType('navigation')[0];
      return {
        ttfb: nav ? nav.responseStart : null,
        lcp: window.__perf ? window.__perf.lcp : null,
        cls: window.__perf ? window.__perf.cls : null,
      };
    });

    perfResults.push({
      path,
      html_bytes: htmlBytes,
      data_js_bytes: dataJsBytes,
      ttfb_ms: metrics.ttfb === null ? null : Math.round(metrics.ttfb),
      lcp_ms: metrics.lcp === null ? null : Math.round(metrics.lcp),
      cls: metrics.cls === null ? null : Number(metrics.cls.toFixed(4)),
      commit: perfCommit(),
    });

    // No expectations on timings: TTFB/LCP/CLS are a trend only.
    // Byte ratchet - set after 3 CI runs, max + 10 % (nastaví se po 3 bězích CI, max + 10 %):
    // expect(htmlBytes).toBeLessThanOrEqual(HTML_BUDGET[path]);
    // expect(dataJsBytes).toBeLessThanOrEqual(DATA_JS_BUDGET);
  });
}
