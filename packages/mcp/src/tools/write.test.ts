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

function fakeApiClient(data: unknown = { id: 'fake_public_id' }) {
  const answer = async (_path: string, _options: { headers?: Record<string, string> }) => ({
    data,
    error: undefined,
    response: new Response(),
  })

  const post = vi.fn(answer)
  const patch = vi.fn(answer)

  return { post, patch, client: { POST: post, PATCH: patch } as unknown as ApiClient }
}

async function connectedPair(apiClient: ApiClient, elicitationSupported: boolean) {
  const server = new McpServer({ name: 'studio-mcp', version: '0.0.1' })
  registerWriteTools(server, () => apiClient)

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

  it('registers exactly the five write tools', async () => {
    const { client } = await connectedPair(fakeApiClient().client, true)
    clients.push(client)

    const { tools } = await client.listTools()

    expect(tools.map((tool) => tool.name).sort()).toEqual([
      'add_internal_note',
      'add_project_field',
      'advance_lead_stage',
      'capture_lead',
      'set_project_fields',
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

  it('set_project_fields PATCHes the values behind the same confirmation gate', async () => {
    const { patch, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient, true)
    clients.push(client)

    client.setRequestHandler(ElicitRequestSchema, async () => ({ action: 'decline' }))

    const declining = await client.callTool({
      name: 'set_project_fields',
      arguments: { project: 'project_public_id', fields: { gaeste: 120 } },
    })

    expect(patch).not.toHaveBeenCalled()
    expect(JSON.stringify(declining.content)).toContain('Not confirmed')

    client.setRequestHandler(ElicitRequestSchema, async () => ({ action: 'accept', content: { confirm: true } }))

    await client.callTool({
      name: 'set_project_fields',
      arguments: { project: 'project_public_id', fields: { gaeste: 120, stil: ['Boho'] } },
    })

    expect(patch).toHaveBeenCalledTimes(1)
    const [path, options] = patch.mock.calls[0]
    expect(path).toBe('/projects/{project}/fields')
    expect(options).toMatchObject({ body: { fields: { gaeste: 120, stil: ['Boho'] } } })
    expect(options.headers?.['Idempotency-Key']).toBeTruthy()
  })

  it('add_project_field POSTs one definition onto the type', async () => {
    const { post, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient, true)
    clients.push(client)

    client.setRequestHandler(ElicitRequestSchema, async () => ({ action: 'accept', content: { confirm: true } }))

    await client.callTool({
      name: 'add_project_field',
      arguments: {
        project_type: 'type_public_id',
        label: 'Location-Art',
        type: 'select',
        options: ['Scheune', 'Schloss'],
        allow_new_options: true,
      },
    })

    const [path, options] = post.mock.calls[0]

    expect(path).toBe('/project-types/{projectType}/fields')
    // The path segment travels as a param, never inside the body.
    expect(options).toMatchObject({
      params: { path: { projectType: 'type_public_id' } },
      body: { label: 'Location-Art', type: 'select', options: ['Scheune', 'Schloss'], allow_new_options: true },
    })
  })

  // N-04: NoInternalIdsTest's invariant ("no integer identifier in any JSON
  // body") extended onto the one JSON surface apps/studio's Pest suite can
  // never reach directly — a Node process's own tool result. A write tool's
  // CallToolResult.content is `JSON.stringify(data)` off the exact same
  // *Controller response I-03 already returns (see write.ts's own doc
  // comment) — public_id strings only, per LeadController/
  // InternalNoteController — so the fake API client below returns the real
  // response shape those controllers produce rather than write.test.ts's
  // usual `{ id: 'fake_public_id' }` stand-in.
  describe('never puts an integer identifier into a write tool\'s result', () => {
    async function toolResult(apiClient: ApiClient, name: string, args: Record<string, unknown>) {
      const { client } = await connectedPair(apiClient, true)
      clients.push(client)
      client.setRequestHandler(ElicitRequestSchema, async () => ({ action: 'accept', content: { confirm: true } }))

      const result = await client.callTool({ name, arguments: args })
      const [content] = result.content as { type: 'text'; text: string }[]

      return JSON.parse(content.text) as unknown
    }

    it('detects an integer id when one is present, so a clean result below actually proves something', () => {
      expect(integerIdPaths({ lead: { id: 7, stage: 'new' } })).toEqual(['$.lead.id'])
      expect(integerIdPaths({ lead: { id: 'lead_abc', stage: 'new' } })).toEqual([])
    })

    it('capture_lead', async () => {
      const { client: apiClient } = fakeApiClient({
        project: { id: 'proj_publicid1234567890ab', name: 'Voss — Portrait' },
        lead: { id: 'lead_publicid1234567890ab', stage: 'new', source: 'website' },
      })

      const data = await toolResult(apiClient, 'capture_lead', {
        contact_name: 'Nina Voss',
        project_name: 'Voss — Portrait',
        type: 'portrait',
        source: 'website',
        captured_via: 'other',
      })

      expect(integerIdPaths(data)).toEqual([])
    })

    it('advance_lead_stage', async () => {
      const { client: apiClient } = fakeApiClient({ id: 'lead_publicid1234567890ab', stage: 'contacted' })

      const data = await toolResult(apiClient, 'advance_lead_stage', {
        lead: 'lead_publicid1234567890ab',
        stage: 'contacted',
      })

      expect(integerIdPaths(data)).toEqual([])
    })

    it('set_project_fields', async () => {
      const { client: apiClient } = fakeApiClient({
        id: 'proj_publicid1234567890ab',
        name: 'Meier Hochzeit',
        type: 'wedding',
        portalVisible: false,
        fields: { gaeste: 120 },
        updatedAt: '2026-09-04T12:00:00Z',
      })

      const data = await toolResult(apiClient, 'set_project_fields', {
        project: 'proj_publicid1234567890ab',
        fields: { gaeste: 120 },
      })

      expect(integerIdPaths(data)).toEqual([])
    })

    it('add_internal_note', async () => {
      const { client: apiClient } = fakeApiClient({
        id: 'note_publicid1234567890ab',
        body: 'Gegenlicht ab 20 Uhr.',
        writtenAt: '2026-08-10T12:00:00Z',
      })

      const data = await toolResult(apiClient, 'add_internal_note', {
        project: 'project_public_id',
        body: 'Gegenlicht ab 20 Uhr.',
      })

      expect(integerIdPaths(data)).toEqual([])
    })
  })
})

/**
 * A JS port of tests/Pest.php's integerIdPaths() — same rule, same shape of
 * answer (JSONPath-ish strings), so the invariant it checks reads as the one
 * invariant everywhere else in this app enforces, not a second one that
 * happens to look similar. Lives here rather than a shared module: this is
 * the only MCP suite that needs it yet (N-02's read tools, once merged, are
 * the next candidate to dedupe this into rather than before then).
 */
function integerIdPaths(node: unknown, path = '$'): string[] {
  if (typeof node !== 'object' || node === null) return []

  const found: string[] = []

  for (const [key, value] of Object.entries(node as Record<string, unknown>)) {
    const childPath = `${path}.${key}`

    if ((key.endsWith('id') || key.endsWith('ids')) && Number.isInteger(value)) {
      found.push(childPath)
    }

    found.push(...integerIdPaths(value, childPath))
  }

  return found
}
