import type { IncomingMessage, RequestListener, ServerResponse } from 'node:http'

import type { StreamableHTTPServerTransport } from '@modelcontextprotocol/sdk/server/streamableHttp.js'

import { extractBearerToken, verifyBearerToken, type McpAuthInfo } from './httpAuth.js'

declare module 'node:http' {
  interface IncomingMessage {
    /** Set once verifyBearerToken succeeds — nothing downstream re-derives who this bearer is. */
    auth?: McpAuthInfo
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
 */
export function createAuthenticatedRequestListener(
  transport: StreamableHTTPServerTransport,
  baseUrl?: string,
): RequestListener {
  return async (req: IncomingMessage, res: ServerResponse) => {
    const token = extractBearerToken(req)
    const auth = token ? await verifyBearerToken(token, baseUrl) : null

    if (!auth) {
      res.writeHead(401, { 'Content-Type': 'application/json', 'WWW-Authenticate': 'Bearer' })
      res.end(JSON.stringify({ error: 'invalid_token' }))
      return
    }

    req.auth = auth
    void transport.handleRequest(req, res)
  }
}
