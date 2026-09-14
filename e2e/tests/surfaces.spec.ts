import { test, expect } from '@playwright/test';

// The surfaces are the prototype files served through SurfaceRenderer; these checks make sure the seams
// (boot object, bridge, API store) load and nothing visual regressed (screenshots vs. docs/ui baselines).

test.describe('public surface', () => {
  test('boots with the server data script and the status page', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveTitle(/Onhost/i);
    const boot = await page.evaluate(() => (window as any).ONHOST);
    expect(boot.surface).toBe('public');
    expect(boot.user).toBeNull();
    const data = await page.evaluate(() => typeof (window as any).ONHOST_DATA?.status === 'function');
    expect(data).toBe(true);
    await page.goto('/stav');
    await expect.poll(() => page.evaluate(() => location.hash)).toBe('#/stav');
    await expect(page).toHaveScreenshot('public-status.png', { fullPage: false });
  });

  test('public API endpoints answer the shapes onhost-data.js expects', async ({ request }) => {
    const status = await request.get('/v1/status');
    expect(status.ok()).toBeTruthy();
    const body = await status.json();
    expect(Array.isArray(body.data.components)).toBe(true);
    const locations = await request.get('/v1/locations');
    expect((await locations.json()).data[0]).toHaveLength(5);
  });
});

test.describe('panel', () => {
  test('redirects guests to sign-in and keeps the deep link', async ({ page }) => {
    const response = await page.goto('/panel/sluzby');
    expect(response?.url()).toContain('/prihlaseni');
    expect(new URL(response!.url()).searchParams.get('next')).toBe('/panel/sluzby');
  });

  test('signed-in customer sees the real session and the API-backed store', async ({ page, request }) => {
    const email = process.env.E2E_CUSTOMER_EMAIL;
    const password = process.env.E2E_CUSTOMER_PASSWORD;
    test.skip(!email || !password, 'E2E_CUSTOMER_EMAIL/PASSWORD not set');
    await page.goto('/');
    await request.get('/sanctum/csrf-cookie');
    const login = await request.post('/v1/auth/login', { data: { email, password } });
    expect(login.ok()).toBeTruthy();
    await page.goto('/panel');
    const boot = await page.evaluate(() => (window as any).ONHOST);
    expect(boot.user?.email).toBe(email);
    expect(boot.user?.role).toMatch(/klient|partner/);
    await expect.poll(() => page.evaluate(() => Boolean((window as any).OnhostStore && (window as any).OnhostStore.refresh))).toBe(true);
    await expect(page).toHaveScreenshot('panel-overview.png');
  });
});
