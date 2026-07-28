import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/E2E',
  timeout: 30000,
  retries: 0,
  use: {
    baseURL: 'http://localhost:8084',
  },
  projects: [
    {
      name: 'api',
      testMatch: '**/{auth,rbac}.spec.js',
      use: {
        extraHTTPHeaders: { Accept: 'application/json' },
      },
    },
    {
      name: 'ui-mobile',
      testMatch: '**/layout-width.spec.js',
      use: {
        ...devices['Pixel 5'],
      },
    },
    {
      name: 'ui-desktop',
      testMatch: '**/layout-width.spec.js',
      use: {
        viewport: { width: 1280, height: 800 },
      },
    },
  ],
});
