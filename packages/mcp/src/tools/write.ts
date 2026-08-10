import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'
import type { CallToolResult } from '@modelcontextprotocol/sdk/types.js'
import { z } from 'zod'

import type { ApiClient } from '../client.js'
import { idempotencyKeyFor } from '../idempotency.js'

/**
 * N-03: the MCP server's only three write tools, each a thin call onto one
 * of I-03's idempotent api-surface endpoints (see routes/api.php) via the
 * generated openapi-fetch client — no DB access, no second copy of the
 * funnel's rules. OQ-8 (#94) decided these ship at all, on the condition
 * that "explicit confirmation is the floor": every handler below elicits an
 * explicit accept from the human in the loop, over MCP's own
 * `elicitation/create` request, before it ever calls the API. Declining,
 * cancelling or a client that doesn't support elicitation at all all fail
 * closed — no confirmation means no write.
 *
 * `annotations.destructiveHint`/`readOnlyHint`/`idempotentHint` are set too,
 * for clients that gate on tool annotations rather than (or in addition to)
 * elicitation — the spec is explicit that these are hints only, never a
 * substitute for the actual confirmation step.
 */
export function registerWriteTools(server: McpServer, apiClient: ApiClient): void {
  registerCaptureLead(server, apiClient)
  registerAdvanceLeadStage(server, apiClient)
  registerAddInternalNote(server, apiClient)
}

const leadSourceEnum = ['referral', 'website', 'instagram', 'wedding_fair', 'other'] as const
const projectTypeEnum = ['wedding', 'portrait', 'other'] as const
const captureChannelEnum = ['phone', 'email', 'in_person', 'other'] as const
const leadStageEnum = ['new', 'contacted', 'quote_sent', 'quote_accepted', 'contract_sent', 'booked'] as const

function registerCaptureLead(server: McpServer, apiClient: ApiClient): void {
  server.registerTool(
    'capture_lead',
    {
      title: 'Capture lead',
      description: 'Create a new lead and project from an inquiry, mirroring the studio capture form.',
      inputSchema: {
        contact_id: z.string().length(24).nullish().describe('CUID2 of an existing contact, if reusing one'),
        contact_name: z.string().max(255).nullish(),
        contact_email: z.string().email().max(255).nullish(),
        contact_phone: z.string().max(64).nullish(),
        partner_name: z.string().max(255).nullish(),
        project_name: z.string().max(255),
        type: z.enum(projectTypeEnum),
        source: z.enum(leadSourceEnum),
        captured_via: z.enum(captureChannelEnum),
        date: z.string().nullish().describe('YYYY-MM-DD'),
        inquiry_text: z.string().max(10000).nullish(),
      },
      // Additive — a new lead never overwrites anything — but still a
      // write with real consequences, so readOnlyHint stays false.
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
    },
    async (args, extra) => {
      const declined = await requireConfirmation(server, `Capture a new lead: "${args.project_name}"?`)
      if (declined) return declined

      const { data, error } = await apiClient.POST('/leads', {
        body: args,
        headers: { 'Idempotency-Key': idempotencyKeyFor('capture_lead', extra) },
      })

      return apiResult(data, error)
    },
  )
}

function registerAdvanceLeadStage(server: McpServer, apiClient: ApiClient): void {
  server.registerTool(
    'advance_lead_stage',
    {
      title: 'Advance lead stage',
      description: "Move a lead to a new stage in the sales funnel (Lead.stage), the same transition the board's drag makes.",
      inputSchema: {
        lead: z.string().describe('The lead public id'),
        stage: z.enum(leadStageEnum),
      },
      // Overwrites the lead's current stage — a destructive update to
      // existing state, even though the resulting call is naturally
      // idempotent (advancing to the same stage twice ends in the same
      // place).
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ lead, stage }, extra) => {
      const declined = await requireConfirmation(server, `Advance lead ${lead} to stage "${stage}"?`)
      if (declined) return declined

      const { data, error } = await apiClient.POST('/leads/{lead}/stage', {
        params: { path: { lead } },
        body: { stage },
        headers: { 'Idempotency-Key': idempotencyKeyFor('advance_lead_stage', extra) },
      })

      return apiResult(data, error)
    },
  )
}

function registerAddInternalNote(server: McpServer, apiClient: ApiClient): void {
  server.registerTool(
    'add_internal_note',
    {
      title: 'Add internal note',
      description: 'Add a staff-only internal note to a project.',
      inputSchema: {
        project: z.string().describe('The project public id'),
        body: z.string().max(5000),
      },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
    },
    async ({ project, body }, extra) => {
      const declined = await requireConfirmation(server, `Add this note to project ${project}?\n\n${body}`)
      if (declined) return declined

      const { data, error } = await apiClient.POST('/projects/{project}/notes', {
        params: { path: { project } },
        body: { body },
        headers: { 'Idempotency-Key': idempotencyKeyFor('add_internal_note', extra) },
      })

      return apiResult(data, error)
    },
  )
}

/**
 * The confirmation step itself: an `elicitation/create` request asking the
 * human in the loop to accept or decline, in plain terms, before any API
 * call happens. Returns a `CallToolResult` explaining the outcome when the
 * write should NOT proceed — declined, cancelled, or a client that never
 * declared the `elicitation` capability at all, which the SDK's own
 * `Server.elicitInput` rejects outright rather than silently skipping. All
 * three fail closed here: no confirmation, no write. Returns `undefined`
 * only when the human explicitly accepted.
 */
async function requireConfirmation(server: McpServer, message: string): Promise<CallToolResult | undefined> {
  let result
  try {
    result = await server.server.elicitInput({
      message,
      requestedSchema: {
        type: 'object',
        properties: {
          confirm: { type: 'boolean', title: 'Confirm', description: 'Yes to proceed with this write.' },
        },
        required: ['confirm'],
      },
    })
  } catch {
    return {
      content: [
        { type: 'text', text: 'This client does not support confirmation — no write was made.' },
      ],
    }
  }

  if (result.action === 'accept' && result.content?.confirm === true) {
    return undefined
  }

  return { content: [{ type: 'text', text: 'Not confirmed — no write was made.' }] }
}

function apiResult(data: unknown, error: unknown): CallToolResult {
  if (error !== undefined) {
    return { isError: true, content: [{ type: 'text', text: JSON.stringify(error) }] }
  }

  return { content: [{ type: 'text', text: JSON.stringify(data) }] }
}
