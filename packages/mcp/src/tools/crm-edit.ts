import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'
import { z } from 'zod'

import type { ApiClientFactory } from '../client.js'
import { apiResult, silentField, writeHeaders } from './write.js'

/**
 * #400/#363: the MCP-audit findings — contact, project, shoot and
 * project-contact writes the admin surface already had and the api surface
 * (and so the MCP tools calling it) did not. Same shape as write.ts: a thin
 * call onto an idempotent api-surface endpoint, no DB access of its own.
 */
export function registerCrmEditTools(server: McpServer, clientFor: ApiClientFactory): void {
  registerCreateContact(server, clientFor)
  registerUpdateContact(server, clientFor)
  registerUpdateProject(server, clientFor)
  registerRemoveProjectContact(server, clientFor)
  registerUpdateShoot(server, clientFor)
  registerDeleteShoot(server, clientFor)
  registerSetPrimaryShoot(server, clientFor)
}

const contactFields = {
  name: z.string().max(255),
  email: z.string().email().max(255).nullish(),
  phone: z.string().max(64).nullish(),
}

function registerCreateContact(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'create_contact',
    {
      title: 'Create a contact',
      description: 'Add a person to the address book on their own, without a project. An email already on file is refused — search_contacts finds them instead.',
      inputSchema: { ...contactFields, ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
    },
    async ({ silent, ...contact }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).POST('/contacts', {
        body: contact,
        headers: writeHeaders('create_contact', contact, silent),
      })

      return apiResult(data, error)
    },
  )
}

function registerUpdateContact(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'update_contact',
    {
      title: 'Update a contact',
      description: "Change a contact's name, e-mail, phone or billing address. Only the fields given change. search_contacts shows the current values.",
      inputSchema: {
        contact: z.string().describe('The contact public id'),
        name: contactFields.name.optional(),
        email: contactFields.email,
        phone: contactFields.phone,
        street: z.string().max(255).nullish(),
        postcode: z.string().max(32).nullish(),
        city: z.string().max(255).nullish(),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ contact, silent, ...fields }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).PATCH('/contacts/{contact}', {
        params: { path: { contact } },
        body: fields as never,
        headers: writeHeaders('update_contact', { contact, ...fields }, silent),
      })

      return apiResult(data, error)
    },
  )
}

function registerUpdateProject(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'update_project',
    {
      title: 'Update a project',
      description:
        "Change a project's name, type or lead source. Only the fields given change; type_other and source_other are kept unless given even when type or source changes to something else. get_project shows the current values.",
      inputSchema: {
        project: z.string().describe('The project public id'),
        name: z.string().max(255).optional(),
        type: z.string().optional().describe('The project type key — see list_project_types'),
        type_other: z.string().max(120).nullish().describe("What 'other' actually was. Only kept beside type: other."),
        source: z.enum(['referral', 'website', 'instagram', 'wedding_fair', 'other']).optional(),
        source_other: z.string().max(120).nullish().describe("What 'other' actually was. Only kept beside source: other."),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ project, silent, ...fields }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).PATCH('/projects/{project}', {
        params: { path: { project } },
        body: fields as never,
        headers: writeHeaders('update_project', { project, ...fields }, silent),
      })

      return apiResult(data, error)
    },
  )
}

function registerRemoveProjectContact(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'remove_project_contact',
    {
      title: 'Remove a project contact',
      description: 'Detach a secondary contact — added by add_project_contact — from a project. The primary contact cannot be removed this way.',
      inputSchema: {
        project: z.string().describe('The project public id'),
        contact: z.string().describe('The contact public id'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ project, contact, silent }, extra) => {
      const { error } = await clientFor(extra.authInfo?.token).DELETE('/projects/{project}/contacts/{contact}', {
        params: { path: { project, contact } },
        headers: writeHeaders('remove_project_contact', { project, contact }, silent),
      })

      // 204, no body — see delete_project in write.ts.
      return error !== undefined
        ? apiResult(undefined, error)
        : { content: [{ type: 'text', text: `Removed contact ${contact} from project ${project}.` }] }
    },
  )
}

const shootFields = {
  kind: z.string().max(255).optional(),
  label: z.string().max(255).nullish(),
  date: z.string().nullish().describe('YYYY-MM-DD'),
  call_time: z.string().nullish().describe('HH:MM'),
  end_time: z.string().nullish().describe('HH:MM'),
  venue_name: z.string().max(255).nullish(),
  venue_city: z.string().max(255).nullish(),
}

function registerUpdateShoot(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'update_shoot',
    {
      title: 'Update a shoot',
      description: 'Change a shoot\'s date, call time, end time, venue, label or kind. Only the fields given change. get_project shows the current values.',
      inputSchema: { shoot: z.string().describe('The shoot public id'), ...shootFields, ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ shoot, silent, ...fields }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).PATCH('/shoots/{shoot}', {
        params: { path: { shoot } },
        body: fields as never,
        headers: writeHeaders('update_shoot', { shoot, ...fields }, silent),
      })

      return apiResult(data, error)
    },
  )
}

function registerDeleteShoot(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'delete_shoot',
    {
      title: 'Delete a shoot',
      description: 'Remove a shooting day that was added by mistake. The primary shoot cannot be deleted this way — set_primary_shoot another one first, or use delete_project.',
      inputSchema: { shoot: z.string().describe('The shoot public id'), ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ shoot, silent }, extra) => {
      const { error } = await clientFor(extra.authInfo?.token).DELETE('/shoots/{shoot}', {
        params: { path: { shoot } },
        headers: writeHeaders('delete_shoot', { shoot }, silent),
      })

      // 204, no body — see delete_project in write.ts.
      return error !== undefined
        ? apiResult(undefined, error)
        : { content: [{ type: 'text', text: `Deleted shoot ${shoot}.` }] }
    },
  )
}

function registerSetPrimaryShoot(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'set_primary_shoot',
    {
      title: 'Set the primary shoot',
      description: "Move the \"Haupttermin\" designation to a different shoot on the same project. A cancelled shoot cannot become primary.",
      inputSchema: { shoot: z.string().describe('The shoot public id to make primary'), ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ shoot, silent }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).POST('/shoots/{shoot}/primary', {
        params: { path: { shoot } },
        headers: writeHeaders('set_primary_shoot', { shoot }, silent),
      })

      return apiResult(data, error)
    },
  )
}
