import { Client } from '@modelcontextprotocol/sdk/client/index.js'
import { InMemoryTransport } from '@modelcontextprotocol/sdk/inMemory.js'
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'
import { afterEach, describe, expect, it, vi } from 'vitest'

import type { ApiClient } from '../client.js'
import { registerGalleryReadTools, registerGalleryWriteTools } from './galleries.js'

function fakeApiClient(data: unknown = { id: 'gallery_1' }) {
  const answer = async (_path: string, _options: { headers?: Record<string, string>; body?: unknown }) => ({
    data,
    error: undefined,
    response: new Response(),
  })

  const get = vi.fn(async (_path: string, _options: { params?: unknown }) => ({
    data: { data, meta: { next_cursor: null } },
    error: undefined,
    response: new Response(),
  }))
  const post = vi.fn(answer)
  const patch = vi.fn(answer)

  return { get, post, patch, client: { GET: get, POST: post, PATCH: patch } as unknown as ApiClient }
}

async function connectedPair(apiClient: ApiClient) {
  const server = new McpServer({ name: 'studio-mcp', version: '0.0.1' })
  registerGalleryReadTools(server, () => apiClient)
  registerGalleryWriteTools(server, () => apiClient)

  const client = new Client({ name: 'test-client', version: '0.0.1' }, { capabilities: {} })

  const [clientTransport, serverTransport] = InMemoryTransport.createLinkedPair()
  await Promise.all([client.connect(clientTransport), server.connect(serverTransport)])

  return { client, server }
}

describe('gallery tools', () => {
  const clients: Client[] = []

  afterEach(async () => {
    await Promise.all(clients.splice(0).map((client) => client.close()))
  })

  it('registers exactly the gallery tools', async () => {
    const { client } = await connectedPair(fakeApiClient().client)
    clients.push(client)

    const { tools } = await client.listTools()

    expect(tools.map((tool) => tool.name).sort()).toEqual([
      'create_gallery',
      'create_gallery_preview_link',
      'get_gallery',
      'list_galleries',
      'list_gallery_favorites',
      'publish_gallery',
      'start_gallery_prerelease',
      'stop_gallery_prerelease',
      'update_gallery',
      'withdraw_gallery',
    ])
  })

  it('marks every read tool readOnlyHint and every write tool not', async () => {
    const { client } = await connectedPair(fakeApiClient().client)
    clients.push(client)

    const { tools } = await client.listTools()
    const byName = Object.fromEntries(tools.map((tool) => [tool.name, tool]))

    expect(byName.list_galleries.annotations?.readOnlyHint).toBe(true)
    expect(byName.get_gallery.annotations?.readOnlyHint).toBe(true)
    expect(byName.list_gallery_favorites.annotations?.readOnlyHint).toBe(true)
    expect(byName.create_gallery.annotations?.readOnlyHint).toBe(false)
    expect(byName.publish_gallery.annotations?.openWorldHint).toBe(true)
    expect(byName.stop_gallery_prerelease.annotations?.destructiveHint).toBe(true)
  })

  it('creates a gallery with project, expiry and features as body', async () => {
    const { post, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    await client.callTool({
      name: 'create_gallery',
      arguments: { project_id: 'proj_1', title: 'Verlobung', is_external: false, expires_at: '2030-01-01', downloads_enabled: false },
    })

    expect(post.mock.calls[0][0]).toBe('/galleries')
    expect(post.mock.calls[0][1]).toMatchObject({
      body: { project_id: 'proj_1', title: 'Verlobung', is_external: false, expires_at: '2030-01-01', downloads_enabled: false },
    })
  })

  it('updates a gallery by its public id, path not body', async () => {
    const { patch, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    await client.callTool({ name: 'update_gallery', arguments: { gallery: 'gallery_1', expires_at: '2030-06-01' } })

    expect(patch.mock.calls[0]).toMatchObject(['/gallery-records/{gallery}', { params: { path: { gallery: 'gallery_1' } }, body: { expires_at: '2030-06-01' } }])
  })

  it('gives two writes that differ only in silent two different Idempotency-Keys', async () => {
    const { post, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    await client.callTool({ name: 'withdraw_gallery', arguments: { gallery: 'gallery_1' } })
    await client.callTool({ name: 'withdraw_gallery', arguments: { gallery: 'gallery_1', silent: true } })

    expect(post.mock.calls[0][1].headers?.['Idempotency-Key']).not.toBe(post.mock.calls[1][1].headers?.['Idempotency-Key'])
  })

  it('lists galleries and unwraps the cursor from meta', async () => {
    const row = {
      id: 'gallery_1',
      title: 'Hochzeit Weber',
      projectId: 'proj_1',
      currentVersion: null,
      draftAssetCount: 3,
      status: 'draft',
      updatedAt: '2026-01-01T00:00:00+00:00',
    }
    const { get, client: apiClient } = fakeApiClient([row])
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    const result = await client.callTool({ name: 'list_galleries', arguments: {} })

    expect(get.mock.calls[0][0]).toBe('/galleries')
    expect((result.structuredContent as { galleries: unknown[] }).galleries).toEqual([row])
  })
})
