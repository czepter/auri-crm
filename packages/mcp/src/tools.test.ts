import { Client } from '@modelcontextprotocol/sdk/client/index.js'
import { InMemoryTransport } from '@modelcontextprotocol/sdk/inMemory.js'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { createApiClient } from './client.js'
import { createMcpServer } from './server.js'

/**
 * N-02's tool-registration and client-call wiring, tested the way N-01's
 * server.test.ts tests transports: a real MCP Client talking to a real
 * McpServer, connected in-process (InMemoryTransport) rather than over
 * stdio/HTTP — the transport itself is already proven, this only needs to
 * prove tool registration and the client call each tool makes. `fetch` is
 * stubbed rather than hitting a live api surface: these are wiring tests,
 * not I-02's own route tests (see apps/studio/tests/Feature/Integration/
 * CrmReadEndpointsTest.php for that).
 */

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

/** Keyed on pathname + method, the two things every call here varies on. */
function stubFetch(handlers: Record<string, (url: URL) => Response>): void {
  vi.stubGlobal(
    'fetch',
    vi.fn(async (input: string | URL | Request) => {
      const url = new URL(typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url)
      const handler = handlers[url.pathname]
      if (!handler) {
        throw new Error(`Unhandled request in test: ${url.pathname}`)
      }
      return handler(url)
    }),
  )
}

async function connectedClient(handlers: Record<string, (url: URL) => Response>): Promise<Client> {
  stubFetch(handlers)

  const server = createMcpServer(() => createApiClient('http://mcp-test.invalid/api', 'test-token'))
  const client = new Client({ name: 'test-client', version: '0.0.1' })
  const [clientTransport, serverTransport] = InMemoryTransport.createLinkedPair()

  await Promise.all([client.connect(clientTransport), server.connect(serverTransport)])

  return client
}

/**
 * Walks a tool result's structuredContent looking for a key ending in "id"
 * (case-insensitive) whose value is a JS number rather than a string — the
 * TypeScript-side version of tests/Pest.php's integerIdPaths(), for the
 * same reason: every id this surface hands out is a public_id string, and a
 * number under an *Id key is exactly what a leaked internal id looks like.
 */
function integerIdPaths(node: unknown, path = '$'): string[] {
  if (node === null || typeof node !== 'object') {
    return []
  }

  const found: string[] = []

  for (const [key, value] of Object.entries(node as Record<string, unknown>)) {
    const childPath = `${path}.${key}`

    if (/id$/i.test(key) && typeof value === 'number') {
      found.push(childPath)
    }

    found.push(...integerIdPaths(value, childPath))
  }

  return found
}

describe('read tools', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('registers the read tools', async () => {
    const client = await connectedClient({})

    const { tools } = await client.listTools()

    // Containment, not exact equality: createMcpServer() also registers
    // N-03's write tools on the same server — this checks read
    // registration specifically, not the server's full tool inventory.
    expect(tools.map((tool) => tool.name).sort()).toEqual(
      expect.arrayContaining([
        'get_project',
        'list_leads',
        'list_project_types',
        'pipeline_snapshot',
        'search_contacts',
        'shoots_in_date_range',
      ])
    )
  })

  it('search_contacts wraps GET /contacts and carries its cursor through', async () => {
    const client = await connectedClient({
      '/api/contacts': () =>
        jsonResponse({
          data: [
            { id: 'ctc_alice', name: 'Alice Example', email: 'alice@example.com', phone: null, updatedAt: '2026-01-01T00:00:00Z' },
            { id: 'ctc_bob', name: 'Bob Example', email: 'bob@example.com', phone: null, updatedAt: '2026-01-02T00:00:00Z' },
          ],
          meta: { path: '/contacts', per_page: 25, next_cursor: 'cur_abc', next_page_url: null, prev_cursor: null, prev_page_url: null },
        }),
    })

    const result = await client.callTool({ name: 'search_contacts', arguments: {} })

    expect(result.isError).toBeFalsy()
    expect(result.structuredContent).toEqual({
      contacts: [
        { id: 'ctc_alice', name: 'Alice Example', email: 'alice@example.com', phone: null, updatedAt: '2026-01-01T00:00:00Z' },
        { id: 'ctc_bob', name: 'Bob Example', email: 'bob@example.com', phone: null, updatedAt: '2026-01-02T00:00:00Z' },
      ],
      nextCursor: 'cur_abc',
    })
    expect(integerIdPaths(result.structuredContent)).toEqual([])
  })

  // #353: the search is the server's, so the tool has to hand the term over
  // rather than sieve the one page it was given back.
  it('search_contacts sends its query term to the server and returns the page as it comes', async () => {
    let requestedQuery: string | null = null

    const client = await connectedClient({
      '/api/contacts': (url) => {
        requestedQuery = url.search
        return jsonResponse({
          data: [
            { id: 'ctc_alice', name: 'Alice Example', email: 'alice@example.com', phone: null, updatedAt: '2026-01-01T00:00:00Z' },
          ],
          meta: { path: '/contacts', per_page: 25, next_cursor: null, next_page_url: null, prev_cursor: null, prev_page_url: null },
        })
      },
    })

    const result = await client.callTool({ name: 'search_contacts', arguments: { query: 'alice' } })

    expect(requestedQuery).toContain('query=alice')
    expect(result.structuredContent).toEqual({
      contacts: [{ id: 'ctc_alice', name: 'Alice Example', email: 'alice@example.com', phone: null, updatedAt: '2026-01-01T00:00:00Z' }],
      nextCursor: null,
    })
  })

  // #350: the lead, the contacts and the shoots come with it — a payload
  // that only validates against the richer output schema, so a get_project
  // that fell back to the index shape would fail here rather than pass.
  it('get_project wraps GET /projects/{project} by public_id, with lead, contacts and shoots', async () => {
    const detail = {
      id: 'prj_meier',
      name: 'Meier Hochzeit',
      type: 'wedding',
      typeOther: null,
      portalVisible: true,
      fields: { gaeste: 120 },
      updatedAt: '2026-01-01T00:00:00Z',
      lead: { stage: 'booked', source: 'referral', sourceOther: null, inquiryText: 'Standesamt Jena, Juni.', capturedVia: 'email', capturedViaOther: null, capturedAt: '2025-11-02T09:00:00Z', stageEnteredAt: null, outcome: null, lostReason: null, lostAt: null },
      contacts: [
        { id: 'ctc_meier', name: 'Lena Meier', email: 'lena@example.com', phone: null, role: 'primary', updatedAt: '2026-01-01T00:00:00Z' },
      ],
      shoots: [
        { id: 'sht_1', projectId: 'prj_meier', kind: 'wedding', label: null, date: '2026-06-20', callTime: null, endTime: null, venueName: null, venueCity: null, status: 'requested', cancelledAt: null, cancelledReason: null, updatedAt: '2026-01-01T00:00:00Z' },
      ],
    }

    const client = await connectedClient({ '/api/projects/prj_meier': () => jsonResponse({ data: detail }) })

    const result = await client.callTool({ name: 'get_project', arguments: { publicId: 'prj_meier' } })

    expect(result.isError).toBeFalsy()
    expect(result.structuredContent).toEqual({ project: detail })
    expect(integerIdPaths(result.structuredContent)).toEqual([])
  })

  it('get_project surfaces a 404 as a tool error rather than throwing', async () => {
    const client = await connectedClient({
      '/api/projects/unknown': () => jsonResponse({ message: 'No query results for model.' }, 404),
    })

    const result = await client.callTool({ name: 'get_project', arguments: { publicId: 'unknown' } })

    expect(result.isError).toBe(true)
    expect(JSON.stringify(result.content)).toContain('No query results for model.')
  })

  it('shoots_in_date_range wraps GET /shoots with from/to query params', async () => {
    let requestedQuery: string | null = null

    const client = await connectedClient({
      '/api/shoots': (url) => {
        requestedQuery = url.search
        return jsonResponse({
          data: [
            { id: 'sht_1', projectId: 'prj_meier', kind: 'wedding', label: null, date: '2026-03-15', callTime: null, endTime: null, venueName: null, venueCity: null, status: 'requested', cancelledAt: null, cancelledReason: null, updatedAt: '2026-01-01T00:00:00Z' },
          ],
          meta: { path: '/shoots', per_page: 25, next_cursor: null, next_page_url: null, prev_cursor: null, prev_page_url: null },
        })
      },
    })

    const result = await client.callTool({
      name: 'shoots_in_date_range',
      arguments: { from: '2026-03-01', to: '2026-03-31' },
    })

    expect(requestedQuery).toContain('from=2026-03-01')
    expect(requestedQuery).toContain('to=2026-03-31')
    expect(requestedQuery).toContain('cancelled=false')
    expect(result.structuredContent).toEqual({
      shoots: [
        { id: 'sht_1', projectId: 'prj_meier', kind: 'wedding', label: null, date: '2026-03-15', callTime: null, endTime: null, venueName: null, venueCity: null, status: 'requested', cancelledAt: null, cancelledReason: null, updatedAt: '2026-01-01T00:00:00Z' },
      ],
      nextCursor: null,
    })
    expect(integerIdPaths(result.structuredContent)).toEqual([])
  })

  it('shoots_in_date_range drops the cancelled filter for include_cancelled', async () => {
    let requestedQuery: string | null = null

    const client = await connectedClient({
      '/api/shoots': (url) => {
        requestedQuery = url.search
        return jsonResponse({
          data: [
            { id: 'sht_1', projectId: 'prj_meier', kind: 'wedding', label: null, date: '2026-03-15', callTime: null, endTime: null, venueName: null, venueCity: null, status: 'requested', cancelledAt: '2026-02-01T00:00:00Z', cancelledReason: null, updatedAt: '2026-02-01T00:00:00Z' },
          ],
          meta: { path: '/shoots', per_page: 25, next_cursor: null, next_page_url: null, prev_cursor: null, prev_page_url: null },
        })
      },
    })

    const result = await client.callTool({
      name: 'shoots_in_date_range',
      arguments: { from: '2026-03-01', to: '2026-03-31', include_cancelled: true },
    })

    expect(requestedQuery).not.toContain('cancelled=')
    expect(result.structuredContent).toEqual({
      shoots: [
        { id: 'sht_1', projectId: 'prj_meier', kind: 'wedding', label: null, date: '2026-03-15', callTime: null, endTime: null, venueName: null, venueCity: null, status: 'requested', cancelledAt: '2026-02-01T00:00:00Z', cancelledReason: null, updatedAt: '2026-02-01T00:00:00Z' },
      ],
      nextCursor: null,
    })
  })

  it('list_leads wraps GET /leads with its filters and carries sourceOther through', async () => {
    let requestedQuery: string | null = null

    const client = await connectedClient({
      '/api/leads': (url) => {
        requestedQuery = url.search
        return jsonResponse({
          data: [
            { id: 'led_1', projectId: 'prj_meier', source: 'other', sourceOther: 'Hochzeitsportal', stage: 'quote_sent', isLost: false, capturedAt: '2026-03-02T09:00:00Z', lostAt: null, updatedAt: '2026-03-02T09:00:00Z' },
          ],
          meta: { path: '/leads', per_page: 25, next_cursor: null, next_page_url: null, prev_cursor: null, prev_page_url: null },
        })
      },
    })

    const result = await client.callTool({
      name: 'list_leads',
      arguments: { stage: 'quote_sent', closed: false, captured_from: '2026-03-01', captured_to: '2026-03-31' },
    })

    expect(requestedQuery).toContain('stage=quote_sent')
    expect(requestedQuery).toContain('closed=false')
    expect(requestedQuery).toContain('captured_from=2026-03-01')
    expect(requestedQuery).toContain('captured_to=2026-03-31')
    expect(result.structuredContent).toEqual({
      leads: [{ id: 'led_1', projectId: 'prj_meier', source: 'other', sourceOther: 'Hochzeitsportal', stage: 'quote_sent', isLost: false, capturedAt: '2026-03-02T09:00:00Z', lostAt: null, updatedAt: '2026-03-02T09:00:00Z' }],
      nextCursor: null,
    })
    expect(integerIdPaths(result.structuredContent)).toEqual([])
  })

  it('list_project_types wraps GET /project-types with the fields of each type', async () => {
    const wedding = {
      id: 'ptp_wedding',
      key: 'wedding',
      name: 'Hochzeit',
      isActive: true,
      isDefault: true,
      fields: [
        {
          id: 'pfl_gaeste',
          key: 'gaeste',
          label: 'Gäste',
          type: 'number',
          options: [],
          unit: 'Personen',
          required: true,
          allowNewOptions: false,
        },
      ],
    }

    const client = await connectedClient({ '/api/project-types': () => jsonResponse({ data: [wedding] }) })

    const result = await client.callTool({ name: 'list_project_types', arguments: {} })

    expect(result.isError).toBeFalsy()
    expect(result.structuredContent).toEqual({ types: [wedding] })
    expect(integerIdPaths(result.structuredContent)).toEqual([])
  })

  it('pipeline_snapshot wraps GET /pipeline-snapshot', async () => {
    const client = await connectedClient({
      '/api/pipeline-snapshot': () =>
        jsonResponse({
          data: [
            { stage: 'new', label: 'Neu', count: 2 },
            { stage: 'contacted', label: 'Kontaktiert', count: 1 },
          ],
        }),
    })

    const result = await client.callTool({ name: 'pipeline_snapshot', arguments: {} })

    expect(result.isError).toBeFalsy()
    expect(result.structuredContent).toEqual({
      stages: [
        { stage: 'new', label: 'Neu', count: 2 },
        { stage: 'contacted', label: 'Kontaktiert', count: 1 },
      ],
    })
  })
})
