import { createServer, type Server } from 'node:http'
import type { AddressInfo } from 'node:net'

import { Client } from '@modelcontextprotocol/sdk/client/index.js'
import {
  discoverAuthorizationServerMetadata,
  discoverOAuthProtectedResourceMetadata,
  exchangeAuthorization,
  registerClient,
  startAuthorization,
} from '@modelcontextprotocol/sdk/client/auth.js'
import { StreamableHTTPClientTransport } from '@modelcontextprotocol/sdk/client/streamableHttp.js'
import { expect, test } from '@playwright/test'

import { signIn } from './helpers'

/*
 * What every other test of the MCP server stubs: the metadata a remote client
 * reads, the browser that follows the consent screen's redirect, and the token
 * the tools call the api surface with. Each of those was broken in production
 * while its own unit test passed, so this spec runs the flow claude.ai runs —
 * the SDK's own discovery, registration and token functions, a real browser in
 * the middle, and a tool call at the end — against the servers playwright.
 * config.ts starts.
 */

// The api surface serves both: the MCP endpoint at /mcp and, at the host
// root, the document that says the admin surface hands out tokens for it.
const MCP_URL = 'http://127.0.0.1:8002/mcp'
const ISSUER = 'http://127.0.0.1:8000'

/** Catches the authorization code, the way a desktop client's loopback listener does. */
async function listenForCallback(): Promise<{ server: Server; redirectUri: string }> {
  const server = createServer((_req, res) => {
    res.writeHead(200, { 'Content-Type': 'text/plain' })
    res.end('ok')
  })
  await new Promise<void>((resolve) => server.listen(0, '127.0.0.1', resolve))

  return { server, redirectUri: `http://127.0.0.1:${(server.address() as AddressInfo).port}/callback` }
}

test('a remote client registers itself, is consented to, and calls a tool', async ({ page }) => {
  const { server, redirectUri } = await listenForCallback()

  try {
    const resource = await discoverOAuthProtectedResourceMetadata(MCP_URL)
    // Absolute, not a bare host: a client cannot dial "admin-….de", and
    // Coolify's SERVICE_FQDN_* hands over exactly that.
    expect(resource.resource).toMatch(/^https?:\/\//)
    expect(resource.authorization_servers?.[0]).toMatch(/^https?:\/\//)

    const metadata = await discoverAuthorizationServerMetadata(resource.authorization_servers![0])
    const clientInformation = await registerClient(ISSUER, {
      metadata,
      clientMetadata: {
        client_name: 'e2e',
        redirect_uris: [redirectUri],
        token_endpoint_auth_method: 'none',
      },
    })

    const { authorizationUrl, codeVerifier } = await startAuthorization(ISSUER, {
      metadata,
      clientInformation,
      redirectUrl: redirectUri,
    })

    await signIn(page)
    await page.goto(authorizationUrl.href)
    await page.getByRole('button', { name: 'Erlauben' }).click()

    // The whole point of this assertion: the consent screen posts through
    // Inertia, and a plain 302 to the client is followed by fetch() rather
    // than the browser — the page then never leaves /oauth/authorize.
    await expect(page).toHaveURL(new RegExp(`^${redirectUri}\\?code=`))
    const code = new URL(page.url()).searchParams.get('code')!

    const tokens = await exchangeAuthorization(ISSUER, {
      metadata,
      clientInformation,
      authorizationCode: code,
      codeVerifier,
      redirectUri,
    })

    const client = new Client({ name: 'e2e', version: '0.0.1' })
    await client.connect(
      new StreamableHTTPClientTransport(new URL(MCP_URL), {
        requestInit: { headers: { Authorization: `Bearer ${tokens.access_token}` } },
      }),
    )

    try {
      // A tool the api surface answers from the seeded database — the tool
      // call is an api call carrying this session's bearer (ApiCall), and
      // without one the scope check behind it refuses.
      const result = (await client.callTool({ name: 'pipeline_snapshot', arguments: {} })) as {
        isError?: boolean
        content: { text: string }[]
        structuredContent?: { stages: { stage: string; count: number }[] }
      }

      expect(result.isError, result.content[0]?.text).toBeFalsy()
      const stages = result.structuredContent!.stages

      expect(stages.map((entry) => entry.stage)).toContain('new')
      expect(stages.reduce((total, entry) => total + entry.count, 0)).toBeGreaterThan(0)
    } finally {
      await client.close()
    }
  } finally {
    await new Promise<void>((resolve) => server.close(() => resolve()))
  }
})
