import { Client } from '@modelcontextprotocol/sdk/client/index.js'
import { InMemoryTransport } from '@modelcontextprotocol/sdk/inMemory.js'
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'
import { afterEach, describe, expect, it, vi } from 'vitest'

import type { ApiClient } from '../client.js'
import { registerBookingTaskTools } from './booking-tasks.js'

// #401: the same end-to-end shape write.test.ts uses — a real MCP
// client/server pair over InMemoryTransport, proving each tool reaches the
// api-surface route it claims with the path segments as params.

function fakeApiClient(data: unknown = { id: 'fake_public_id' }) {
  const answer = async (_path: string, _options: { headers?: Record<string, string>; body?: unknown }) => ({
    data,
    error: undefined,
    response: new Response(),
  })

  const get = vi.fn(answer)
  const post = vi.fn(answer)
  // reopen_project's DELETE answers with a body (it undoes a completion, not
  // a row); delete_task's ignores whatever comes back — see its own tool.
  const del = vi.fn(answer)

  return { get, post, del, client: { GET: get, POST: post, DELETE: del } as unknown as ApiClient }
}

async function connectedPair(apiClient: ApiClient) {
  const server = new McpServer({ name: 'studio-mcp', version: '0.0.1' })
  registerBookingTaskTools(server, () => apiClient)

  const client = new Client({ name: 'test-client', version: '0.0.1' }, { capabilities: {} })

  const [clientTransport, serverTransport] = InMemoryTransport.createLinkedPair()
  await Promise.all([client.connect(clientTransport), server.connect(serverTransport)])

  return { client, server }
}

describe('registerBookingTaskTools', () => {
  const clients: Client[] = []

  afterEach(async () => {
    await Promise.all(clients.splice(0).map((client) => client.close()))
  })

  it('registers exactly the booking and task tools', async () => {
    const { client } = await connectedPair(fakeApiClient().client)
    clients.push(client)

    const { tools } = await client.listTools()

    expect(tools.map((tool) => tool.name).sort()).toEqual([
      'assign_task',
      'book_project',
      'complete_project',
      'complete_task',
      'create_task',
      'delete_task',
      'list_jobs',
      'list_projects',
      'list_tasks',
      'reopen_project',
      'reopen_task',
      'set_task_dependency',
    ])
  })

  it('marks list_projects, list_jobs and list_tasks read-only', async () => {
    const { client } = await connectedPair(fakeApiClient().client)
    clients.push(client)

    const { tools } = await client.listTools()

    for (const name of ['list_projects', 'list_jobs', 'list_tasks']) {
      const tool = tools.find((t) => t.name === name)
      expect(tool?.annotations?.readOnlyHint, `${name} must be readOnlyHint: true`).toBe(true)
    }
  })

  it('books a project by public id', async () => {
    const { post, client: apiClient } = fakeApiClient({ id: 'job_1', projectId: 'p1', bookedAt: '2019-07-06T00:00:00Z', bookedVia: 'manual', updatedAt: '2019-07-06T00:00:00Z' })
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    await client.callTool({ name: 'book_project', arguments: { project: 'p1' } })

    expect(post.mock.calls[0][0]).toBe('/projects/{project}/book')
    expect(post.mock.calls[0][1]).toMatchObject({ params: { path: { project: 'p1' } } })
    expect(post.mock.calls[0][1].headers?.['Idempotency-Key']).toBeTruthy()
  })

  it('completes and reopens a project', async () => {
    const { post, del, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    await client.callTool({ name: 'complete_project', arguments: { project: 'p1' } })
    await client.callTool({ name: 'reopen_project', arguments: { project: 'p1' } })

    expect(post.mock.calls[0][0]).toBe('/projects/{project}/complete')
    expect(del.mock.calls[0][0]).toBe('/projects/{project}/complete')
  })

  it('lists a project’s tasks, done filter as a query param', async () => {
    const { get, client: apiClient } = fakeApiClient({ data: [{ id: 'task_1', projectId: 'p1' }] })
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    await client.callTool({ name: 'list_tasks', arguments: { project: 'p1', done: false } })

    expect(get.mock.calls[0][0]).toBe('/projects/{project}/tasks')
    expect(get.mock.calls[0][1]).toMatchObject({ params: { path: { project: 'p1' }, query: { done: 'false' } } })
  })

  it('creates a task with an anchor and offset', async () => {
    const { post, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    await client.callTool({
      name: 'create_task',
      arguments: { project: 'p1', title: 'Crew-Briefing verschicken', audience: 'internal', anchor: 'shoot:sh1', offset_days: -3 },
    })

    expect(post.mock.calls[0][0]).toBe('/projects/{project}/tasks')
    expect(post.mock.calls[0][1]).toMatchObject({
      params: { path: { project: 'p1' } },
      body: { title: 'Crew-Briefing verschicken', audience: 'internal', anchor: 'shoot:sh1', offset_days: -3 },
    })
  })

  it('completes, reopens, assigns and sets the dependency of a task by its id', async () => {
    const { post, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    await client.callTool({ name: 'complete_task', arguments: { task: 'task_1', completed_on: '2019-07-06' } })
    await client.callTool({ name: 'reopen_task', arguments: { task: 'task_1' } })
    await client.callTool({ name: 'assign_task', arguments: { task: 'task_1', assignee: 'usr_1' } })
    await client.callTool({ name: 'set_task_dependency', arguments: { task: 'task_1', depends_on: 'task_0' } })

    expect(post.mock.calls[0]).toMatchObject(['/tasks/{task}/complete', { params: { path: { task: 'task_1' } }, body: { completed_on: '2019-07-06' } }])
    expect(post.mock.calls[1][0]).toBe('/tasks/{task}/reopen')
    expect(post.mock.calls[2]).toMatchObject(['/tasks/{task}/assign', { params: { path: { task: 'task_1' } }, body: { assignee: 'usr_1' } }])
    expect(post.mock.calls[3]).toMatchObject(['/tasks/{task}/depend', { params: { path: { task: 'task_1' } }, body: { depends_on: 'task_0' } }])
  })

  it('deletes a task', async () => {
    const { del, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    const deleted = await client.callTool({ name: 'delete_task', arguments: { task: 'task_1' } })

    expect(del.mock.calls[0][0]).toBe('/tasks/{task}')
    expect(JSON.stringify(deleted.content)).toContain('Deleted task task_1.')
  })
})
