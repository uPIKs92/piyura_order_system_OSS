import { test, expect } from '@playwright/test';

const BASE = 'http://localhost:8084';
const OWNER_EMAIL = 'owner@example.com';
const OWNER_PASS = 'password';

test.describe('RBAC Dynamic Role Assignment', () => {
    test.beforeEach(async ({ page, request }) => {
        const r = await request.post(`${BASE}/api/login`, {
            data: { email: OWNER_EMAIL, password: OWNER_PASS },
        });
        expect(r.ok()).toBeTruthy();
        const { token } = await r.json();
        await page.goto('/login');
        await page.evaluate((t) => localStorage.setItem('token', t), token);
        await page.goto('/users');
        await page.waitForSelector('text=Pengguna', { timeout: 15000 });
    });

    test('owner can assign staff role', async ({ page }) => {
        const email = `staff${Date.now()}@example.com`;
        await page.getByRole('button', { name: /tambah/i }).click();
        await page.getByRole('textbox').nth(0).fill('Staff Baru');
        await page.getByRole('textbox').nth(1).fill(email);
        await page.locator('input[type="password"]').fill('password123');
        await page.getByRole('button', { name: 'Buat' }).click();
        await expect(page.getByText(email)).toBeVisible({ timeout: 10000 });
    });

    test('owner can promote staff to owner', async ({ page }) => {
        await page.getByRole('button', { name: 'Aksi baris' }).first().click();
        await page.getByRole('menuitem', { name: 'Ubah' }).click();
        await page.getByRole('combobox').click();
        await page.getByRole('option', { name: 'Owner' }).click();
        await page.getByRole('button', { name: 'Simpan' }).click();
        await expect(page.getByText('Disimpan')).toBeVisible({ timeout: 10000 });
    });
});
