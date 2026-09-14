import { Client } from '@modelcontextprotocol/sdk/client/index.js'
import { InMemoryTransport } from '@modelcontextprotocol/sdk/inMemory.js'
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'
import { afterEach, describe, expect, it, vi } from 'vitest'

import type { ApiClient } from '../client.js'
import { registerMessageTools } from './messages.js'

// #404: send_message, cancel_scheduled_message and the inbox tools — the
// send half write.ts's add_message never covered. Same shape as
// write.test.ts: a real MCP client/server pair over InMemoryTransport, a
// fake openapi-fetch client standing in for the api surface.

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
  registerMessageTools(server, () => apiClient)

  const client = new Client({ name: 'test-client', version: '0.0.1' }, { capabilities: {} })

  const [clientTransport, serverTransport] = InMemoryTransport.createLinkedPair()
  await Promise.all([client.connect(clientTransport), server.connect(serverTransport)])

  return { client, server }
}

describe('registerMessageTools', () => {
  const clients: Client[] = []

  afterEach(async () => {
    await Promise.all(clients.splice(0).map((client) => client.close()))
  })

  it('registers exactly the message tools', async () => {
    const { client } = await connectedPair(fakeApiClient().client)
    clients.push(client)

    const { tools } = await client.listTools()

    expect(tools.map((tool) => tool.name).sort()).toEqual([
      'attach_inbound_mail',
      'cancel_scheduled_message',
      'close_inbox_thread',
      'file_thread_to_project',
      'list_inbox_threads',
      'reply_inbox_thread',
      'send_message',
    ])
  })

  it('flags send_message and reply_inbox_thread as reaching the client, unlike add_message', async () => {
    const { client } = await connectedPair(fakeApiClient().client)
    clients.push(client)

    const { tools } = await client.listTools()
    const byName = Object.fromEntries(tools.map((tool) => [tool.name, tool]))

    expect(byName.send_message?.annotations?.openWorldHint).toBe(true)
    expect(byName.reply_inbox_thread?.annotations?.openWorldHint).toBe(true)
    expect(byName.list_inbox_threads?.annotations?.readOnlyHint).toBe(true)
    expect(byName.close_inbox_thread?.annotations?.readOnlyHint).toBe(false)
  })

  it('sends a message on a project, with scheduled_for as body', async () => {
    const { post, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    await client.callTool({ name: 'send_message', arguments: { project: 'p1', body: 'Bis Samstag!', scheduled_for: '2026-10-01T09:00:00+02:00' } })

    expect(post.mock.calls[0][0]).toBe('/projects/{project}/messages/send')
    expect(post.mock.calls[0][1]).toMatchObject({
      params: { path: { project: 'p1' } },
      body: { body: 'Bis Samstag!', scheduled_for: '2026-10-01T09:00:00+02:00' },
    })
    expect(post.mock.calls[0][1].headers?.['Idempotency-Key']).toBeTruthy()
  })

  it('cancels a scheduled message, with no body on the 204', async () => {
    const { del, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    const result = await client.callTool({ name: 'cancel_scheduled_message', arguments: { message: 'msg_1' } })

    expect(del.mock.calls[0][0]).toBe('/messages/{message}')
    expect(JSON.stringify(result.content)).toContain('Cancelled message msg_1.')
  })

  it('lists inbox threads with a plain GET', async () => {
    const { get, client: apiClient } = fakeApiClient({ data: [{ id: 'thread_1' }] })
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    await client.callTool({ name: 'list_inbox_threads', arguments: {} })

    expect(get.mock.calls[0][0]).toBe('/inbox/threads')
  })

  it('replies, closes, attaches and files by thread or inbound mail id', async () => {
    const { post, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    await client.callTool({ name: 'reply_inbox_thread', arguments: { thread: 't1', body: 'Danke!' } })
    await client.callTool({ name: 'close_inbox_thread', arguments: { thread: 't1' } })
    await client.callTool({ name: 'attach_inbound_mail', arguments: { inbound_message: 'im1', thread: 't1' } })
    await client.callTool({ name: 'file_thread_to_project', arguments: { thread: 't1', project: 'p1' } })

    expect(post.mock.calls[0]).toMatchObject(['/inbox/threads/{thread}/reply', { params: { path: { thread: 't1' } }, body: { body: 'Danke!' } }])
    expect(post.mock.calls[1]).toMatchObject(['/inbox/threads/{thread}/close', { params: { path: { thread: 't1' } } }])
    expect(post.mock.calls[2]).toMatchObject(['/inbox/messages/{inboundMessage}/attach', { params: { path: { inboundMessage: 'im1' } }, body: { thread: 't1' } }])
    expect(post.mock.calls[3]).toMatchObject(['/inbox/threads/{thread}/file', { params: { path: { thread: 't1' } }, body: { project: 'p1' } }])
  })
})
