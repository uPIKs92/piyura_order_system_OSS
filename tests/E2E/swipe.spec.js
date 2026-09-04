// @ts-check
import { test, expect } from '@playwright/test';

const BASE = 'http://127.0.0.1:8084';
const TEST_EMAIL = 'owner@example.com';
const TEST_PASS = 'password';

async function login(page) {
    // Load the login page first (initializes browser context cookies)
    await page.goto(`${BASE}/login`);

    // Sanctum SPA auth: get CSRF cookie via page.request (shares cookies with page)
    await page.request.get(`${BASE}/sanctum/csrf-cookie`);

    // Read XSRF-TOKEN from the page's browser context cookies
    const cookies = await page.context().cookies(BASE);
    const xsrf = cookies.find((c) => c.name === 'XSRF-TOKEN');
    const xsrfHeader = xsrf ? decodeURIComponent(xsrf.value) : '';

    // Login via page.request (shared cookie store ensures session persists)
    const r = await page.request.post(`${BASE}/api/login`, {
        data: { email: TEST_EMAIL, password: TEST_PASS },
        headers: {
            'X-XSRF-TOKEN': xsrfHeader,
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
        },
    });
    expect(r.ok(), `Login failed: ${r.status()}`).toBeTruthy();
}

test('swipe-left on pending order moves row content', async ({ page }) => {
    await login(page);

    // Enable touch support so TouchEvent/Touch constructors exist in Chromium
    // (desktop Chromium does not define them by default).
    const client = await page.context().newCDPSession(page);
    await client.send('Emulation.setTouchEmulationEnabled', { enabled: true });

    await page.goto(`${BASE}/orders`);

    // Wait for the order list to render
    await page.waitForSelector('main .app-frame button', { timeout: 15000 });

    // Find the test order
    let orderButton = page.locator('button:has-text("INV-SWIPE-TEST")');
    let usingFallback = false;
    if (!(await orderButton.count())) {
        usingFallback = true;
        orderButton = page.locator('main .app-frame button[type="button"]').first();
    }
    await expect(orderButton).toBeVisible();

    // contentRef div = button's parent (sliding layer in IosSwipeRow)
    const contentHandle = await orderButton.evaluateHandle((el) => el.parentElement);

    const before = await contentHandle.evaluate((el) => el.style.transform);
    console.log('Before:', JSON.stringify(before), 'Fallback:', usingFallback);

    const box = await orderButton.boundingBox();
    const cx = box.x + box.width / 2;
    const cy = box.y + box.height / 2;

    // Dispatch real TouchEvents (the swipe handler listens to touchstart/move/end
    // via addEventListener — page.mouse sends mouse/pointer events which no longer
    // trigger it). TouchEvents must be cancelable so preventDefault() works.
    await orderButton.evaluate(
        (el, [cx, cy]) => {
            const makeTouch = (x, y) =>
                new Touch({ identifier: 1, target: el, clientX: x, clientY: y });
            const dispatch = (type, x, y) =>
                el.dispatchEvent(
                    new TouchEvent(type, {
                        touches: type === 'touchend' ? [] : [makeTouch(x, y)],
                        changedTouches: [makeTouch(x, y)],
                        cancelable: true,
                        bubbles: true,
                    }),
                );

            dispatch('touchstart', cx, cy);
            for (let i = 1; i <= 20; i++) {
                dispatch('touchmove', cx - 5 * i, cy);
            }
        },
        [cx, cy],
    );

    const during = await contentHandle.evaluate((el) => el.style.transform);
    console.log('During:', JSON.stringify(during));

    await orderButton.evaluate(
        (el, [cx, cy]) => {
            const makeTouch = (x, y) =>
                new Touch({ identifier: 1, target: el, clientX: x, clientY: y });
            el.dispatchEvent(
                new TouchEvent('touchend', {
                    touches: [],
                    changedTouches: [makeTouch(cx - 100, cy)],
                    cancelable: true,
                    bubbles: true,
                }),
            );
        },
        [cx, cy],
    );
    await page.waitForTimeout(300);

    const after = await contentHandle.evaluate((el) => el.style.transform);
    console.log('After:', JSON.stringify(after));

    // The row content should have moved during the swipe
    expect(during).toMatch(/translateX/);
});
