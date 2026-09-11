import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'
import type { CallToolResult } from '@modelcontextprotocol/sdk/types.js'
import { z } from 'zod'

import type { ApiClientFactory } from '../client.js'
import { idempotencyKeyFor } from '../idempotency.js'

/**
 * N-03: the MCP server's write tools, each a thin call onto one of I-03's
 * (and C-25's) idempotent api-surface endpoints (see routes/api.php) via the
 * generated openapi-fetch client — no DB access, no second copy of the
 * funnel's rules.
 *
 * OQ-8 (#94) made "explicit confirmation is the floor" a condition of these
 * shipping at all, and N-03 built that floor here, out of an
 * `elicitation/create` round trip before every API call. That was the wrong
 * place for it: the spec puts the confirmation prompt on the client
 * ("Clients SHOULD prompt for user confirmation on sensitive operations",
 * server/tools § Security Considerations), and elicitation is how a server
 * asks for *missing information*, not a yes/no gate on an action. Neither
 * Claude.ai nor Claude Code implements it, so the gate failed closed on
 * every write from either — the floor was a wall.
 *
 * So confirmation lives in the client now, and `annotations` below is what
 * it gates on: `readOnlyHint` false throughout, `destructiveHint` true
 * wherever a call overwrites existing state.
 */export function registerWriteTools(server: McpServer, clientFor: ApiClientFactory): void {
  registerCaptureLead(server, clientFor)
  registerCaptureLeadsBulk(server, clientFor)
  registerAdvanceLeadStage(server, clientFor)
  registerUpdateLead(server, clientFor)
  registerCloseLead(server, clientFor)
  registerReopenLead(server, clientFor)
  registerCreateShoot(server, clientFor)
  registerAdvanceShootStatus(server, clientFor)
  registerCancelShoot(server, clientFor)
  registerUncancelShoot(server, clientFor)
  registerAddInternalNote(server, clientFor)
  registerUpdateInternalNote(server, clientFor)
  registerDeleteInternalNote(server, clientFor)
  registerAddProjectContact(server, clientFor)
  registerSetProjectFields(server, clientFor)
  registerAddProjectField(server, clientFor)
  registerDeleteProject(server, clientFor)
}

/**
 * The backfill switch, on every write tool because a migration touches every
 * one of them. What it suppresses is decided server-side (see
 * App\Http\Middleware\MarkSilentWrite): the header is the whole contract
 * this side holds.
 */
const silentField = {
  silent: z
    .boolean()
    .nullish()
    .describe('For entering history that already happened: no mail, no in-app notice, no webhook, and no follow-up tasks. Use it for every write of a record from before this studio moved onto the app.'),
}

function writeHeaders(tool: string, payload: Record<string, unknown>, silent: boolean | null | undefined): Record<string, string> {
  return {
    // `silent` decides whether mail, tasks and webhooks fire, so it is part
    // of what makes two writes the same write.
    'Idempotency-Key': idempotencyKeyFor(tool, { ...payload, silent: silent === true }),
    ...(silent ? { 'X-Silent-Write': '1' } : {}),
  }
}

const leadSourceEnum = ['referral', 'website', 'instagram', 'wedding_fair', 'other'] as const
const captureChannelEnum = ['phone', 'email', 'in_person', 'other'] as const
const leadStageEnum = ['new', 'contacted', 'quote_sent', 'quote_accepted', 'contract_sent', 'booked'] as const
const leadOutcomeEnum = ['lost', 'declined'] as const

const leadFields = {
  contact_id: z.string().length(24).nullish().describe('CUID2 of an existing contact, if reusing one'),
  contact_name: z.string().max(255).nullish(),
  contact_email: z.string().email().max(255).nullish(),
  contact_phone: z.string().max(64).nullish(),
  people: z
    .array(z.object({ name: z.string().max(255), email: z.string().email().max(255).nullish(), phone: z.string().max(64).nullish() }))
    .optional()
    .describe('Everyone on the inquiry besides the contact — the partner of a wedding couple. Each becomes a contact of their own on the project.'),
  project_name: z.string().max(255),
  type: z.string().describe('The project type key — see list_project_types. A studio defines its own, so this is not a fixed list.'),
  source: z.enum(leadSourceEnum),
  source_other: z.string().max(120).nullish().describe("What 'other' actually was, e.g. the platform the inquiry came from. Only kept beside source: other."),
  captured_via: z.enum(captureChannelEnum),
  date: z.string().nullish().describe('YYYY-MM-DD'),
  inquiry_text: z.string().max(10000).nullish(),
  captured_at: z
    .string()
    .nullish()
    .describe('When the inquiry reached the studio, for one entered after the fact. ISO 8601 or YYYY-MM-DD, never in the future. Defaults to now.'),
}

function registerCaptureLead(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'capture_lead',
    {
      title: 'Capture lead',
      description: 'Create a new lead and project from an inquiry, mirroring the studio capture form.',
      inputSchema: { ...leadFields, ...silentField },
      // Additive — a new lead never overwrites anything — but still a
      // write with real consequences, so readOnlyHint stays false.
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
    },
    async ({ silent, ...args }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).POST('/leads', {
        body: args,
        headers: writeHeaders('capture_lead', args, silent),
      })

      return apiResult(data, error)
    },
  )
}

/**
 * The import tool. `silent` defaults to true here, the opposite of every other write tool: a
 * batch of leads is history being entered, and the people in it heard about
 * their enquiry when they made it.
 */
function registerCaptureLeadsBulk(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'capture_leads_bulk',
    {
      title: 'Capture leads in bulk',
      description:
        'Create many leads in one call, for moving existing client history onto this app. All or nothing: one unusable row refuses the whole batch, naming its position, so a retry cannot double-write the rows that were fine.',
      inputSchema: {
        leads: z.array(z.object(leadFields)).min(1).max(500),
        silent: z
          .boolean()
          .default(true)
          .describe('Default true, because a batch is history: no mail, no in-app notice, no webhook, no follow-up tasks. Set false only for leads that really are arriving now.'),
      },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
    },
    async ({ leads, silent }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).POST('/leads/bulk', {
        body: { leads } as never,
        headers: writeHeaders('capture_leads_bulk', { leads }, silent),
      })

      return apiResult(data, error)
    },
  )
}

function registerAdvanceLeadStage(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'advance_lead_stage',
    {
      title: 'Advance lead stage',
      description: "Move a lead to a new stage in the sales funnel (Lead.stage), the same transition the board's drag makes.",
      inputSchema: {
        lead: z.string().describe('The lead public id'),
        stage: z.enum(leadStageEnum),
        entered_at: z
          .string()
          .nullish()
          .describe('Since when it stands there, for a move entered after the fact. ISO 8601 or YYYY-MM-DD, never in the future.'),
        ...silentField,
      },
      // Overwrites the lead's current stage — a destructive update to
      // existing state, even though the resulting call is naturally
      // idempotent (advancing to the same stage twice ends in the same
      // place).
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ lead, stage, entered_at: enteredAt, silent }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).POST('/leads/{lead}/stage', {
        params: { path: { lead } },
        body: { stage, entered_at: enteredAt } as never,
        headers: writeHeaders('advance_lead_stage', { lead, stage, entered_at: enteredAt }, silent),
      })

      return apiResult(data, error)
    },
  )
}

function registerUpdateLead(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'update_lead',
    {
      title: 'Update lead',
      description: "Replace a lead's inquiry text, the one written at capture. get_project shows the current one.",
      inputSchema: {
        lead: z.string().describe('The lead public id'),
        inquiry_text: z.string().max(10000).nullable(),
      },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ lead, inquiry_text: inquiryText }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).PATCH('/leads/{lead}', {
        params: { path: { lead } },
        body: { inquiry_text: inquiryText } as never,
        headers: writeHeaders('update_lead', { lead, inquiry_text: inquiryText }, undefined),
      })

      return apiResult(data, error)
    },
  )
}

function registerCloseLead(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'close_lead',
    {
      title: 'Close lead',
      description:
        "End the pursuit. `lost` is the client's decision, `declined` the studio's — the two are counted apart, so pick the one that happened. The stage is left alone: which stage a lead died in is what the funnel is measured on. Its open shoots are cancelled with it. Reversible with reopen_lead, which takes those absagen back too.",
      inputSchema: {
        lead: z.string().describe('The lead public id'),
        outcome: z.enum(leadOutcomeEnum),
        reason: z.string().max(255),
        lost_at: z.string().nullish().describe('When it ended, for one entered after the fact. Never in the future.'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false },
    },
    async ({ lead, outcome, reason, lost_at: lostAt, silent }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).POST('/leads/{lead}/lost', {
        params: { path: { lead } },
        body: { outcome, reason, lost_at: lostAt } as never,
        headers: writeHeaders('close_lead', { lead, outcome, reason, lost_at: lostAt }, silent),
      })

      return apiResult(data, error)
    },
  )
}

function registerReopenLead(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'reopen_lead',
    {
      title: 'Reopen lead',
      description:
        'Take back a close. A closed lead can make no further move at all, so this is the only way out of one entered by mistake. It returns to the stage it was closed in.',
      inputSchema: {
        lead: z.string().describe('The lead public id'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false },
    },
    async ({ lead, silent }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).POST('/leads/{lead}/reopen', {
        params: { path: { lead } },
        headers: writeHeaders('reopen_lead', { lead }, silent),
      })

      return apiResult(data, error)
    },
  )
}

const shootKindEnum = ['engagement', 'wedding', 'reception', 'portrait', 'other'] as const

/**
 * A second shooting day on a project that already has one. `capture_lead`
 * makes exactly one shoot out of its `date`, so a wedding split over two days
 * — Trauung on one, Feier on another — could not be entered from here at all.
 */
function registerCreateShoot(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'create_shoot',
    {
      title: 'Create a shoot',
      description:
        'Add a shooting day to a project. The first one already exists — capture_lead makes it out of the inquiry date — so this is the second and every one after it: the Feier of a wedding split across two days, an Engagement-Shooting ahead of it, a second portrait session. Leave `date` empty for a day that is agreed but not dated.',
      inputSchema: {
        project: z.string().describe('The project public id'),
        kind: z.enum(shootKindEnum),
        label: z.string().max(255).nullish().describe('What this day is called on the project screen, where the kind alone is ambiguous'),
        date: z.string().nullish().describe('YYYY-MM-DD'),
        call_time: z.string().nullish().describe('HH:MM'),
        end_time: z.string().nullish().describe('HH:MM, after call_time'),
        venue_name: z.string().max(255).nullish(),
        venue_city: z.string().max(255).nullish(),
        entered_at: z.string().nullish().describe('Since when it stands in its first status, for a day entered after the fact. Never in the future.'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
    },
    async ({ project, silent, ...shoot }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).POST('/projects/{project}/shoots', {
        params: { path: { project } },
        body: shoot as never,
        headers: writeHeaders('create_shoot', { project, ...shoot }, silent),
      })

      return apiResult(data, error)
    },
  )
}

function registerAdvanceShootStatus(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'advance_shoot_status',
    {
      title: 'Advance shoot status',
      description:
        'Move one shoot through the production pipeline (Shoot.status) — the transition the project screen makes. A status below the current one is a correction of a mistaken move and restores the moment it first stood there.',
      inputSchema: {
        shoot: z.string().describe('The shoot public id'),
        status: z.string().describe("The stage key in the project pipeline, e.g. 'booked' or 'delivered'"),
        entered_at: z.string().nullish().describe('Since when it stands there. Never in the future.'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ shoot, status, entered_at: enteredAt, silent }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).POST('/shoots/{shoot}/status', {
        params: { path: { shoot } },
        body: { status, entered_at: enteredAt } as never,
        headers: writeHeaders('advance_shoot_status', { shoot, status, entered_at: enteredAt }, silent),
      })

      return apiResult(data, error)
    },
  )
}

function registerCancelShoot(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'cancel_shoot',
    {
      title: 'Cancel shoot',
      description:
        'Call off one shooting day. The status is left alone — which position it was cancelled in is what the pipeline is measured on. Reversible with uncancel_shoot.',
      inputSchema: {
        shoot: z.string().describe('The shoot public id'),
        reason: z.string().max(255),
        cancelled_at: z.string().nullish().describe('When it was called off. Never in the future.'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false },
    },
    async ({ shoot, reason, cancelled_at: cancelledAt, silent }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).POST('/shoots/{shoot}/cancel', {
        params: { path: { shoot } },
        body: { reason, cancelled_at: cancelledAt } as never,
        headers: writeHeaders('cancel_shoot', { shoot, reason, cancelled_at: cancelledAt }, silent),
      })

      return apiResult(data, error)
    },
  )
}

function registerUncancelShoot(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'uncancel_shoot',
    {
      title: 'Uncancel shoot',
      description:
        'Take back an absage. A cancelled shoot can make no further move at all, so this is the only way out of one entered by mistake.',
      inputSchema: {
        shoot: z.string().describe('The shoot public id'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false },
    },
    async ({ shoot, silent }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).POST('/shoots/{shoot}/uncancel', {
        params: { path: { shoot } },
        headers: writeHeaders('uncancel_shoot', { shoot }, silent),
      })

      return apiResult(data, error)
    },
  )
}

function registerAddInternalNote(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'add_internal_note',
    {
      title: 'Add internal note',
      description: 'Add a staff-only internal note to a project.',
      inputSchema: {
        project: z.string().describe('The project public id'),
        body: z.string().max(5000),
        occurred_at: z
          .string()
          .nullish()
          .describe('When what the note is about happened, for one written up later. ISO 8601 or YYYY-MM-DD, never in the future.'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
    },
    async ({ project, body, occurred_at, silent }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).POST('/projects/{project}/notes', {
        params: { path: { project } },
        body: { body, occurred_at } as never,
        headers: writeHeaders('add_internal_note', { project, body, occurred_at }, silent),
      })

      return apiResult(data, error)
    },
  )
}

function registerUpdateInternalNote(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'update_internal_note',
    {
      title: 'Update internal note',
      description: 'Replace the body of an internal note, by the id add_internal_note returned. occurred_at is kept unless given.',
      inputSchema: {
        note: z.string().describe('The note public id'),
        body: z.string().max(5000),
        occurred_at: z
          .string()
          .nullish()
          .describe('When what the note is about happened. ISO 8601 or YYYY-MM-DD, never in the future.'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ note, body, occurred_at, silent }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).PATCH('/notes/{note}', {
        params: { path: { note } },
        body: { body, occurred_at } as never,
        headers: writeHeaders('update_internal_note', { note, body, occurred_at }, silent),
      })

      return apiResult(data, error)
    },
  )
}

function registerDeleteInternalNote(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'delete_internal_note',
    {
      title: 'Delete internal note',
      description: 'Delete an internal note, by the id add_internal_note returned. There is no undo.',
      inputSchema: {
        note: z.string().describe('The note public id'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ note, silent }, extra) => {
      const { error } = await clientFor(extra.authInfo?.token).DELETE('/notes/{note}', {
        params: { path: { note } },
        headers: writeHeaders('delete_internal_note', { note }, silent),
      })

      // 204, no body — see delete_project.
      return error !== undefined
        ? apiResult(undefined, error)
        : { content: [{ type: 'text', text: `Deleted note ${note}.` }] }
    },
  )
}

function registerAddProjectContact(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'add_project_contact',
    {
      title: 'Add project contact',
      description:
        'Attach a person to an existing project: the partner of a couple, a payer, a planner. Pass contact_id for someone on file (see search_contacts), or name with email and phone; an email already on file reuses that contact. The primary contact is set at capture and cannot be added here.',
      inputSchema: {
        project: z.string().describe('The project public id'),
        contact_id: z.string().length(24).nullish().describe('CUID2 of an existing contact'),
        name: z.string().max(255).nullish(),
        email: z.string().email().max(255).nullish(),
        phone: z.string().max(64).nullish(),
        role: z.enum(['partner', 'payer', 'planner', 'vendor']).nullish().describe('Defaults to partner'),
        ...silentField,
      },
      // Attaching the same person in the same role twice leaves one link.
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false },
    },
    async ({ project, silent, ...person }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).POST('/projects/{project}/contacts', {
        params: { path: { project } },
        body: person as never,
        headers: writeHeaders('add_project_contact', { project, ...person }, silent),
      })

      return apiResult(data, error)
    },
  )
}

const projectFieldTypeEnum =['text', 'textarea', 'number', 'select', 'tags', 'date', 'boolean'] as const

function registerSetProjectFields(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'set_project_fields',
    {
      title: 'Set project fields',
      description:
        "C-25: write the values of a project's own fields — \"120 Gäste, Scheune\" out of a mail. Call `list_project_types` first: the keys of `fields` are that type's field keys, and each value takes the shape its definition names (a number, a string, a YYYY-MM-DD date, a boolean, or a list of strings for a tags field). Keys are merged, never replaced, so writing one field leaves the rest alone. A value outside a closed option list is refused; on a field that allows new options it is added to the list.",
      inputSchema: {
        project: z.string().describe('The project public id'),
        fields: z.record(z.string(), z.unknown()).describe('Keyed by field key — see list_project_types.'),
        ...silentField,
      },
      // Overwrites whatever stood under those keys, so destructive even
      // though re-sending the same values changes nothing.
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ project, fields, silent }, extra) => {
      // Scramble reads the `array` rule as `string[]` (see tools.ts's module
      // doc comment on why this surface's generated payloads are only
      // trustworthy for paths and params).
      const { data, error } = await clientFor(extra.authInfo?.token).PATCH('/projects/{project}/fields', {
        params: { path: { project } },
        body: { fields } as never,
        headers: writeHeaders('set_project_fields', { project, fields }, silent),
      })

      return apiResult(data, error)
    },
  )
}

function registerAddProjectField(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'add_project_field',
    {
      title: 'Add a project field',
      description:
        "C-25: define a new field on a project type, so every project of that type carries it. The key is derived from the label and fixed; `options` and `allow_new_options` apply to select and tags only, `unit` to number only. Changes what every project of this type is asked for, so only do it when the studio asked for it.",
      inputSchema: {
        project_type: z.string().describe('The project type public id — see list_project_types'),
        label: z.string().max(120),
        type: z.enum(projectFieldTypeEnum),
        options: z.array(z.string().max(255)).optional().describe('select and tags only'),
        unit: z.string().max(40).nullish().describe('number only — "Personen" makes 120 read as "120 Personen"'),
        required: z.boolean().optional(),
        allow_new_options: z.boolean().optional().describe('select and tags only: may a project add an option?'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
    },
    async ({ project_type: projectType, silent, ...field }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).POST('/project-types/{projectType}/fields', {
        params: { path: { projectType } },
        body: field,
        headers: writeHeaders('add_project_field', { projectType, ...field }, silent),
      })

      return apiResult(data, error)
    },
  )
}

/**
 * No delete_lead beside this: a lead is born with its project and means
 * nothing without it, so the project is the unit that goes.
 */
function registerDeleteProject(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'delete_project',
    {
      title: 'Delete a project',
      description:
        'Deletes a project together with its lead, its shoots, its internal notes and its links to contacts. The contacts themselves stay. Refused while a job, a quote, a contract or a gallery hangs off the project — past that point the project is a record of an agreement, and removing it is a decision for a screen rather than a tool. There is no undo.',
      inputSchema: {
        project: z.string().describe('The project public id'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ project, silent }, extra) => {
      const { error } = await clientFor(extra.authInfo?.token).DELETE('/projects/{project}', {
        params: { path: { project } },
        headers: writeHeaders('delete_project', { project }, silent),
      })

      // 204, so there is no body to hand back the way every other write tool
      // does — apiResult() would stringify `undefined` into a content block
      // with no text at all.
      return error !== undefined
        ? apiResult(undefined, error)
        : { content: [{ type: 'text', text: `Deleted project ${project}.` }] }
    },
  )
}

function apiResult(data: unknown, error: unknown): CallToolResult {
  if (error !== undefined) {
    return { isError: true, content: [{ type: 'text', text: JSON.stringify(error) }] }
  }

  return { content: [{ type: 'text', text: JSON.stringify(data) }] }
}
