import { createServer as createHttpServer } from 'node:http'
import type { AddressInfo } from 'node:net'

import { Client } from '@modelcontextprotocol/sdk/client/index.js'
import { StreamableHTTPClientTransport } from '@modelcontextprotocol/sdk/client/streamableHttp.js'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import type { ApiClient } from './client.js'
import { createAuthenticatedRequestListener, sessionTransports } from './http.js'
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
    httpServer = createHttpServer(createAuthenticatedRequestListener(sessionTransports(), 'http://crm.test/api'))
    await new Promise<void>((resolve) => httpServer.listen(0, resolve))
    port = (httpServer.address() as AddressInfo).port
  })

  afterEach(async () => {
    await Promise.all(clients.splice(0).map((client) => client.close()))
    await new Promise<void>((resolve) => httpServer.close(() => resolve()))
    vi.unstubAllGlobals()
    vi.unstubAllEnvs()
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

  it('makes a bare Coolify FQDN absolute in the metadata', async () => {
    vi.stubEnv('MCP_PUBLIC_URL', 'mcp.example.test')
    vi.stubEnv('OAUTH_ISSUER_URL', 'admin.example.test')

    const response = await fetch(`http://127.0.0.1:${port}/.well-known/oauth-protected-resource`)

    await expect(response.json()).resolves.toMatchObject({
      resource: 'https://mcp.example.test',
      authorization_servers: ['https://admin.example.test'],
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

  // A shared transport answers this second handshake with 400 "Server
  // already initialized" — claude.ai reports that as a connect error right
  // after the OAuth flow it just completed.
  it('completes a second handshake against the same running process', async () => {
    vi.stubGlobal(
      'fetch',
      mockTokenInfoFetch(
        () => new Response(JSON.stringify({ clientId: 'usr_abc', scopes: ['manage_leads'] }), { status: 200 }),
      ),
    )

    for (const name of ['first-client', 'second-client']) {
      const client = new Client({ name, version: '0.0.1' })
      clients.push(client)
      await client.connect(
        new StreamableHTTPClientTransport(new URL(`http://127.0.0.1:${port}/mcp`), {
          requestInit: { headers: { Authorization: 'Bearer good-token' } },
        }),
      )

      expect(client.getServerVersion()).toEqual({ name: 'studio-mcp', version: '0.0.1' })
    }
  })
})

// Its own server pair, outside the describe above: this one hands
// createMcpServer a stand-in factory to see which token the tools build
// their api client with, where every case above wants the real one.
it('builds the api client with the bearer that opened the session', async () => {
  vi.stubGlobal(
    'fetch',
    mockTokenInfoFetch(
      () => new Response(JSON.stringify({ clientId: 'usr_abc', scopes: ['manage_leads'] }), { status: 200 }),
    ),
  )

  const seen: (string | undefined)[] = []
  const serverFactory = () =>
    createMcpServer((token) => {
      seen.push(token)
      return { GET: async () => ({ data: { data: [] } }) } as unknown as ApiClient
    })

  const httpServer = createHttpServer(
    createAuthenticatedRequestListener(sessionTransports(serverFactory), 'http://crm.test/api'),
  )
  await new Promise<void>((resolve) => httpServer.listen(0, resolve))
  const port = (httpServer.address() as AddressInfo).port

  const client = new Client({ name: 'test-client', version: '0.0.1' })
  await client.connect(new StreamableHTTPClientTransport(new URL(`http://127.0.0.1:${port}/mcp`), {
    requestInit: { headers: { Authorization: 'Bearer session-token' } },
  }))
  await client.callTool({ name: 'list_project_types', arguments: {} })

  expect(seen).toEqual(['session-token'])

  await client.close()
  await new Promise<void>((resolve) => httpServer.close(() => resolve()))
  vi.unstubAllGlobals()
})
