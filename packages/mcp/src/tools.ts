import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'
import { z } from 'zod'

import type { ApiClientFactory } from './client.js'
import { errorResult, jsonResult } from './results.js'

/**
 * N-02: the read tools that give an assistant the questions I-02's routes
 * alone can't answer — search, get-one, a date range, the funnel summary and
 * the leads behind it. Every one hands back the same shape the matching
 * *ReadData class already returns on the `api` surface — public_id only, never a row's
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
  street: z.string().nullable(),
  postcode: z.string().nullable(),
  city: z.string().nullable(),
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

const crewMemberSchema = z.object({
  id: z.string(),
  userId: z.string(),
  name: z.string(),
  initials: z.string(),
  role: z.string(),
  roleLabel: z.string(),
})

const shootSchema = z.object({
  id: z.string(),
  projectId: z.string(),
  kind: z.string(),
  label: z.string().nullable(),
  date: z.string().nullable(),
  callTime: z.string().nullable(),
  endTime: z.string().nullable(),
  venueName: z.string().nullable(),
  venueCity: z.string().nullable(),
  status: z.string(),
  isPrimary: z.boolean(),
  cancelledAt: z.string().nullable(),
  cancelledReason: z.string().nullable(),
  crew: z.array(crewMemberSchema),
  updatedAt: z.string(),
})

const leadSchema = z.object({
  id: z.string(),
  projectId: z.string(),
  projectName: z.string(),
  source: z.string(),
  sourceOther: z.string().nullable(),
  stage: z.string(),
  stageEnteredAt: z.string().nullable(),
  isLost: z.boolean(),
  outcome: z.string().nullable(),
  lostReason: z.string().nullable(),
  capturedAt: z.string(),
  lostAt: z.string().nullable(),
  updatedAt: z.string(),
})

/** What GET /projects/{project} alone returns — see ProjectDetailReadData. */
const projectDetailSchema = projectSchema.extend({
  typeOther: z.string().nullable(),
  lead: z
    .object({
      stage: z.string(),
      source: z.string(),
      sourceOther: z.string().nullable(),
      inquiryText: z.string().nullable(),
      capturedVia: z.string(),
      capturedViaOther: z.string().nullable(),
      capturedAt: z.string(),
      stageEnteredAt: z.string().nullable(),
      outcome: z.string().nullable(),
      lostReason: z.string().nullable(),
      lostAt: z.string().nullable(),
    })
    .nullable(),
  job: z
    .object({
      id: z.string(),
      projectId: z.string(),
      bookedAt: z.string(),
      bookedVia: z.string(),
      updatedAt: z.string(),
    })
    .nullable(),
  openTaskCount: z.number(),
  contacts: z.array(contactSchema.extend({ role: z.string() })),
  shoots: z.array(shootSchema),
  // QuoteData, amounts in cents.
  quotes: z.array(
    z.object({
      id: z.string(),
      isAddendum: z.boolean(),
      status: z.string(),
      pricingMode: z.string(),
      number: z.string().nullable(),
      validUntil: z.string().nullable(),
      sentAt: z.string().nullable(),
      total: z.number(),
      discountType: z.string().nullable(),
      discountValue: z.number().nullable(),
      discountAmount: z.number(),
      net: z.number(),
      vatMode: z.string(),
      vatRate: z.number(),
      vatNote: z.string().nullable(),
      vatAmount: z.number(),
      intro: z.string().nullable(),
      outro: z.string().nullable(),
      hourlyRate: z.number().nullable(),
      minHours: z.number().nullable(),
      maxHours: z.number().nullable(),
      imagesPerHour: z.number().nullable(),
      hours: z.number().nullable(),
      paymentStages: z.array(
        z.object({ id: z.string(), percent: z.number(), label: z.string(), minimum: z.number() }),
      ),
      recipientContact: z.string().nullable(),
      recipientStreet: z.string().nullable(),
      recipientPostcode: z.string().nullable(),
      recipientCity: z.string().nullable(),
      portalUrl: z.string().nullable(),
      items: z.array(
        z.object({
          id: z.string(),
          title: z.string(),
          description: z.string().nullable(),
          quantity: z.number(),
          unitPrice: z.number().nullable(),
          netTotal: z.number(),
          selectable: z.boolean(),
          selected: z.boolean(),
          outcome: z.string().nullable(),
        }),
      ),
    }),
  ),
})

/** PackageData — see list_packages. */
const packageSchema = z.object({
  id: z.string(),
  name: z.string(),
  description: z.string().nullable(),
  isActive: z.boolean(),
  portalOffered: z.boolean(),
  portalPriceMode: z.string(),
  price: z.number().nullable(),
  items: z.array(
    z.object({
      id: z.string(),
      title: z.string(),
      description: z.string().nullable(),
      quantity: z.number(),
      unitPrice: z.number().nullable(),
      selectable: z.boolean(),
      selected: z.boolean(),
      outcome: z.string().nullable(),
    }),
  ),
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

export function registerReadTools(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'search_contacts',
    {
      title: 'Search contacts',
      description:
        "Lists contacts on the CRM's api surface, or searches them by name or email with `query` — the search runs over the whole contact list on the server, not the page this call returns. Every id is a public_id, never a row's internal integer id.",
      inputSchema: {
        query: z.string().optional().describe('Case-insensitive substring match against name or email.'),
        cursor: z.string().optional().describe("Opaque cursor from a previous call's nextCursor."),
        since: z.string().optional().describe('ISO 8601 datetime — only contacts updated at or after this instant.'),
        limit: z.number().int().min(1).max(100).optional().describe('Page size, up to the api surface\'s max of 100. Defaults to 25.'),
      },
      outputSchema: {
        contacts: z.array(contactSchema),
        nextCursor: z.string().nullable(),
      },
      annotations: { readOnlyHint: true },
    },
    async ({ query, cursor, since, limit }, extra) => {
      const client = clientFor(extra.authInfo?.token)

      // `cursor`/`since`/`per_page` all work at runtime but the contract
      // documents none of them: CrmReadController validates them inside its
      // shared private page() helper, not the contacts() action Scramble
      // inspects. Built as a variable for the reason shoots_in_date_range
      // below gives — a literal would be rejected for those excess keys.
      const queryParams = { query, cursor, since, per_page: limit }
      const { data, error, response } = await client.GET('/contacts', { params: { query: queryParams } })
      if (!response.ok || data === undefined) {
        return errorResult(error, response)
      }

      return jsonResult({
        contacts: data.data as unknown as z.infer<typeof contactSchema>[],
        nextCursor: data.meta.next_cursor,
      })
    },
  )

  server.registerTool(
    'get_project',
    {
      title: 'Get a project',
      description:
        "Gets a single project by its public_id, with its lead (including its inquiry text), its contacts and their roles, its shoots and its quotes — enough to verify a capture or an import without a second call. Internal notes are not part of it; they need their own scope. The project index returns the lean shape instead.",
      inputSchema: {
        publicId: z.string().describe("The project's public_id, never its internal integer id."),
      },
      outputSchema: {
        project: projectDetailSchema,
      },
      annotations: { readOnlyHint: true },
    },
    async ({ publicId }, extra) => {
      const client = clientFor(extra.authInfo?.token)

      const { data, error, response } = await client.GET('/projects/{project}', { params: { path: { project: publicId } } })
      if (!response.ok || data === undefined) {
        return errorResult(error, response)
      }

      return jsonResult({ project: data.data as unknown as z.infer<typeof projectDetailSchema> })
    },
  )

  server.registerTool(
    'list_internal_notes',
    {
      title: 'List internal notes',
      description:
        "Lists a project's staff-only internal notes, oldest first by when they happened. The ids are what update_internal_note and delete_internal_note take.",
      inputSchema: {
        publicId: z.string().describe("The project's public_id, never its internal integer id."),
      },
      outputSchema: {
        notes: z.array(
          z.object({
            id: z.string(),
            body: z.string(),
            author: z.string().nullable(),
            writtenAt: z.string(),
            occurredAt: z.string(),
          }),
        ),
      },
      annotations: { readOnlyHint: true },
    },
    async ({ publicId }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).GET('/projects/{project}/notes', {
        params: { path: { project: publicId } },
      })
      if (!response.ok || data === undefined) {
        return errorResult(error, response)
      }

      return jsonResult({ notes: data.data })
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
      annotations: { readOnlyHint: true },
    },
    async (_args, extra) => {
      const client = clientFor(extra.authInfo?.token)
      const { data, error, response } = await client.GET('/project-types')
      if (!response.ok || data === undefined) {
        return errorResult(error, response)
      }

      return jsonResult({ types: data.data as unknown as z.infer<typeof projectTypeSchema>[] })
    },
  )

  server.registerTool(
    'list_packages',
    {
      title: 'List packages',
      description:
        "The studio's packages with their lines, amounts in cents. The id is what create_quote's `package` and update_package take. `isActive` false means the package is hidden from what a new quote can start from.",
      inputSchema: {},
      outputSchema: {
        packages: z.array(packageSchema),
      },
      annotations: { readOnlyHint: true },
    },
    async (_args, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).GET('/packages')
      if (!response.ok || data === undefined) {
        return errorResult(error, response)
      }

      return jsonResult({ packages: data.data as unknown as z.infer<typeof packageSchema>[] })
    },
  )

  server.registerTool(
    'shoots_in_date_range',
    {
      title: 'Shoots in a date range',
      description:
        "Lists shoots whose date falls in [from, to], either bound optional — N-02's addition of `from`/`to` filters to I-02's GET /api/shoots, on top of the plain `since` staleness filter it already had. The question this answers: which shoots are scheduled, and by extension unstaffed, in a given window.",
      inputSchema: {
        from: z.string().optional().describe('Inclusive start date, YYYY-MM-DD. Left out, every shoot up to `to` matches.'),
        to: z.string().optional().describe('Inclusive end date, YYYY-MM-DD. Left out, every shoot from `from` onward matches.'),
        since: z.string().optional().describe('ISO 8601 datetime — only shoots updated at or after this instant.'),
        cursor: z.string().optional().describe("Opaque cursor from a previous call's nextCursor."),
        include_cancelled: z
          .boolean()
          .optional()
          .describe('Keep cancelled shoots in the result. Off by default: an absage frees the date.'),
      },
      outputSchema: {
        shoots: z.array(shootSchema),
        nextCursor: z.string().nullable(),
      },
      annotations: { readOnlyHint: true },
    },
    async ({ from, to, since, cursor, include_cancelled }, extra) => {
      const client = clientFor(extra.authInfo?.token)

      // Built as a variable rather than an inline literal: structural typing
      // (unlike the excess-property check TypeScript runs on a literal
      // passed straight into a typed slot) accepts the extra `cursor` key
      // here, since /shoots's documented query type — unlike /contacts's —
      // is a real (if incomplete) object rather than `never`.
      const queryParams = { from, to, since, cursor, ...(include_cancelled ? {} : { cancelled: 'false' as const }) }
      const { data, error, response } = await client.GET('/shoots', { params: { query: queryParams } })
      if (!response.ok || data === undefined) {
        return errorResult(error, response)
      }

      return jsonResult({
        shoots: data.data as unknown as z.infer<typeof shootSchema>[],
        nextCursor: data.meta.next_cursor,
      })
    },
  )

  server.registerTool(
    'list_leads',
    {
      title: 'List leads',
      description:
        "The rows behind `pipeline_snapshot`: which leads stand in a stage, which were closed, which came in over a window. `closed` left out returns both open and closed leads; a closed one keeps the stage it was closed in, so `stage` and `closed` narrow independently. `sourceOther` carries the platform behind source 'other'.",
      inputSchema: {
        stage: z.string().optional().describe('A lead stage key, e.g. "quote_sent" — see pipeline_snapshot for the studio\'s own stages.'),
        closed: z.boolean().optional().describe('true for lost and declined leads only, false for the ones still in play.'),
        captured_from: z.string().optional().describe('Inclusive start date the inquiry arrived on, YYYY-MM-DD.'),
        captured_to: z.string().optional().describe('Inclusive end date the inquiry arrived on, YYYY-MM-DD.'),
        cursor: z.string().optional().describe("Opaque cursor from a previous call's nextCursor."),
      },
      outputSchema: {
        leads: z.array(leadSchema),
        nextCursor: z.string().nullable(),
      },
      annotations: { readOnlyHint: true },
    },
    async ({ stage, closed, captured_from: capturedFrom, captured_to: capturedTo, cursor }, extra) => {
      const client = clientFor(extra.authInfo?.token)

      // '/leads' documents no query parameters — same Scramble blind spot as
      // '/contacts' above, and the same cast past the contract's `never`.
      const query = { stage, closed, captured_from: capturedFrom, captured_to: capturedTo, cursor } as never
      const { data, error, response } = await client.GET('/leads', { params: { query } })
      if (!response.ok || data === undefined) {
        return errorResult(error, response)
      }

      return jsonResult({
        leads: data.data as unknown as z.infer<typeof leadSchema>[],
        nextCursor: data.meta.next_cursor,
      })
    },
  )

  server.registerTool(
    'pipeline_snapshot',
    {
      title: 'Lead pipeline snapshot',
      description:
        'The sales funnel as a count per LeadStage, in funnel order — N-02\'s addition of GET /api/pipeline-snapshot, since nothing on the api surface answered "how many leads are at each stage" before this. Closed leads (lost, declined or otherwise closed) are excluded from every count; use list_leads with `closed: true` for those. Inactive stages are left out rather than shown with a 0.',
      inputSchema: {},
      outputSchema: {
        stages: z.array(pipelineStageSchema),
      },
      annotations: { readOnlyHint: true },
    },
    async (_args, extra) => {
      const client = clientFor(extra.authInfo?.token)
      const { data, error, response } = await client.GET('/pipeline-snapshot')
      if (!response.ok || data === undefined) {
        return errorResult(error, response)
      }

      return jsonResult({ stages: data.data as unknown as z.infer<typeof pipelineStageSchema>[] })
    },
  )
}
