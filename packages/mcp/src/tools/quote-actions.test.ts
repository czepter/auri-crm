import { Client } from '@modelcontextprotocol/sdk/client/index.js'
import { InMemoryTransport } from '@modelcontextprotocol/sdk/inMemory.js'
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'
import { afterEach, describe, expect, it, vi } from 'vitest'

import type { ApiClient } from '../client.js'
import { registerQuoteActionTools } from './quote-actions.js'

// #403's tools, end to end over a real MCP client/server pair
// (InMemoryTransport, no network) — the same shape write.test.ts drives
// tools/write.ts's tools with.

function fakeApiClient(data: unknown = { id: 'fake_public_id' }) {
  const answer = async (_path: string, _options: { headers?: Record<string, string>; body?: unknown }) => ({
    data,
    error: undefined,
    response: new Response(),
  })

  const get = vi.fn(answer)
  const post = vi.fn(answer)
  const del = vi.fn(async (_path: string, _options: { headers?: Record<string, string> }) => ({
    data: undefined,
    error: undefined,
    response: new Response(null, { status: 204 }),
  }))

  return { get, post, del, client: { GET: get, POST: post, DELETE: del } as unknown as ApiClient }
}

async function connectedPair(apiClient: ApiClient) {
  const server = new McpServer({ name: 'studio-mcp', version: '0.0.1' })
  registerQuoteActionTools(server, () => apiClient)

  const client = new Client({ name: 'test-client', version: '0.0.1' }, { capabilities: {} })

  const [clientTransport, serverTransport] = InMemoryTransport.createLinkedPair()
  await Promise.all([client.connect(clientTransport), server.connect(serverTransport)])

  return { client, server }
}

describe('registerQuoteActionTools', () => {
  const clients: Client[] = []

  afterEach(async () => {
    await Promise.all(clients.splice(0).map((client) => client.close()))
  })

  it('registers exactly the quote action and service suggestion tools', async () => {
    const { client } = await connectedPair(fakeApiClient().client)
    clients.push(client)

    const { tools } = await client.listTools()

    expect(tools.map((tool) => tool.name).sort()).toEqual([
      'adopt_quote_recipient',
      'create_quote_addendum',
      'delete_quote',
      'delete_service_suggestion',
      'duplicate_quote',
      'list_service_suggestions',
      'mark_quote_sent',
      'save_quote_as_package',
      'suggest_service',
    ])
  })

  it('marks list_service_suggestions read-only and every write tool not read-only', async () => {
    const { client } = await connectedPair(fakeApiClient().client)
    clients.push(client)

    const { tools } = await client.listTools()

    for (const tool of tools) {
      const expected = tool.name === 'list_service_suggestions'
      expect(tool.annotations?.readOnlyHint, `${tool.name} readOnlyHint`).toBe(expected)
    }
  })

  it('creates an addendum on the accepted quote', async () => {
    const { post, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    await client.callTool({ name: 'create_quote_addendum', arguments: { quote: 'quote_1' } })

    expect(post.mock.calls[0][0]).toBe('/quotes/{quote}/addendum')
    expect(post.mock.calls[0][1]).toMatchObject({ params: { path: { quote: 'quote_1' } } })
  })

  it('deletes a quote with no body content', async () => {
    const { del, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    const deleted = await client.callTool({ name: 'delete_quote', arguments: { quote: 'quote_1' } })

    expect(del.mock.calls[0][0]).toBe('/quotes/{quote}')
    expect(JSON.stringify(deleted.content)).toContain('Deleted quote quote_1.')
  })

  it('saves a single line of a quote as a package', async () => {
    const { post, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    await client.callTool({ name: 'save_quote_as_package', arguments: { quote: 'quote_1', name: 'Nur Album', item: 'item_1' } })

    expect(post.mock.calls[0][0]).toBe('/quotes/{quote}/save-as-package')
    expect(post.mock.calls[0][1]).toMatchObject({
      params: { path: { quote: 'quote_1' } },
      body: { name: 'Nur Album', item: 'item_1' },
    })
  })

  it('lists a contact’s service suggestions by GET', async () => {
    const { get, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    await client.callTool({ name: 'list_service_suggestions', arguments: { contact: 'contact_1' } })

    expect(get.mock.calls[0][0]).toBe('/contacts/{contact}/service-suggestions')
    expect(get.mock.calls[0][1]).toMatchObject({ params: { path: { contact: 'contact_1' } } })
  })

  it('suggests a service with the package and note as body', async () => {
    const { post, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    await client.callTool({ name: 'suggest_service', arguments: { contact: 'contact_1', package: 'pkg_1', note: 'Passt gut.' } })

    expect(post.mock.calls[0][0]).toBe('/contacts/{contact}/service-suggestions')
    expect(post.mock.calls[0][1]).toMatchObject({
      params: { path: { contact: 'contact_1' } },
      body: { package: 'pkg_1', note: 'Passt gut.' },
    })
  })

  it('deletes a service suggestion by id', async () => {
    const { del, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    const deleted = await client.callTool({ name: 'delete_service_suggestion', arguments: { suggestion: 'sugg_1' } })

    expect(del.mock.calls[0][0]).toBe('/service-suggestions/{serviceSuggestion}')
    expect(del.mock.calls[0][1]).toMatchObject({ params: { path: { serviceSuggestion: 'sugg_1' } } })
    expect(JSON.stringify(deleted.content)).toContain('Deleted service suggestion sugg_1.')
  })
})
