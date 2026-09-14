import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'
import { z } from 'zod'

import type { ApiClientFactory } from '../client.js'
import { apiResult } from '../results.js'
import { silentField, writeHeaders } from './write.js'

/**
 * #406: the crew, studio-settings, closures, number-range, form and
 * portal-visibility ops the admin surface has and the api surface (routes/
 * api.php) gained alongside it — everything I-02/I-03's original tool set
 * left out. Every read/write pair is a thin call onto its own api-surface
 * endpoint, the same shape write.ts's writers already use.
 */
export function registerStudioOpsTools(server: McpServer, clientFor: ApiClientFactory): void {
  registerListStaff(server, clientFor)
  registerListCrew(server, clientFor)
  registerAssignCrew(server, clientFor)
  registerRemoveCrew(server, clientFor)
  registerGetStudioSettings(server, clientFor)
  registerListStudioClosures(server, clientFor)
  registerListNumberRanges(server, clientFor)
  registerListForms(server, clientFor)
  registerCreateForm(server, clientFor)
  registerUpdateForm(server, clientFor)
  registerShareForm(server, clientFor)
  registerAnswerFormField(server, clientFor)
  registerListShootTimeline(server, clientFor)
  registerAddTimelineEntry(server, clientFor)
  registerDeleteTimelineEntry(server, clientFor)
  registerListShotList(server, clientFor)
  registerAddShotListItem(server, clientFor)
  registerCheckShotListItem(server, clientFor)
  registerDeleteShotListItem(server, clientFor)
  registerSetPortalVisibility(server, clientFor)
}

function registerListStaff(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'list_staff',
    {
      title: 'List staff',
      description: 'Every staff user, with the public id assign_crew needs.',
      inputSchema: {},
      annotations: { readOnlyHint: true, openWorldHint: false },
    },
    async (_args, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).GET('/staff')

      return apiResult(data, error, response)
    },
  )
}

function registerListCrew(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'list_crew',
    {
      title: "List a shoot's crew",
      description: 'Who is assigned to a shoot, and in what role.',
      inputSchema: { shoot: z.string().describe('The shoot public id') },
      annotations: { readOnlyHint: true, openWorldHint: false },
    },
    async ({ shoot }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).GET('/shoots/{shoot}/crew', {
        params: { path: { shoot } },
      })

      return apiResult(data, error, response)
    },
  )
}

const crewRoleEnum = ['lead_photographer', 'second_shooter', 'assistant'] as const

function registerAssignCrew(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'assign_crew',
    {
      title: 'Assign crew to a shoot',
      description: 'Puts a staff user on a shoot in a given role. Assigning someone already on the day updates their role instead of failing.',
      inputSchema: {
        shoot: z.string().describe('The shoot public id'),
        user: z.string().describe('The staff public id, from list_staff'),
        role: z.enum(crewRoleEnum),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
    },
    async ({ shoot, user, role, silent }, extra) => {
      const payload = { user, role }
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/shoots/{shoot}/crew', {
        params: { path: { shoot } },
        body: payload,
        headers: writeHeaders('assign_crew', { shoot, ...payload }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerRemoveCrew(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'remove_crew',
    {
      title: 'Remove a crew assignment',
      description: 'Takes a staff user off a shoot. The assignment id comes from list_crew.',
      inputSchema: { assignment: z.string().describe('The crew assignment public id'), ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ assignment, silent }, extra) => {
      const { error, response } = await clientFor(extra.authInfo?.token).DELETE('/crew/{assignment}', {
        params: { path: { assignment } },
        headers: writeHeaders('remove_crew', { assignment }, silent),
      })

      return response.ok ? { content: [{ type: 'text', text: `Removed crew assignment ${assignment}.` }] } : apiResult(undefined, error, response)
    },
  )
}

function registerGetStudioSettings(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'get_studio_settings',
    {
      title: 'Get studio settings',
      description: 'VAT mode and rate, and the quote/payment defaults create_quote relies on. Never mail credentials, bank details or tokens.',
      inputSchema: {},
      annotations: { readOnlyHint: true, openWorldHint: false },
    },
    async (_args, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).GET('/studio-settings')

      return apiResult(data, error, response)
    },
  )
}

function registerListStudioClosures(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'list_studio_closures',
    {
      title: 'List studio closures',
      description: "The studio's own closed date ranges, the second conflict source shoots_in_date_range does not see on its own.",
      inputSchema: {},
      annotations: { readOnlyHint: true, openWorldHint: false },
    },
    async (_args, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).GET('/studio-closures')

      return apiResult(data, error, response)
    },
  )
}

function registerListNumberRanges(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'list_number_ranges',
    {
      title: 'List number ranges',
      description: 'One row per document type, with its prefix and a preview of the next number it would issue.',
      inputSchema: {},
      annotations: { readOnlyHint: true, openWorldHint: false },
    },
    async (_args, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).GET('/number-ranges')

      return apiResult(data, error, response)
    },
  )
}

function registerListForms(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'list_forms',
    {
      title: "List a project's forms",
      description: "A project's questionnaires, seeded or blank, shared or not, with their fields and current answers.",
      inputSchema: { project: z.string().describe('The project public id') },
      annotations: { readOnlyHint: true, openWorldHint: false },
    },
    async ({ project }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).GET('/projects/{project}/forms', {
        params: { path: { project } },
      })

      return apiResult(data, error, response)
    },
  )
}

function registerCreateForm(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'create_form',
    {
      title: 'Create a form',
      description: "Seeds a new questionnaire on a project from a template's current questions, or starts one blank when `template` is omitted.",
      inputSchema: {
        project: z.string().describe('The project public id'),
        template: z.string().nullish().describe('A form template public id'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
    },
    async ({ project, template, silent }, extra) => {
      const payload = { template: template ?? undefined }
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/projects/{project}/forms', {
        params: { path: { project } },
        body: payload,
        headers: writeHeaders('create_form', { project, ...payload }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

const formFieldTypeEnum = ['text', 'short_text', 'boolean', 'select', 'date', 'time', 'name_list', 'group_photos', 'file'] as const

const formFieldSchema = z.object({
  label: z.string(),
  type: z.enum(formFieldTypeEnum),
  required: z.boolean(),
  options: z.unknown().nullish().describe('Only "select" reads this, as a list of choices. Leave unset for every other type — "file" and "group_photos" answers are out of scope here.'),
})

function registerUpdateForm(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'update_form',
    {
      title: "Replace a form's fields",
      description: "Renames a form and replaces its fields wholesale — the same shape create_form seeds, personalised. There is no partial edit; send every field the form should keep.",
      inputSchema: {
        form: z.string().describe('The form instance public id'),
        name: z.string(),
        fields: z.array(formFieldSchema),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false },
    },
    async ({ form, silent, ...body }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).PATCH('/forms/{formInstance}', {
        params: { path: { formInstance: form } },
        body: body as never,
        headers: writeHeaders('update_form', { form, ...body }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerShareForm(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'share_form',
    {
      title: 'Share or unshare a form',
      description: 'Puts a questionnaire in front of the client on the portal, or takes it back. Unsharing hides it; it never deletes an answer already given.',
      inputSchema: { form: z.string().describe('The form instance public id'), shared: z.boolean(), ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false },
    },
    async ({ form, shared, silent }, extra) => {
      const payload = { shared }
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/forms/{formInstance}/share', {
        params: { path: { formInstance: form } },
        body: payload,
        headers: writeHeaders('share_form', { form, ...payload }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerAnswerFormField(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'answer_form_field',
    {
      title: 'Record an answer',
      description: 'Records (or corrects) the answer to one question on a form already seeded. Appends the next answer version; every earlier one stays. A "file" field cannot be answered here — there is no way to attach bytes through this tool.',
      inputSchema: { field: z.string().describe('The form field public id'), value: z.string().nullish(), ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
    },
    async ({ field, value, silent }, extra) => {
      const payload = { value: value ?? undefined }
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/form-fields/{formInstanceField}/answer', {
        params: { path: { formInstanceField: field } },
        body: payload,
        headers: writeHeaders('answer_form_field', { field, ...payload }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerListShootTimeline(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'list_shoot_timeline',
    {
      title: "List a shoot's timeline",
      description: "A shoot's day-of schedule: one row per stop, in time order.",
      inputSchema: { shoot: z.string().describe('The shoot public id') },
      annotations: { readOnlyHint: true, openWorldHint: false },
    },
    async ({ shoot }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).GET('/shoots/{shoot}/timeline', {
        params: { path: { shoot } },
      })

      return apiResult(data, error, response)
    },
  )
}

function registerAddTimelineEntry(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'add_timeline_entry',
    {
      title: 'Add a timeline entry',
      description: "Adds one stop to a shoot's day-of schedule.",
      inputSchema: {
        shoot: z.string().describe('The shoot public id'),
        time: z.string().describe('HH:MM, 24h'),
        title: z.string(),
        location: z.string().nullish(),
        note: z.string().nullish(),
        isTransit: z.boolean().nullish().describe('Marks a travel leg between two real stops rather than a stop itself.'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
    },
    async ({ shoot, isTransit, silent, ...body }, extra) => {
      const payload = { ...body, is_transit: isTransit ?? undefined }
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/shoots/{shoot}/timeline', {
        params: { path: { shoot } },
        body: payload,
        headers: writeHeaders('add_timeline_entry', { shoot, ...payload }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerDeleteTimelineEntry(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'delete_timeline_entry',
    {
      title: 'Delete a timeline entry',
      description: "Removes one stop from a shoot's day-of schedule.",
      inputSchema: { entry: z.string().describe('The timeline entry public id'), ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ entry, silent }, extra) => {
      const { error, response } = await clientFor(extra.authInfo?.token).DELETE('/timeline/{entry}', {
        params: { path: { entry } },
        headers: writeHeaders('delete_timeline_entry', { entry }, silent),
      })

      return response.ok ? { content: [{ type: 'text', text: `Deleted timeline entry ${entry}.` }] } : apiResult(undefined, error, response)
    },
  )
}

function registerListShotList(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'list_shot_list',
    {
      title: "List a shoot's shot list",
      description: 'The shots a studio means to get on a shoot, checked off as they happen.',
      inputSchema: { shoot: z.string().describe('The shoot public id') },
      annotations: { readOnlyHint: true, openWorldHint: false },
    },
    async ({ shoot }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).GET('/shoots/{shoot}/shot-list', {
        params: { path: { shoot } },
      })

      return apiResult(data, error, response)
    },
  )
}

function registerAddShotListItem(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'add_shot_list_item',
    {
      title: 'Add a shot list item',
      description: "Adds one shot to a shoot's shot list.",
      inputSchema: { shoot: z.string().describe('The shoot public id'), label: z.string(), ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
    },
    async ({ shoot, label, silent }, extra) => {
      const payload = { label }
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/shoots/{shoot}/shot-list', {
        params: { path: { shoot } },
        body: payload,
        headers: writeHeaders('add_shot_list_item', { shoot, ...payload }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerCheckShotListItem(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'check_shot_list_item',
    {
      title: 'Check off a shot',
      description: 'Marks a shot list item as done. Checking an already-checked item leaves it checked — safe to retry.',
      inputSchema: { item: z.string().describe('The shot list item public id'), ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false },
    },
    async ({ item, silent }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/shot-list/{item}/check', {
        params: { path: { item } },
        headers: writeHeaders('check_shot_list_item', { item }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerDeleteShotListItem(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'delete_shot_list_item',
    {
      title: 'Delete a shot list item',
      description: "Removes one shot from a shoot's shot list.",
      inputSchema: { item: z.string().describe('The shot list item public id'), ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ item, silent }, extra) => {
      const { error, response } = await clientFor(extra.authInfo?.token).DELETE('/shot-list/{item}', {
        params: { path: { item } },
        headers: writeHeaders('delete_shot_list_item', { item }, silent),
      })

      return response.ok ? { content: [{ type: 'text', text: `Deleted shot list item ${item}.` }] } : apiResult(undefined, error, response)
    },
  )
}

function registerSetPortalVisibility(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'set_portal_visibility',
    {
      title: "Set a project's portal visibility",
      description: 'Shows or hides a project on the portal. Takes the value explicitly rather than flipping it, so a retry always lands on the same state.',
      inputSchema: { project: z.string().describe('The project public id'), visible: z.boolean(), ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false },
    },
    async ({ project, visible, silent }, extra) => {
      const payload = { visible }
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/projects/{project}/portal-visibility', {
        params: { path: { project } },
        body: payload,
        headers: writeHeaders('set_portal_visibility', { project, ...payload }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}
