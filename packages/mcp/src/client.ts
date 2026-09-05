import createClient from 'openapi-fetch'

import type { paths } from './generated/openapi.js'

/**
 * The MCP server's only way to reach the CRM: a plain authenticated HTTP
 * client against packages/contracts/openapi.json, typed by `pnpm generate`
 * (openapi-typescript, see src/generated/). No DB connection, no Eloquent
 * models — a tool call is an API call, so the audit log stays complete.
 * Base URL and token are configurable because this runs as a regular
 * bearer-token client of App\Integration\Models\ApiToken (I-01), not a
 * process with any special access.
 */
export function createApiClient(
  baseUrl: string = process.env.CRM_API_BASE_URL ?? 'http://localhost:8000/api',
  token: string | undefined = process.env.CRM_API_TOKEN,
) {
  return createClient<paths>({
    baseUrl,
    headers: token ? { Authorization: `Bearer ${token}` } : undefined,
  })
}

export type ApiClient = ReturnType<typeof createApiClient>

/**
 * A client per call rather than one per process: over HTTP the token is the
 * bearer that opened the session (N-04), so a shared client built at boot
 * would call the api surface as nobody at all — 302 to /login, and an error
 * body no tool can read. Over stdio there is no such bearer and the env
 * token stays the fallback.
 */
export type ApiClientFactory = (token?: string) => ApiClient

export function apiClientFactory(): ApiClientFactory {
  return (token) => createApiClient(undefined, token)
}
