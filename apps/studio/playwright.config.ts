import { defineConfig } from '@playwright/test'

// CI installs PHP natively; locally there is no host PHP, so the server runs in
// the compose container, which publishes its port 8000 on the host as APP_PORT.
// That host port is a variable because several git worktrees of this repository
// run their own stack at once and cannot all bind the same one — see the ports
// section of docker-compose.yml. Inside the container the port never moves, so
// only the host side and the URL Playwright dials read the variable.
const hostPort = process.env.CI ? '8000' : (process.env.APP_PORT ?? '8000')
const baseURL = `http://127.0.0.1:${hostPort}`

// migrate:fresh, not just a seed. These specs create leads, so a database that
// survives between runs accumulates them and assertions start matching two
// elements on the second run — a suite whose result depends on how often it has
// been run before is not a suite. E2eSeeder is the single source of e2e
// fixtures; locally, `php artisan migrate:fresh --seed` brings the demo cast
// back for browsing.
const serve =
  'sh -c "php artisan migrate:fresh --force' +
  ' && php artisan db:seed --class=E2eSeeder --force' +
  ' && php artisan serve --host=0.0.0.0 --port=8000"'

export default defineConfig({
  testDir: './tests/e2e',
  timeout: 30_000,
  use: {
    baseURL,
    trace: 'on-first-retry',
  },
  webServer: {
    cwd: process.env.CI ? '.' : '../..',
    command: process.env.CI
      ? serve
      : `docker compose run --rm --service-ports -e APP_SURFACE=admin php ${serve}`,
    url: `${baseURL}/up`,
    reuseExistingServer: !process.env.CI,
    env: { APP_SURFACE: 'admin' },
    timeout: 120_000,
  },
})
