import { Client } from '@modelcontextprotocol/sdk/client/index.js'
import { InMemoryTransport } from '@modelcontextprotocol/sdk/inMemory.js'
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'
import { ElicitRequestSchema } from '@modelcontextprotocol/sdk/types.js'
import { afterEach, describe, expect, it, vi } from 'vitest'

import type { ApiClient } from '../client.js'
import { registerWriteTools } from './write.js'

// N-03's acceptance bar (issue #91): "no write tool executes without an
// explicit confirmation step, and a repeated call with the same idempotency
// key does not double-write." The idempotency-key half is covered by
// idempotency.test.ts; this file drives the tools end to end over a real
// MCP client/server pair (InMemoryTransport, no network) to prove the
// confirmation gate actually blocks the API call, not just that an
// annotation is set.

function fakeApiClient() {
  const post = vi.fn(async (_path: string, _options: { headers?: Record<string, string> }) => ({
    data: { id: 'fake_public_id' },
    error: undefined,
    response: new Response(),
  }))

  return { post, client: { POST: post } as unknown as ApiClient }
}

async function connectedPair(apiClient: ApiClient, elicitationSupported: boolean) {
  const server = new McpServer({ name: 'studio-mcp', version: '0.0.1' })
  registerWriteTools(server, apiClient)

  const client = new Client(
    { name: 'test-client', version: '0.0.1' },
    { capabilities: elicitationSupported ? { elicitation: { form: {} } } : {} },
  )

  const [clientTransport, serverTransport] = InMemoryTransport.createLinkedPair()
  await Promise.all([client.connect(clientTransport), server.connect(serverTransport)])

  return { client, server }
}

describe('registerWriteTools', () => {
  const clients: Client[] = []

  afterEach(async () => {
    await Promise.all(clients.splice(0).map((client) => client.close()))
  })

  it('registers exactly capture_lead, advance_lead_stage and add_internal_note', async () => {
    const { client } = await connectedPair(fakeApiClient().client, true)
    clients.push(client)

    const { tools } = await client.listTools()

    expect(tools.map((tool) => tool.name).sort()).toEqual([
      'add_internal_note',
      'advance_lead_stage',
      'capture_lead',
    ])
  })

  it('marks every write tool as non-read-only, with a destructive/idempotent hint set', async () => {
    const { client } = await connectedPair(fakeApiClient().client, true)
    clients.push(client)

    const { tools } = await client.listTools()

    for (const tool of tools) {
      expect(tool.annotations?.readOnlyHint, `${tool.name} must not be readOnlyHint: true`).toBe(false)
      expect(tool.annotations?.destructiveHint, `${tool.name} needs destructiveHint set`).toBeDefined()
      expect(tool.annotations?.idempotentHint, `${tool.name} needs idempotentHint set`).toBeDefined()
    }
  })

  it('does not call the API when the human declines confirmation', async () => {
    const { post, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient, true)
    clients.push(client)

    client.setRequestHandler(ElicitRequestSchema, async () => ({ action: 'decline' }))

    const result = await client.callTool({
      name: 'add_internal_note',
      arguments: { project: 'project_public_id', body: 'a note' },
    })

    expect(post).not.toHaveBeenCalled()
    expect(JSON.stringify(result.content)).toContain('Not confirmed')
  })

  it('does not call the API when a client cannot elicit confirmation at all', async () => {
    const { post, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient, false)
    clients.push(client)

    const result = await client.callTool({
      name: 'add_internal_note',
      arguments: { project: 'project_public_id', body: 'a note' },
    })

    expect(post).not.toHaveBeenCalled()
    expect(JSON.stringify(result.content)).toContain('does not support confirmation')
  })

  it('calls the API with an Idempotency-Key header once the human accepts', async () => {
    const { post, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient, true)
    clients.push(client)

    client.setRequestHandler(ElicitRequestSchema, async () => ({ action: 'accept', content: { confirm: true } }))

    await client.callTool({
      name: 'add_internal_note',
      arguments: { project: 'project_public_id', body: 'a note' },
    })

    expect(post).toHaveBeenCalledTimes(1)
    const [path, options] = post.mock.calls[0]
    expect(path).toBe('/projects/{project}/notes')
    expect(options.headers?.['Idempotency-Key']).toBeTruthy()
  })
})
