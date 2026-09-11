import { Client } from '@modelcontextprotocol/sdk/client/index.js'
import { InMemoryTransport } from '@modelcontextprotocol/sdk/inMemory.js'
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'
import { afterEach, describe, expect, it, vi } from 'vitest'

import type { ApiClient } from '../client.js'
import { registerWriteTools } from './write.js'

// N-03's acceptance bar (issue #91), as OQ-8/#94 now reads it: the write
// tools carry the annotations a client gates its confirmation prompt on, and
// a repeated call with the same idempotency key does not double-write. The
// idempotency-key half is covered by idempotency.test.ts; this file drives
// the tools end to end over a real MCP client/server pair (InMemoryTransport,
// no network) to prove each one reaches the API endpoint it claims, with the
// path segments as params and `silent` as a header.

function fakeApiClient(data: unknown = { id: 'fake_public_id' }) {
  const answer = async (_path: string, _options: { headers?: Record<string, string>; body?: unknown }) => ({
    data,
    error: undefined,
    response: new Response(),
  })

  const post = vi.fn(answer)
  const patch = vi.fn(answer)
  // A 204 and no body, the way DELETE /projects/{project} answers.
  const del = vi.fn(async (_path: string, _options: { headers?: Record<string, string> }) => ({
    data: undefined,
    error: undefined,
    response: new Response(null, { status: 204 }),
  }))

  return { post, patch, del, client: { POST: post, PATCH: patch, DELETE: del } as unknown as ApiClient }
}

async function connectedPair(apiClient: ApiClient) {
  const server = new McpServer({ name: 'studio-mcp', version: '0.0.1' })
  registerWriteTools(server, () => apiClient)

  const client = new Client({ name: 'test-client', version: '0.0.1' }, { capabilities: {} })

  const [clientTransport, serverTransport] = InMemoryTransport.createLinkedPair()
  await Promise.all([client.connect(clientTransport), server.connect(serverTransport)])

  return { client, server }
}

describe('registerWriteTools', () => {
  const clients: Client[] = []

  afterEach(async () => {
    await Promise.all(clients.splice(0).map((client) => client.close()))
  })

  it('registers exactly the write tools', async () => {
    const { client } = await connectedPair(fakeApiClient().client)
    clients.push(client)

    const { tools } = await client.listTools()

    expect(tools.map((tool) => tool.name).sort()).toEqual([
      'add_internal_note',
      'add_project_contact',
      'add_project_field',
      'advance_lead_stage',
      'advance_shoot_status',
      'cancel_shoot',
      'capture_lead',
      'capture_leads_bulk',
      'close_lead',
      'create_shoot',
      'delete_project',
      'reopen_lead',
      'set_project_fields',
      'uncancel_shoot',
    ])
  })

  // Since OQ-8's floor moved into the client, these annotations are the only
  // thing a client has to gate its confirmation prompt on. A tool that
  // overwrites existing state and does not say so gets written without being
  // asked about.
  it('marks every write tool as non-read-only, naming the ones that overwrite state', async () => {
    const { client } = await connectedPair(fakeApiClient().client)
    clients.push(client)

    const { tools } = await client.listTools()

    for (const tool of tools) {
      expect(tool.annotations?.readOnlyHint, `${tool.name} must not be readOnlyHint: true`).toBe(false)
      expect(tool.annotations?.idempotentHint, `${tool.name} needs idempotentHint set`).toBeDefined()
    }

    expect(
      tools
        .filter((tool) => tool.annotations?.destructiveHint === true)
        .map((tool) => tool.name)
        .sort(),
    ).toEqual([
      'advance_lead_stage',
      'advance_shoot_status',
      'cancel_shoot',
      'close_lead',
      'delete_project',
      'set_project_fields',
    ])
  })

  it('calls the API with an Idempotency-Key header', async () => {
    const { post, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    await client.callTool({
      name: 'add_internal_note',
      arguments: { project: 'project_public_id', body: 'a note' },
    })

    expect(post).toHaveBeenCalledTimes(1)
    const [path, options] = post.mock.calls[0]
    expect(path).toBe('/projects/{project}/notes')
    expect(options.headers?.['Idempotency-Key']).toBeTruthy()
  })

  // The bug this catches shipped green: idempotency.test.ts asserted the key
  // varies with the MCP request id, and every write tool asserted only that
  // an Idempotency-Key header exists. Nothing put two calls through a client
  // and compared them — so a key that never varied read as covered.
  it('gives two different writes two different Idempotency-Keys', async () => {
    const { post, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    await client.callTool({ name: 'advance_lead_stage', arguments: { lead: 'lead_abc', stage: 'contacted', entered_at: '2018-10-29' } })
    await client.callTool({ name: 'advance_lead_stage', arguments: { lead: 'lead_abc', stage: 'booked', entered_at: '2018-12-03' } })

    expect(post).toHaveBeenCalledTimes(2)
    expect(post.mock.calls[0][1].headers?.['Idempotency-Key']).not.toBe(post.mock.calls[1][1].headers?.['Idempotency-Key'])
  })

  it('gives a retry of the same write the same Idempotency-Key', async () => {
    const { post, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    const note = { project: 'project_public_id', body: 'Gegenlicht ab 20 Uhr.' }

    await client.callTool({ name: 'add_internal_note', arguments: note })
    await client.callTool({ name: 'add_internal_note', arguments: { ...note } })

    expect(post.mock.calls[0][1].headers?.['Idempotency-Key']).toBe(post.mock.calls[1][1].headers?.['Idempotency-Key'])
  })

  it('separates two writes that differ only in silent', async () => {
    const { post, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    const lead = { contact_name: 'Nina Voss', project_name: 'Voss', type: 'portrait', source: 'referral', captured_via: 'phone' }

    await client.callTool({ name: 'capture_lead', arguments: lead })
    await client.callTool({ name: 'capture_lead', arguments: { ...lead, silent: true } })

    expect(post.mock.calls[0][1].headers?.['Idempotency-Key']).not.toBe(post.mock.calls[1][1].headers?.['Idempotency-Key'])
  })

  it('adds a contact to a project by path, the person as body', async () => {
    const { post, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    await client.callTool({ name: 'add_project_contact', arguments: { project: 'p1', name: 'Friedrich Rüffer', role: 'partner' } })

    expect(post.mock.calls[0][0]).toBe('/projects/{project}/contacts')
    expect(post.mock.calls[0][1]).toMatchObject({ params: { path: { project: 'p1' } }, body: { name: 'Friedrich Rüffer', role: 'partner' } })
  })

  // #346/#351: the type key is the studio's own vocabulary, not a fixed
  // three, and source_other is where a platform lead names its platform.
  it('carries a studio-defined type key and source_other into the body, single and bulk', async () => {
    const { post, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    const lead = {
      contact_name: 'Nina Voss',
      project_name: 'Voss — Firmenfeier',
      type: 'event',
      source: 'other',
      source_other: 'Hochzeitsportal',
      captured_via: 'phone',
    }

    await client.callTool({ name: 'capture_lead', arguments: lead })
    await client.callTool({ name: 'capture_leads_bulk', arguments: { leads: [lead] } })

    expect(post.mock.calls[0][1].body).toMatchObject({ type: 'event', source_other: 'Hochzeitsportal' })
    expect((post.mock.calls[1][1].body as { leads: Record<string, unknown>[] }).leads[0]).toMatchObject({
      type: 'event',
      source_other: 'Hochzeitsportal',
    })
  })

  it('captures a whole batch in one call, silent unless told otherwise', async () => {
    const { post, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    const leads = Array.from({ length: 12 }, (_, i) => ({
      contact_name: `Kundin ${i}`,
      project_name: `Auftrag ${i}`,
      type: 'portrait',
      source: 'referral',
      captured_via: 'phone',
    }))

    await client.callTool({ name: 'capture_leads_bulk', arguments: { leads } })

    expect(post).toHaveBeenCalledTimes(1)
    const [path, options] = post.mock.calls[0]
    expect(path).toBe('/leads/bulk')
    expect((options.body as { leads: unknown[] }).leads).toHaveLength(12)
    expect(options.headers?.['X-Silent-Write']).toBe('1')
  })

  it('lets a bulk caller opt back into notifications', async () => {
    const { post, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    await client.callTool({
      name: 'capture_leads_bulk',
      arguments: {
        leads: [{ contact_name: 'Nina Voss', project_name: 'Voss', type: 'portrait', source: 'referral', captured_via: 'phone' }],
        silent: false,
      },
    })

    expect(post.mock.calls[0][1].headers?.['X-Silent-Write']).toBeUndefined()
  })

  it('sends X-Silent-Write only when the caller asked for silence, and never in the body', async () => {
    const { post, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    const lead = { contact_name: 'Nina Voss', project_name: 'Voss', type: 'portrait', source: 'referral', captured_via: 'phone' }

    await client.callTool({ name: 'capture_lead', arguments: lead })
    await client.callTool({ name: 'capture_lead', arguments: { ...lead, silent: true } })

    expect(post.mock.calls[0][1].headers?.['X-Silent-Write']).toBeUndefined()
    expect(post.mock.calls[1][1].headers?.['X-Silent-Write']).toBe('1')
    // The server reads the header; `silent` reaching the payload would be a
    // stray key in a validated request body.
    expect(post.mock.calls[1][1].body).not.toHaveProperty('silent')
  })

  it('create_shoot POSTs a second shooting day onto the project', async () => {
    const { post, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    await client.callTool({
      name: 'create_shoot',
      arguments: { project: 'project_public_id', kind: 'reception', label: 'Feier', date: '2019-07-06', silent: true },
    })

    const [path, options] = post.mock.calls[0]

    expect(path).toBe('/projects/{project}/shoots')
    expect(options).toMatchObject({
      params: { path: { project: 'project_public_id' } },
      body: { kind: 'reception', label: 'Feier', date: '2019-07-06' },
    })
    expect(options.body).not.toHaveProperty('project')
    expect(options.headers?.['X-Silent-Write']).toBe('1')
  })

  it('set_project_fields PATCHes the values', async () => {
    const { patch, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

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
    const { client } = await connectedPair(apiClient)
    clients.push(client)

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

  // #352. The 204 is the part worth pinning: every other write tool answers
  // with JSON.stringify(data), and this one has no data to stringify.
  it('delete_project sends DELETE with the project as a path param and answers a bodyless 204', async () => {
    const { del, client: apiClient } = fakeApiClient()
    const { client } = await connectedPair(apiClient)
    clients.push(client)

    const result = await client.callTool({ name: 'delete_project', arguments: { project: 'project_public_id' } })

    const [path, options] = del.mock.calls[0]

    expect(path).toBe('/projects/{project}')
    expect(options).toMatchObject({ params: { path: { project: 'project_public_id' } } })
    expect(options.headers?.['Idempotency-Key']).toBeTruthy()
    expect(result.isError).toBeFalsy()
    expect(result.content).toEqual([{ type: 'text', text: 'Deleted project project_public_id.' }])
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
      const { client } = await connectedPair(apiClient)
      clients.push(client)

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
