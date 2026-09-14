import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'
import { z } from 'zod'

import type { ApiClientFactory } from '../client.js'
import { apiResult, silentField, writeHeaders } from './write.js'

/**
 * #403: the admin-only quote actions and personal service recommendations
 * the MCP audit found missing — each a thin call onto its own I-03-shaped
 * api-surface endpoint (routes/api.php), the same way tools/write.ts's
 * tools are. A separate file and a separate register call, not a growing
 * registerWriteTools, so this lands beside #396–#398's quote work without
 * touching it.
 */
export function registerQuoteActionTools(server: McpServer, clientFor: ApiClientFactory): void {
  registerCreateQuoteAddendum(server, clientFor)
  registerDuplicateQuote(server, clientFor)
  registerDeleteQuote(server, clientFor)
  registerMarkQuoteSent(server, clientFor)
  registerAdoptQuoteRecipient(server, clientFor)
  registerSaveQuoteAsPackage(server, clientFor)
  registerListServiceSuggestions(server, clientFor)
  registerSuggestService(server, clientFor)
  registerDeleteServiceSuggestion(server, clientFor)
}

function registerCreateQuoteAddendum(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'create_quote_addendum',
    {
      title: 'Create a quote addendum',
      description:
        'Start a new draft quote beside an already-accepted one, for adding to a booking after the fact. The accepted quote stays accepted, unlike revise_quote which supersedes it. Fails if the given quote is not accepted.',
      inputSchema: { quote: z.string().describe('The accepted quote public id to add to'), ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
    },
    async ({ quote, silent }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).POST('/quotes/{quote}/addendum', {
        params: { path: { quote } },
        headers: writeHeaders('create_quote_addendum', { quote }, silent),
      })

      return apiResult(data, error)
    },
  )
}

function registerDuplicateQuote(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'duplicate_quote',
    {
      title: 'Duplicate a quote',
      description: 'Copy a quote’s lines, payment plan and terms into a new independent draft on the same project. Status, sent/accepted history and predecessor stay behind.',
      inputSchema: { quote: z.string().describe('The quote public id to copy'), ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
    },
    async ({ quote, silent }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).POST('/quotes/{quote}/duplicate', {
        params: { path: { quote } },
        headers: writeHeaders('duplicate_quote', { quote }, silent),
      })

      return apiResult(data, error)
    },
  )
}

function registerDeleteQuote(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'delete_quote',
    {
      title: 'Delete a quote',
      description: 'Delete a draft quote. Refused once it has been sent — a client may already hold a copy. There is no undo.',
      inputSchema: { quote: z.string().describe('The quote public id'), ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ quote, silent }, extra) => {
      const { error } = await clientFor(extra.authInfo?.token).DELETE('/quotes/{quote}', {
        params: { path: { quote } },
        headers: writeHeaders('delete_quote', { quote }, silent),
      })

      return error !== undefined
        ? apiResult(undefined, error)
        : { content: [{ type: 'text', text: `Deleted quote ${quote}.` }] }
    },
  )
}

function registerMarkQuoteSent(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'mark_quote_sent',
    {
      title: 'Mark a quote sent by the studio itself',
      description: 'Record a draft quote as delivered outside the app — WhatsApp, a phone call, the studio’s own mail client. Same state change as send_quote, channel ‘self’, no client notification.',
      inputSchema: { quote: z.string().describe('The quote public id'), ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ quote, silent }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).POST('/quotes/{quote}/mark-sent', {
        params: { path: { quote } },
        headers: writeHeaders('mark_quote_sent', { quote }, silent),
      })

      return apiResult(data, error)
    },
  )
}

function registerAdoptQuoteRecipient(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'adopt_quote_recipient',
    {
      title: 'Adopt a quote’s recipient address',
      description: 'Write a quote’s recipient address (street, postcode, city) back onto its recipient contact. Fails if the quote has no recipient contact.',
      inputSchema: { quote: z.string().describe('The quote public id'), ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ quote, silent }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).POST('/quotes/{quote}/adopt-recipient', {
        params: { path: { quote } },
        headers: writeHeaders('adopt_quote_recipient', { quote }, silent),
      })

      return apiResult(data, error)
    },
  )
}

function registerSaveQuoteAsPackage(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'save_quote_as_package',
    {
      title: 'Save a quote as a package',
      description:
        'Turn a quote, or one of its lines, into a catalogue package — the only way to write a package’s hourly rate and payment plan from here. Give `item` (a line’s id from get_project) to save just that line; omit it to save the whole quote.',
      inputSchema: {
        quote: z.string().describe('The quote public id'),
        name: z.string().max(255).describe('The new package’s name'),
        item: z.string().nullish().describe('A quote line’s id, to save only that line'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
    },
    async ({ quote, name, item, silent }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).POST('/quotes/{quote}/save-as-package', {
        params: { path: { quote } },
        body: { name, item } as never,
        headers: writeHeaders('save_quote_as_package', { quote, name, item }, silent),
      })

      return apiResult(data, error)
    },
  )
}

function registerListServiceSuggestions(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'list_service_suggestions',
    {
      title: 'List a contact’s service suggestions',
      description: 'Lists the packages this studio has personally suggested to a contact — what suggest_service writes, and what the contact sees in their portal.',
      inputSchema: { contact: z.string().describe('The contact public id') },
      annotations: { readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false },
    },
    async ({ contact }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).GET('/contacts/{contact}/service-suggestions', {
        params: { path: { contact } },
      })

      return apiResult(data, error)
    },
  )
}

function registerSuggestService(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'suggest_service',
    {
      title: 'Suggest a service to a contact',
      description:
        'Personally recommend a package to a contact, with an optional note — what shows in their portal. The package must be offered there (portal_offered on the package); suggesting the same package again replaces the note rather than adding a second suggestion.',
      inputSchema: {
        contact: z.string().describe('The contact public id'),
        package: z.string().describe('The package public id — must have portal_offered set'),
        note: z.string().max(2000).nullish(),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
    },
    async ({ contact, package: pkg, note, silent }, extra) => {
      const { data, error } = await clientFor(extra.authInfo?.token).POST('/contacts/{contact}/service-suggestions', {
        params: { path: { contact } },
        body: { package: pkg, note } as never,
        headers: writeHeaders('suggest_service', { contact, package: pkg, note }, silent),
      })

      return apiResult(data, error)
    },
  )
}

function registerDeleteServiceSuggestion(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'delete_service_suggestion',
    {
      title: 'Delete a service suggestion',
      description: 'Withdraw a personal service suggestion from a contact’s portal. There is no undo.',
      inputSchema: { suggestion: z.string().describe('The service suggestion public id, from list_service_suggestions'), ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ suggestion, silent }, extra) => {
      const { error } = await clientFor(extra.authInfo?.token).DELETE('/service-suggestions/{serviceSuggestion}', {
        params: { path: { serviceSuggestion: suggestion } },
        headers: writeHeaders('delete_service_suggestion', { suggestion }, silent),
      })

      return error !== undefined
        ? apiResult(undefined, error)
        : { content: [{ type: 'text', text: `Deleted service suggestion ${suggestion}.` }] }
    },
  )
}
