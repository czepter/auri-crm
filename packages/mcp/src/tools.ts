import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'
import { z } from 'zod'

import type { createApiClient } from './client.js'

type ApiClient = ReturnType<typeof createApiClient>

/**
 * N-02: the read tools that give an assistant the four questions I-02's
 * routes alone can't answer — search, get-one, a date range, and a funnel
 * summary. Every one hands back the same shape the matching *ReadData class
 * already returns on the `api` surface — public_id only, never a row's
 * internal integer id (apps/studio/app/Integration/Data/*.php).
 *
 * These schemas are typed by hand rather than off `src/generated/
 * openapi.d.ts`: Scramble's OSS tier can't resolve a Spatie Data return type
 * into field-level schema (see packages/contracts/openapi.json — every one
 * of I-02's *ReadData responses collapses to `{ [key: string]: unknown }`,
 * and the paginated ones even worse, to `string[]`), so the generated types
 * are only trustworthy for the path and the params here, not the payload.
 */

const contactSchema = z.object({
  id: z.string(),
  name: z.string(),
  email: z.string().nullable(),
  phone: z.string().nullable(),
  updatedAt: z.string(),
})

const projectSchema = z.object({
  id: z.string(),
  name: z.string(),
  type: z.string(),
  portalVisible: z.boolean(),
  // C-25: keyed by ProjectField.key — list_project_types says what each means.
  fields: z.record(z.string(), z.unknown()),
  updatedAt: z.string(),
})

const shootSchema = z.object({
  id: z.string(),
  projectId: z.string(),
  kind: z.string(),
  label: z.string().nullable(),
  date: z.string().nullable(),
  status: z.string(),
  updatedAt: z.string(),
})

const projectFieldSchema = z.object({
  id: z.string(),
  key: z.string(),
  label: z.string(),
  type: z.string(),
  options: z.array(z.string()),
  unit: z.string().nullable(),
  required: z.boolean(),
  allowNewOptions: z.boolean(),
})

const projectTypeSchema = z.object({
  id: z.string(),
  key: z.string(),
  name: z.string(),
  isActive: z.boolean(),
  isDefault: z.boolean(),
  fields: z.array(projectFieldSchema),
})

const pipelineStageSchema = z.object({
  stage: z.string(),
  label: z.string(),
  count: z.number(),
})

/**
 * Every error body on this surface is at least `{ message: string }` —
 * Laravel's own exception-to-JSON shape — so that's the one thing worth
 * lifting into a tool error rather than a thrown exception that would kill
 * the whole server process over one bad call.
 */
function apiErrorMessage(error: unknown): string {
  if (error && typeof error === 'object' && 'message' in error && typeof error.message === 'string') {
    return error.message
  }
  return 'The api surface returned an error with no message.'
}

function errorResult(error: unknown): { isError: true; content: [{ type: 'text'; text: string }] } {
  return { isError: true, content: [{ type: 'text', text: apiErrorMessage(error) }] }
}

function jsonResult<T extends Record<string, unknown>>(structuredContent: T): {
  content: [{ type: 'text'; text: string }]
  structuredContent: T
} {
  return { content: [{ type: 'text', text: JSON.stringify(structuredContent) }], structuredContent }
}

export function registerReadTools(server: McpServer, client: ApiClient): void {
  server.registerTool(
    'search_contacts',
    {
      title: 'Search contacts',
      description:
        "Lists contacts on the CRM's api surface. I-02's GET /api/contacts has no server-side name/email search — only cursor pagination and a `since` staleness filter — so passing `query` here filters client-side over the one page returned, not the whole contact list. ponytail: page-local filter, add server-side search on /api/contacts if a studio's contact list outgrows one page. Every id is a public_id, never a row's internal integer id.",
      inputSchema: {
        query: z.string().optional().describe('Case-insensitive substring match against name or email, applied to the fetched page only.'),
        cursor: z.string().optional().describe("Opaque cursor from a previous call's nextCursor."),
        since: z.string().optional().describe('ISO 8601 datetime — only contacts updated at or after this instant.'),
      },
      outputSchema: {
        contacts: z.array(contactSchema),
        nextCursor: z.string().nullable(),
      },
    },
    async ({ query, cursor, since }) => {
      // '/contacts' documents no query parameters at all (see the module
      // doc comment) even though `cursor`/`since` both work at runtime —
      // Scramble never sees them because CrmReadController validates them
      // inside its shared private page() helper, not the contacts() action
      // Scramble actually inspects. Cast past the contract's `never` rather
      // than widen it for every route on this surface.
      const { data, error } = await client.GET('/contacts', { params: { query: { cursor, since } as never } })
      if (error) {
        return errorResult(error)
      }

      let contacts = data.data as unknown as z.infer<typeof contactSchema>[]
      if (query) {
        const needle = query.toLowerCase()
        contacts = contacts.filter(
          (contact) =>
            contact.name.toLowerCase().includes(needle) || (contact.email?.toLowerCase().includes(needle) ?? false),
        )
      }

      return jsonResult({ contacts, nextCursor: data.meta.next_cursor })
    },
  )

  server.registerTool(
    'get_project',
    {
      title: 'Get a project',
      description:
        "Gets a single project by its public_id — N-02's addition to I-02's api surface, which previously only had a project index, never a single-resource read.",
      inputSchema: {
        publicId: z.string().describe("The project's public_id, never its internal integer id."),
      },
      outputSchema: {
        project: projectSchema,
      },
    },
    async ({ publicId }) => {
      const { data, error } = await client.GET('/projects/{project}', { params: { path: { project: publicId } } })
      if (error) {
        return errorResult(error)
      }

      return jsonResult({ project: data.data as unknown as z.infer<typeof projectSchema> })
    },
  )

  server.registerTool(
    'list_project_types',
    {
      title: 'List project types',
      description:
        "C-25: every project type the studio offers with the fields a project of that type carries — the vocabulary `set_project_fields` writes against. `key` is what a value is stored under, `type` says what shape it takes (text, textarea, number, select, tags, date, boolean), `options` is the list a select or tags field offers, and `allowNewOptions` says whether writing an option that is not on that list adds it. Inactive fields are left out: a caller may only write what the studio still asks for.",
      inputSchema: {},
      outputSchema: {
        types: z.array(projectTypeSchema),
      },
    },
    async () => {
      const { data, error } = await client.GET('/project-types')
      if (error) {
        return errorResult(error)
      }

      return jsonResult({ types: data.data as unknown as z.infer<typeof projectTypeSchema>[] })
    },
  )

  server.registerTool(
    'shoots_in_date_range',
    {
      title: 'Shoots in a date range',
      description:
        "Lists shoots whose date falls in [from, to], both inclusive — N-02's addition of `from`/`to` filters to I-02's GET /api/shoots, which previously only supported `since` staleness polling. The question this answers: which shoots are scheduled, and by extension unstaffed, in a given window.",
      inputSchema: {
        from: z.string().describe('Inclusive start date, YYYY-MM-DD.'),
        to: z.string().describe('Inclusive end date, YYYY-MM-DD.'),
        cursor: z.string().optional().describe("Opaque cursor from a previous call's nextCursor."),
      },
      outputSchema: {
        shoots: z.array(shootSchema),
        nextCursor: z.string().nullable(),
      },
    },
    async ({ from, to, cursor }) => {
      // Built as a variable rather than an inline literal: structural typing
      // (unlike the excess-property check TypeScript runs on a literal
      // passed straight into a typed slot) accepts the extra `cursor` key
      // here, since /shoots's documented query type — unlike /contacts's —
      // is a real (if incomplete) object rather than `never`.
      const queryParams = { from, to, cursor }
      const { data, error } = await client.GET('/shoots', { params: { query: queryParams } })
      if (error) {
        return errorResult(error)
      }

      return jsonResult({
        shoots: data.data as unknown as z.infer<typeof shootSchema>[],
        nextCursor: data.meta.next_cursor,
      })
    },
  )

  server.registerTool(
    'pipeline_snapshot',
    {
      title: 'Lead pipeline snapshot',
      description:
        'The sales funnel as a count per LeadStage, in funnel order — N-02\'s addition of GET /api/pipeline-snapshot, since nothing on the api surface answered "how many leads are at each stage" before this.',
      inputSchema: {},
      outputSchema: {
        stages: z.array(pipelineStageSchema),
      },
    },
    async () => {
      const { data, error } = await client.GET('/pipeline-snapshot')
      if (error) {
        return errorResult(error)
      }

      return jsonResult({ stages: data.data as unknown as z.infer<typeof pipelineStageSchema>[] })
    },
  )
}
