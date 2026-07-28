// @ts-check
import { test, expect } from '@playwright/test';

const OWNER_EMAIL = 'owner@example.com';
const OWNER_PASS = 'password';

const PAGES = [
    {
        path: '/orders',
        name: 'orders',
        primarySelector: 'main .app-frame input[placeholder*="Cari"]',
        surfaceSelector: 'main .app-frame .overflow-hidden.rounded-2xl.border.bg-card',
    },
    {
        path: '/catalog',
        name: 'catalog',
        primarySelector: 'main .app-frame [data-slot="tabs-list"]',
        surfaceSelector: 'main .app-frame .overflow-hidden.rounded-2xl.border.bg-card',
    },
    {
        path: '/reports',
        name: 'reports',
        primarySelector: 'main .app-frame [data-slot="tabs-list"]',
        surfaceSelector: 'main .app-frame [data-slot="card"]:has-text("Tren Harian")',
    },
    {
        path: '/users',
        name: 'users',
        primarySelector: 'main .app-frame .overflow-hidden.rounded-2xl.border.bg-card',
        surfaceSelector: 'main .app-frame .overflow-hidden.rounded-2xl.border.bg-card',
    },
    {
        path: '/settings',
        name: 'settings',
        primarySelector: 'main .app-frame .rounded-2xl.bg-muted.p-1',
        surfaceSelector: 'main .app-frame [data-slot="card"]',
    },
    {
        path: '/settings/ops',
        name: 'settings-ops',
        primarySelector: 'main .app-frame .rounded-2xl.bg-muted.p-1',
        surfaceSelector: 'main .app-frame [data-slot="card"]:has-text("Pengaturan PPN")',
    },
];

async function loginAsOwner(page, request) {
    const response = await request.post('/api/login', {
        data: { email: OWNER_EMAIL, password: OWNER_PASS },
    });
    expect(response.ok()).toBeTruthy();
    const { token } = await response.json();
    await page.addInitScript((value) => {
        localStorage.setItem('token', value);
    }, token);
}

async function box(page, selector) {
    const locator = page.locator(selector).first();
    await expect(locator).toBeVisible();
    return locator.evaluate((element) => {
        const rect = element.getBoundingClientRect();
        return {
            x: Math.round(rect.x),
            width: Math.round(rect.width),
            right: Math.round(rect.right),
        };
    });
}

async function frameContentBox(page) {
    return page.locator('main .app-frame').evaluate((element) => {
        const rect = element.getBoundingClientRect();
        const style = getComputedStyle(element);
        const paddingLeft = parseFloat(style.paddingLeft);
        const paddingRight = parseFloat(style.paddingRight);
        return {
            x: Math.round(rect.x + paddingLeft),
            width: Math.round(rect.width - paddingLeft - paddingRight),
            right: Math.round(rect.right - paddingRight),
        };
    });
}

function expectAligned(reference, target, label) {
    expect(Math.abs(target.width - reference.width), `${label} width`).toBeLessThanOrEqual(1);
    expect(Math.abs(target.x - reference.x), `${label} left edge`).toBeLessThanOrEqual(1);
}

test.describe('Page layout width', () => {
    test.beforeEach(async ({ page, request }) => {
        await loginAsOwner(page, request);
    });

    for (const pageInfo of PAGES) {
        test(`${pageInfo.name} aligns primary surfaces to app-frame content`, async ({ page }) => {
            await page.goto(pageInfo.path);
            await expect(page.locator('main .app-frame')).toBeVisible();

            const content = await frameContentBox(page);
            const primary = await box(page, pageInfo.primarySelector);
            const surface = await box(page, pageInfo.surfaceSelector);

            expectAligned(content, primary, `${pageInfo.name} primary`);
            expectAligned(content, surface, `${pageInfo.name} surface`);
            expectAligned(primary, surface, `${pageInfo.name} primary vs surface`);
        });
    }

    test('mobile header and main share the same frame width', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/orders');
        await expect(page.locator('header .app-frame')).toBeVisible();

        const mainFrame = await page.locator('main .app-frame').evaluate((element) => {
            const rect = element.getBoundingClientRect();
            return { x: Math.round(rect.x), width: Math.round(rect.width) };
        });
        const headerFrame = await page.locator('header .app-frame').evaluate((element) => {
            const rect = element.getBoundingClientRect();
            return { x: Math.round(rect.x), width: Math.round(rect.width) };
        });

        expectAligned(mainFrame, headerFrame, 'header frame');
    });

    test('orders, reports, and settings frames match across navigation', async ({ page }) => {
        const frames = [];

        for (const path of ['/orders', '/reports', '/settings']) {
            await page.goto(path);
            await expect(page.locator('main .app-frame')).toBeVisible();
            frames.push(await frameContentBox(page));
        }

        for (let index = 1; index < frames.length; index += 1) {
            expectAligned(frames[0], frames[index], `frame content ${index}`);
        }
    });
});
