import { randomUUID } from 'node:crypto'
import type { IncomingMessage, RequestListener, ServerResponse } from 'node:http'

import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'
import { StreamableHTTPServerTransport } from '@modelcontextprotocol/sdk/server/streamableHttp.js'

import { extractBearerToken, verifyBearerToken, type McpAuthInfo } from './httpAuth.js'
import { captureTransportError } from './observability.js'
import { createMcpServer } from './server.js'

declare module 'node:http' {
  interface IncomingMessage {
    /** Set once verifyBearerToken succeeds — nothing downstream re-derives who this bearer is. */
    auth?: McpAuthInfo
  }
}

const PROTECTED_RESOURCE_PATH = '/.well-known/oauth-protected-resource'

/** Coolify's SERVICE_FQDN_* is a bare host; RFC 8414 and 9728 want an absolute URL. */
function absoluteUrl(value: string | undefined, fallback: string): string {
  const raw = (value?.trim() || fallback).replace(/\/+$/, '')

  return /^https?:\/\//.test(raw) ? raw : `https://${raw}`
}

/** This process's own public URL — Coolify's SERVICE_FQDN_MCP in deployment. */
function publicUrl(): string {
  return absoluteUrl(process.env.MCP_PUBLIC_URL, 'http://localhost:3000')
}

/**
 * Where this process lives as far as a remote client is concerned, and who
 * hands out tokens for it. Both are read from env rather than fetched from
 * Laravel: the document is three constants, and a client that cannot reach
 * this endpoint has no way to start the flow at all — resolving it through
 * the very service it is trying to discover would be a needless second point
 * of failure.
 *
 * The issuer is the admin surface, not the api one: /oauth/authorize,
 * /oauth/token and /oauth/register all live in routes/admin.php. It is a
 * separate variable from CRM_API_BASE_URL for that reason, and because that
 * one points at a container-internal host no browser could follow.
 */
function resourceMetadata(): Record<string, unknown> {
  return {
    resource: publicUrl(),
    authorization_servers: [absoluteUrl(process.env.OAUTH_ISSUER_URL, 'http://localhost:8000')],
    bearer_methods_supported: ['header'],
  }
}

export type TransportResolver = (req: IncomingMessage) => Promise<StreamableHTTPServerTransport | null>

/**
 * One transport and one McpServer per session, not one for the whole
 * process: a StreamableHTTPServerTransport holds a single session's state,
 * so a shared instance answers the second client's initialize with 400
 * "Server already initialized" — every connect after the first since the
 * container booted, which is what claude.ai reports as a server error right
 * after a successful OAuth flow.
 */
export function sessionTransports(
  serverFactory: () => McpServer = createMcpServer,
  onError: (error: Error) => void = captureTransportError,
): TransportResolver {
  const sessions = new Map<string, StreamableHTTPServerTransport>()

  return async (req) => {
    const sessionId = req.headers['mcp-session-id']
    if (typeof sessionId === 'string') return sessions.get(sessionId) ?? null

    const transport = new StreamableHTTPServerTransport({
      sessionIdGenerator: () => randomUUID(),
      onsessioninitialized: (id) => {
        sessions.set(id, transport)
      },
    })
    transport.onclose = () => {
      if (transport.sessionId) sessions.delete(transport.sessionId)
    }
    transport.onerror = onError

    await serverFactory().connect(transport)
    return transport
  }
}

/**
 * N-04: the HTTP transport's own gate, in front of `transport.handleRequest`
 * — every request needs a bearer token the CRM accepts before it ever
 * reaches the MCP session (initialize included; a stolen or fabricated
 * token gets nothing, not even a handshake). Pulled out of index.ts so it's
 * testable directly: index.ts runs top-level side effects on import
 * (spawns a real listener), which is exactly why server.test.ts's own HTTP
 * case builds a transport by hand instead of importing it.
 *
 * The one thing served before that gate is RFC 9728's metadata document: a
 * client with no token yet reads it to find out who can give it one, so
 * gating it behind a token would close the only door in.
 */
export function createAuthenticatedRequestListener(
  transportFor: TransportResolver,
  baseUrl?: string,
): RequestListener {
  return async (req: IncomingMessage, res: ServerResponse) => {
    if (req.method === 'GET' && req.url?.split('?')[0] === PROTECTED_RESOURCE_PATH) {
      res.writeHead(200, { 'Content-Type': 'application/json' })
      res.end(JSON.stringify(resourceMetadata()))
      return
    }

    const token = extractBearerToken(req)
    const auth = token ? await verifyBearerToken(token, baseUrl) : null

    if (!auth) {
      // RFC 9728 §5.1: the 401 has to say where the metadata lives, or a
      // client that has never seen this server has nothing to go on.
      const metadataUrl = `${publicUrl()}${PROTECTED_RESOURCE_PATH}`

      res.writeHead(401, {
        'Content-Type': 'application/json',
        'WWW-Authenticate': `Bearer resource_metadata="${metadataUrl}"`,
      })
      res.end(JSON.stringify({ error: 'invalid_token' }))
      return
    }

    req.auth = auth

    const transport = await transportFor(req)
    if (!transport) {
      res.writeHead(404, { 'Content-Type': 'application/json' })
      res.end(JSON.stringify({ error: 'session_not_found' }))
      return
    }

    void transport.handleRequest(req, res)
  }
}
