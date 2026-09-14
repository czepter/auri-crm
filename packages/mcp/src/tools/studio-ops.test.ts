import { Client } from '@modelcontextprotocol/sdk/client/index.js'
import { InMemoryTransport } from '@modelcontextprotocol/sdk/inMemory.js'
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'
import { afterEach, describe, expect, it, vi } from 'vitest'

import type { ApiClient } from '../client.js'
import { registerStudioOpsTools } from './studio-ops.js'

// Same shape as write.test.ts's own fakeApiClient — a real MCP client/server
// pair (InMemoryTransport, no network) driving each tool end to end, proving
// it reaches the endpoint it claims with the right path params, body and
// headers.
function fakeApiClient(data: unknown = { id: 'fake_public_id' }) {
  const answer = async (_path: string, _options?: { headers?: Record<string, string>; body?: unknown }) => ({
    data,
    error: undefined,
    response: new Response(),
  })

  const get = vi.fn(answer)
  const post = vi.fn(answer)
  const patch = vi.fn(answer)
  const del = vi.fn(async (_path: string, _options?: { headers?: Record<string, string> }) => ({
    data: undefined,
    error: undefined,
    response: new Response(null, { status: 204 }),
  }))

  return { get, post, patch, del, client: { GET: get, POST: post, PATCH: patch, DELETE: del } as unknown as ApiClient }
}

async function connectedPair(apiClient: ApiClient) {
  const server = new McpServer({ name: 'studio-mcp', version: '0.0.1' })
  registerStudioOpsTools(server, () => apiClient)

  const client = new Client({ name: 'test-client', version: '0.0.1' }, { capabilities: {} })

  const [clientTransport, serverTransport] = InMemoryTransport.createLinkedPair()
  await Promise.all([client.connect(clientTransport), server.connect(serverTransport)])

  return { client, server }
}

describe('registerStudioOpsTools', () => {
  const clients: Client[] = []

  afterEach(async () => {
    await Promise.all(clients.splice(0).map((client) => client.close()))
  })

  it('registers exactly the studio-ops tools', async () => {
    const { client } = await connectedPair(fakeApiClient().client)
    clients.push(client)

    const { tools } = await client.listTools()

    expect(tools.map((tool) => tool.name).sort()).toEqual([
      'add_shot_list_item',
      'add_timeline_entry',
      'answer_form_field',
      'assign_crew',
      'check_shot_list_item',
      'create_form',
      'delete_shot_list_item',
      'delete_timeline_entry',
      'get_studio_settings',
      'list_crew',
      'list_forms',
      'list_number_ranges',
      'list_shoot_timeline',
      'list_shot_list',
      'list_staff',
      'list_studio_closures',
      'remove_crew',
      'set_portal_visibility',
      'share_form',
      'update_form',
    ])
  })

  it('marks every read as readOnlyHint and every write as not', async () => {
    const { client } = await connectedPair(fakeApiClient().client)
    clients.push(client)

    const { tools } = await client.listTools()
    const readOnly = tools.filter((tool) => tool.annotations?.readOnlyHint).map((tool) => tool.name).sort()

    expect(readOnly).toEqual([
      'get_studio_settings',
      'list_crew',
      'list_forms',
      'list_number_ranges',
      'list_shoot_timeline',
      'list_shot_list',
      'list_staff',
      'list_studio_closures',
    ])
  })

  it('assign_crew POSTs the shoot as a path param, user and role as body', async () => {
    const fake = fakeApiClient()
    const { client } = await connectedPair(fake.client)
    clients.push(client)

    await client.callTool({ name: 'assign_crew', arguments: { shoot: 'shoot_1', user: 'user_1', role: 'second_shooter' } })

    expect(fake.post).toHaveBeenCalledWith('/shoots/{shoot}/crew', expect.objectContaining({
      params: { path: { shoot: 'shoot_1' } },
      body: { user: 'user_1', role: 'second_shooter' },
    }))
  })

  it('remove_crew DELETEs by the assignment id and answers a bodyless 204', async () => {
    const fake = fakeApiClient()
    const { client } = await connectedPair(fake.client)
    clients.push(client)

    const result = await client.callTool({ name: 'remove_crew', arguments: { assignment: 'crew_1' } })

    expect(fake.del).toHaveBeenCalledWith('/crew/{assignment}', expect.objectContaining({ params: { path: { assignment: 'crew_1' } } }))
    expect(result.isError).toBeFalsy()
  })

  it('set_portal_visibility sends the explicit value rather than a toggle', async () => {
    const fake = fakeApiClient()
    const { client } = await connectedPair(fake.client)
    clients.push(client)

    await client.callTool({ name: 'set_portal_visibility', arguments: { project: 'project_1', visible: true } })

    expect(fake.post).toHaveBeenCalledWith('/projects/{project}/portal-visibility', expect.objectContaining({
      params: { path: { project: 'project_1' } },
      body: { visible: true },
    }))
  })

  it('update_form PATCHes the wholesale field replacement', async () => {
    const fake = fakeApiClient()
    const { client } = await connectedPair(fake.client)
    clients.push(client)

    await client.callTool({
      name: 'update_form',
      arguments: { form: 'form_1', name: 'Familie', fields: [{ label: 'Wer kommt?', type: 'text', required: true }] },
    })

    expect(fake.patch).toHaveBeenCalledWith('/forms/{formInstance}', expect.objectContaining({
      params: { path: { formInstance: 'form_1' } },
      body: { name: 'Familie', fields: [{ label: 'Wer kommt?', type: 'text', required: true }] },
    }))
  })
})
