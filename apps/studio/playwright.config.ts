import { defineConfig } from '@playwright/test'

// CI installs PHP natively; locally there is no host PHP, so the server runs
// in the compose container that publishes port 8000.
const serve = 'php artisan serve --host=0.0.0.0 --port=8000'

export default defineConfig({
  testDir: './tests/e2e',
  timeout: 30_000,
  use: {
    baseURL: 'http://127.0.0.1:8000',
    trace: 'on-first-retry',
  },
  webServer: {
    cwd: process.env.CI ? '.' : '../..',
    command: process.env.CI
      ? serve
      : `docker compose run --rm --service-ports -e APP_SURFACE=admin php ${serve}`,
    url: 'http://127.0.0.1:8000/up',
    reuseExistingServer: !process.env.CI,
    env: { APP_SURFACE: 'admin' },
    timeout: 120_000,
  },
})
