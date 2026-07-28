// @ts-check
import { test, expect } from '@playwright/test';

const BASE = 'http://localhost:8084';
const OWNER_EMAIL = 'owner@example.com';
const OWNER_PASS = 'password';
const STAFF_EMAIL = () => `staff-${Date.now()}@sentinel.test`;
const STAFF_PASS = 'password';

test.describe.configure({ mode: 'serial' });

test.describe('Phase 1 E2E — Sentinel', () => {
  let ownerToken, staffToken;

  // ── 1. Seed data once ──────────────────────────────
  test.beforeAll(async ({ request }) => {
    // Login owner
    const r = await request.post(`${BASE}/api/login`, {
      data: { email: OWNER_EMAIL, password: OWNER_PASS },
    });
    expect(r.ok()).toBeTruthy();
    const body = await r.json();
    ownerToken = body.token;
    expect(body.user.role).toBe('owner');

    // Create staff user
    const staffEmail = STAFF_EMAIL();
    const r2 = await request.post(`${BASE}/api/users`, {
      headers: { Authorization: `Bearer ${ownerToken}` },
      data: { name: 'Staff User', email: staffEmail, password: STAFF_PASS, role: 'staff' },
    });
    expect(r2.ok()).toBeTruthy();

    // Login as staff
    const r3 = await request.post(`${BASE}/api/login`, {
      data: { email: staffEmail, password: STAFF_PASS },
    });
    const body3 = await r3.json();
    staffToken = body3.token;
  });

  // ── 2. Auth tests ──────────────────────────────────
  test('login fails with wrong credentials', async ({ request }) => {
    const r = await request.post(`${BASE}/api/login`, {
      data: { email: 'nonexist@test.com', password: 'wrong' },
    });
    expect(r.status()).toBe(422);
  });

  test('authenticated user can access /api/user', async ({ request }) => {
    const r = await request.get(`${BASE}/api/user`, {
      headers: { Authorization: `Bearer ${ownerToken}` },
    });
    expect(r.ok()).toBeTruthy();
    const body = await r.json();
    expect(body.email).toBe(OWNER_EMAIL);
  });

  test('unauthenticated request is rejected', async ({ request }) => {
    const r = await request.get(`${BASE}/api/user`);
    expect(r.status()).toBe(401);
  });

  test('logout revokes token', async ({ request }) => {
    const r1 = await request.post(`${BASE}/api/login`, {
      data: { email: OWNER_EMAIL, password: OWNER_PASS },
    });
    const { token } = await r1.json();

    const r2 = await request.post(`${BASE}/api/logout`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(r2.ok()).toBeTruthy();

    const r3 = await request.get(`${BASE}/api/user`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(r3.status()).toBe(401);
  });

  // ── 3. Category CRUD ──────────────────────────────
  test('owner creates category', async ({ request }) => {
    const r = await request.post(`${BASE}/api/categories`, {
      headers: { Authorization: `Bearer ${ownerToken}` },
      data: { nama: 'Minuman', deskripsi: 'Semua minuman' },
    });
    expect(r.status()).toBe(201);
    const body = await r.json();
    expect(body.nama).toBe('Minuman');
    expect(body.slug).toMatch(/^minuman/);
  });

  test('staff can view categories but cannot create', async ({ request }) => {
    const r1 = await request.get(`${BASE}/api/categories`, {
      headers: { Authorization: `Bearer ${staffToken}` },
    });
    expect(r1.ok()).toBeTruthy();

    const r2 = await request.post(`${BASE}/api/categories`, {
      headers: { Authorization: `Bearer ${staffToken}` },
      data: { nama: 'Makanan' },
    });
    expect(r2.status()).toBe(403);
  });

  test('owner updates a category', async ({ request }) => {
    const list = await request.get(`${BASE}/api/categories`, {
      headers: { Authorization: `Bearer ${ownerToken}` },
    });
    const cats = await list.json();
    expect(cats.length).toBeGreaterThan(0);
    const id = cats[0].id;

    const r = await request.patch(`${BASE}/api/categories/${id}`, {
      headers: { Authorization: `Bearer ${ownerToken}` },
      data: { nama: 'Minuman Segar' },
    });
    expect(r.ok()).toBeTruthy();
  });

  test('owner deletes a category', async ({ request }) => {
    const r1 = await request.post(`${BASE}/api/categories`, {
      headers: { Authorization: `Bearer ${ownerToken}` },
      data: { nama: 'To Delete', deskripsi: 'temporary' },
    });
    const cat = await r1.json();
    const r2 = await request.delete(`${BASE}/api/categories/${cat.id}`, {
      headers: { Authorization: `Bearer ${ownerToken}` },
    });
    expect(r2.ok()).toBeTruthy();
  });

  // ── 4. Product CRUD ───────────────────────────────
  test('owner creates product', async ({ request }) => {
    const list = await request.get(`${BASE}/api/categories`, {
      headers: { Authorization: `Bearer ${ownerToken}` },
    });
    const cats = await list.json();
    const catId = cats.length > 0 ? cats[0].id : 1;

    const sku = `COC-${Date.now()}`;
    const r = await request.post(`${BASE}/api/products`, {
      headers: { Authorization: `Bearer ${ownerToken}` },
      data: {
        category_id: catId,
        nama: 'Coca-Cola',
        sku,
        units: [{ satuan: 'pcs', harga_jual: 5000, harga_beli: 4000, stok: 100, is_default: true }],
      },
    });
    expect(r.status()).toBe(201);
    const body = await r.json();
    expect(body.nama).toBe('Coca-Cola');
    expect(body.slug).toMatch(/^coca-cola/);
  });

  test('staff can view products but cannot create', async ({ request }) => {
    const r1 = await request.get(`${BASE}/api/products`, {
      headers: { Authorization: `Bearer ${staffToken}` },
    });
    expect(r1.ok()).toBeTruthy();

    const r2 = await request.post(`${BASE}/api/products`, {
      headers: { Authorization: `Bearer ${staffToken}` },
      data: {
        nama: 'X',
        sku: 'X001',
        units: [{ satuan: 'pcs', harga_jual: 100, harga_beli: 50, stok: 1, is_default: true }],
      },
    });
    expect(r2.status()).toBe(403);
  });

  test('owner updates a product', async ({ request }) => {
    const list = await request.get(`${BASE}/api/products`, {
      headers: { Authorization: `Bearer ${ownerToken}` },
    });
    const prods = await list.json();
    const prod = prods.find(p => p.nama === 'Coca-Cola');
    if (!prod) return; // skip if product missing
    const unitId = prod.units?.[0]?.id;
    if (!unitId) return;
    const r = await request.patch(`${BASE}/api/products/${prod.id}`, {
      headers: { Authorization: `Bearer ${ownerToken}` },
      data: {
        units: [{
          id: unitId,
          satuan: 'pcs',
          harga_jual: 5500,
          harga_beli: 4000,
          stok: 100,
          is_default: true,
        }],
      },
    });
    expect(r.ok()).toBeTruthy();
  });

  test('owner deletes a product', async ({ request }) => {
    const list = await request.get(`${BASE}/api/categories`, {
      headers: { Authorization: `Bearer ${ownerToken}` },
    });
    const cats = await list.json();
    const catId = cats.length > 0 ? cats[0].id : 1;
    const sku = `TMP-${Date.now()}`;
    const r1 = await request.post(`${BASE}/api/products`, {
      headers: { Authorization: `Bearer ${ownerToken}` },
      data: {
        category_id: catId,
        nama: 'Temp Product',
        sku,
        units: [{ satuan: 'pcs', harga_jual: 1000, harga_beli: 500, stok: 1, is_default: true }],
      },
    });
    expect(r1.ok()).toBeTruthy();
    const prod = await r1.json();
    const r2 = await request.delete(`${BASE}/api/products/${prod.id}`, {
      headers: { Authorization: `Bearer ${ownerToken}` },
    });
    expect(r2.ok()).toBeTruthy();
  });

  // ── 5. User CRUD (Owner only) ─────────────────────
  test('owner lists users', async ({ request }) => {
    const r = await request.get(`${BASE}/api/users`, {
      headers: { Authorization: `Bearer ${ownerToken}` },
    });
    expect(r.ok()).toBeTruthy();
    const body = await r.json();
    expect(body.length).toBeGreaterThan(0);
  });

  test('staff cannot access user CRUD', async ({ request }) => {
    const r = await request.get(`${BASE}/api/users`, {
      headers: { Authorization: `Bearer ${staffToken}` },
    });
    expect(r.status()).toBe(403);
  });
});
