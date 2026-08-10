import type { IncomingMessage } from 'node:http'

/**
 * N-04: what the HTTP transport knows about the bearer that opened this
 * connection. Mirrors the MCP SDK's own `AuthInfo` shape (server/auth/
 * types.js) without depending on it — this process never runs the SDK's
 * bundled OAuth server (that lives in Laravel, see App\Http\Controllers\
 * Staff\OAuthAuthorizationController/OAuthTokenController), so only the
 * fields index.ts actually reads are declared here.
 */
export interface McpAuthInfo {
  token: string
  clientId: string
  scopes: string[]
  expiresAt?: number
}

/** A bare `Authorization: Bearer <token>` header, or null if the request never carried one. */
export function extractBearerToken(req: IncomingMessage): string | null {
  const header = req.headers.authorization
  if (!header) return null

  const [scheme, token] = header.split(' ')
  return scheme?.toLowerCase() === 'bearer' && token ? token : null
}

/**
 * The HTTP transport's whole gate: is this bearer token good, according to
 * the CRM. Not validated locally — this process holds no secret and no DB
 * connection (see client.ts's own doc comment), so "valid" can only ever
 * mean "the CRM says so." GET /api/oauth/token-info (N-04) answers for
 * either kind of bearer the `api` guard already accepts: an ApiToken (I-01,
 * a fixed scope list) or a staff user's own OAuth token (N-04, Sanctum's
 * token abilities) — this function doesn't need to know which.
 */
export async function verifyBearerToken(
  token: string,
  baseUrl: string = process.env.CRM_API_BASE_URL ?? 'http://localhost:8000/api',
): Promise<McpAuthInfo | null> {
  // Plain concatenation, not `new URL('/oauth/token-info', baseUrl)` — a
  // leading slash there would discard baseUrl's own `/api` path entirely
  // (WHATWG URL base resolution), not append to it.
  let response: Response
  try {
    response = await fetch(`${baseUrl.replace(/\/+$/, '')}/oauth/token-info`, {
      headers: { Authorization: `Bearer ${token}` },
    })
  } catch {
    return null
  }

  if (!response.ok) return null

  const body = (await response.json()) as { clientId: string; scopes: string[]; expiresAt: number | null }

  return { token, clientId: body.clientId, scopes: body.scopes, expiresAt: body.expiresAt ?? undefined }
}
