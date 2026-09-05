import { randomUUID } from 'node:crypto'
import { createServer as createHttpServer } from 'node:http'
import type { AddressInfo } from 'node:net'

import { Client } from '@modelcontextprotocol/sdk/client/index.js'
import { StreamableHTTPClientTransport } from '@modelcontextprotocol/sdk/client/streamableHttp.js'
import { StreamableHTTPServerTransport } from '@modelcontextprotocol/sdk/server/streamableHttp.js'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { createAuthenticatedRequestListener } from './http.js'
import { createMcpServer } from './server.js'

// N-04's acceptance bar: the HTTP transport validates an incoming bearer
// token against the CRM's own OAuth mechanism (GET /oauth/token-info) before
// a request — including the initialize handshake itself — ever reaches the
// MCP session. Real StreamableHTTPServerTransport/Client pair over a real
// net.Server, same shape as server.test.ts's own HTTP case; only the
// listener differs, and only fetch (verifyBearerToken's one dependency) is
// mocked.

// Client and server share this one process's event loop (and its one global
// fetch) in this test — a blanket fetch stub would also swallow the test
// client's own request to the local server below, since that request is
// itself made over fetch. Only the CRM's /oauth/token-info call is faked;
// everything else falls through to the real implementation.
const realFetch = globalThis.fetch
function mockTokenInfoFetch(tokenInfoResponse: () => Response) {
  return vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = typeof input === 'string' ? input : input.toString()
    return url.endsWith('/oauth/token-info') ? tokenInfoResponse() : realFetch(input, init)
  })
}

describe('createAuthenticatedRequestListener', () => {
  const clients: Client[] = []
  let httpServer: ReturnType<typeof createHttpServer>
  let port: number

  beforeEach(async () => {
    const transport = new StreamableHTTPServerTransport({ sessionIdGenerator: () => randomUUID() })
    await createMcpServer().connect(transport)

    httpServer = createHttpServer(createAuthenticatedRequestListener(transport, 'http://crm.test/api'))
    await new Promise<void>((resolve) => httpServer.listen(0, resolve))
    port = (httpServer.address() as AddressInfo).port
  })

  afterEach(async () => {
    await Promise.all(clients.splice(0).map((client) => client.close()))
    await new Promise<void>((resolve) => httpServer.close(() => resolve()))
    vi.unstubAllGlobals()
  })

  it('refuses a connection with no bearer token at all', async () => {
    const response = await fetch(`http://127.0.0.1:${port}/mcp`, { method: 'POST' })

    expect(response.status).toBe(401);
    expect(response.headers.get('WWW-Authenticate')).toContain('Bearer')
  })

  it('points an unauthenticated caller at its own RFC 9728 metadata', async () => {
    const response = await fetch(`http://127.0.0.1:${port}/mcp`, { method: 'POST' })

    expect(response.headers.get('WWW-Authenticate')).toContain(
      'resource_metadata="http://localhost:3000/.well-known/oauth-protected-resource"',
    )
  })

  it('serves the protected resource metadata without a token', async () => {
    const response = await fetch(`http://127.0.0.1:${port}/.well-known/oauth-protected-resource`)

    expect(response.status).toBe(200)
    await expect(response.json()).resolves.toMatchObject({
      resource: 'http://localhost:3000',
      // The admin surface, where /oauth/authorize actually lives — never
      // CRM_API_BASE_URL, which points at a container-internal host.
      authorization_servers: ['http://localhost:8000'],
    })
  })

  it('refuses a bearer token the CRM does not accept', async () => {
    vi.stubGlobal('fetch', mockTokenInfoFetch(() => new Response(null, { status: 401 })))

    const response = await fetch(`http://127.0.0.1:${port}/mcp`, {
      method: 'POST',
      headers: { Authorization: 'Bearer garbage', 'Content-Type': 'application/json' },
      body: '{}',
    })

    expect(response.status).toBe(401)
  })

  it('completes the MCP handshake once the CRM accepts the bearer token', async () => {
    vi.stubGlobal(
      'fetch',
      mockTokenInfoFetch(
        () => new Response(JSON.stringify({ clientId: 'usr_abc', scopes: ['manage_leads'] }), { status: 200 }),
      ),
    )

    const client = new Client({ name: 'test-client', version: '0.0.1' })
    clients.push(client)
    await client.connect(new StreamableHTTPClientTransport(new URL(`http://127.0.0.1:${port}/mcp`), {
      requestInit: { headers: { Authorization: 'Bearer good-token' } },
    }))

    expect(client.getServerVersion()).toEqual({ name: 'studio-mcp', version: '0.0.1' })
  })
})
