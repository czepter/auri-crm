import { defineConfig } from '@playwright/test'
import { NOW } from './tests/e2e/helpers'

// Every PHP process of the suite runs at NOW (#324). A session cookie issued
// then has already expired for a browser living in real time.
// ponytail: ten years of SESSION_LIFETIME, move NOW forward before 2036.
const clock = { E2E_NOW: NOW, SESSION_LIFETIME: String(10 * 365 * 24 * 60) }

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
// cache:clear gehoert zu migrate:fresh. Der StudioSettingsResolver legt die
// Studio-Einstellungen in Redis ab, und Redis ueberlebt die frische Datenbank
// — der Name im Kopf jedes Screenshots war danach der, den die
// Entwicklungsanwendung zuletzt gesetzt hatte, nicht der geseedete.
const serve =
  'sh -c "php artisan migrate:fresh --force' +
  ' && php artisan cache:clear' +
  ' && php artisan db:seed --class=E2eSeeder --force' +
  // --no-reload, or the DB_DATABASE the compose run below sets never reaches
  // the server: ServeCommand passes only its own $passthroughVariables list
  // to the child process, and DB_* is not on it. Everything before this line
  // took the override and rebuilt studio_e2e; the server fell back to .env
  // and answered from the developer's own database, so every spec that signs
  // in as an E2eSeeder account met an account that was not there.
  ' && php artisan serve --no-reload --host=0.0.0.0 --port=8000"'

// mcp.spec.ts drives a remote MCP client through the whole OAuth flow, so it
// needs one more process than every other spec: the api surface, where the
// MCP endpoint and the tool routes behind it live (Surface::registerRoutes
// mounts routes/mcp.php and routes/api.php only under APP_SURFACE=api). CI
// only — locally the admin server already occupies the one published port of
// the compose php service, and a second `docker compose run --service-ports`
// would collide with it.
//
// ADMIN_URL is what the RFC 9728 document names as the issuer, and the
// authorize/token/register routes it points at live on the admin surface at
// :8000.
const mcpServers = [
  {
    cwd: '.',
    command: 'php artisan serve --no-reload --host=127.0.0.1 --port=8002',
    url: 'http://127.0.0.1:8002/up',
    env: {
      APP_SURFACE: 'api',
      PHP_CLI_SERVER_WORKERS: '4',
      API_URL: 'http://127.0.0.1:8002',
      ADMIN_URL: 'http://127.0.0.1:8000',
      ...clock,
    },
    timeout: 120_000,
  },
]

// visual.spec.ts dials the portal surface, which only CI and
// docker/e2e/run.sh start.
const portalServer = {
  cwd: '.',
  command: 'php artisan serve --no-reload --host=0.0.0.0 --port=8001',
  url: 'http://127.0.0.1:8001/up',
  env: { APP_SURFACE: 'portal', PHP_CLI_SERVER_WORKERS: '4', ...clock },
  timeout: 120_000,
}

export default defineConfig({
  testDir: './tests/e2e',
  // visual first: the other specs write to the fixtures its screens show.
  // mcp.spec.ts braucht den gebauten MCP-Server; CI fährt ihn in einem
  // eigenen Job hoch, lokal steht er nicht.
  projects: process.env.CI
    ? [
        { name: 'visual', testMatch: '**/visual.spec.ts' },
        { name: 'e2e', testIgnore: '**/visual.spec.ts', dependencies: ['visual'] },
      ]
    : [{ name: 'e2e', testIgnore: ['**/mcp.spec.ts', '**/visual.spec.ts'] }],
  timeout: 30_000,
  use: {
    baseURL,
    trace: 'on-first-retry',
  },
  webServer: [
    {
      cwd: process.env.CI ? '.' : '../..',
      command: process.env.CI
        ? serve
        : `docker compose run --rm --service-ports -e APP_SURFACE=admin -e DB_DATABASE=studio_e2e -e E2E_NOW -e SESSION_LIFETIME php ${serve}`,
      url: `${baseURL}/up`,
      reuseExistingServer: !process.env.CI,
      // One PHP worker serves one request at a time, so a fetch a page fires
      // while that page is still pulling assets waits behind them — which is
      // what gallery-record's five-second assertion on a minted link kept
      // losing to on CI. Respected only alongside --no-reload above.
      env: { APP_SURFACE: 'admin', PHP_CLI_SERVER_WORKERS: '4', PORTAL_URL: 'http://localhost:8001', ...clock },
      timeout: 120_000,
    },
    ...(process.env.CI ? [portalServer, ...mcpServers] : []),
  ],
})
