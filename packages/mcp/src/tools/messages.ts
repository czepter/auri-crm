import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'
import { z } from 'zod'

import type { ApiClientFactory } from '../client.js'
import { idempotencyKeyFor } from '../idempotency.js'
import { apiResult } from '../results.js'

/**
 * #404: the send half of E-06/E-13/E-12 that add_message never covered —
 * add_message (write.ts) only backfills history, nothing leaves. Every tool
 * here reaches the client or moves the inbox, so `silent` is left off
 * throughout: a silent send is a contradiction (that is what add_message is
 * for), and offering the flag would only invite `send_message` to become a
 * second, confusing way to write history nobody reads.
 */
export function registerMessageTools(server: McpServer, clientFor: ApiClientFactory): void {
  registerSendMessage(server, clientFor)
  registerCancelScheduledMessage(server, clientFor)
  registerListInboxThreads(server, clientFor)
  registerReplyInboxThread(server, clientFor)
  registerCloseInboxThread(server, clientFor)
  registerAttachInboundMail(server, clientFor)
  registerFileThreadToProject(server, clientFor)
}

function writeHeaders(tool: string, payload: Record<string, unknown>): Record<string, string> {
  return { 'Idempotency-Key': idempotencyKeyFor(tool, payload) }
}

function registerSendMessage(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'send_message',
    {
      title: 'Send a message',
      description:
        "Reply on a project's conversation — the client receives it by mail, unlike add_message, which only backfills history and sends nothing. Needs a thread the client already started; there is none to reply to on a project with no conversation yet. With scheduled_for it goes out later instead, cancellable until then with cancel_scheduled_message.",
      inputSchema: {
        project: z.string().describe('The project public id'),
        body: z.string().max(5000),
        scheduled_for: z.string().nullish().describe('When to send it. ISO 8601, in the future. Immediate if omitted.'),
      },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: true },
    },
    async ({ project, body, scheduled_for }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/projects/{project}/messages/send', {
        params: { path: { project } },
        body: { body, scheduled_for } as never,
        headers: writeHeaders('send_message', { project, body, scheduled_for }),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerCancelScheduledMessage(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'cancel_scheduled_message',
    {
      title: 'Cancel a scheduled message',
      description: 'Deletes a message send_message scheduled for later, before it goes out. Refused once it has already sent.',
      inputSchema: { message: z.string().describe('The message public id, from send_message') },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ message }, extra) => {
      const { error, response } = await clientFor(extra.authInfo?.token).DELETE('/messages/{message}', {
        params: { path: { message } },
        headers: writeHeaders('cancel_scheduled_message', { message }),
      })

      // 204, no body — see write.ts's delete_project for the same shape.
      return response.ok ? { content: [{ type: 'text', text: `Cancelled message ${message}.` }] } : apiResult(undefined, error, response)
    },
  )
}

function registerListInboxThreads(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'list_inbox_threads',
    {
      title: 'List inbox threads',
      description:
        'The Posteingang tray: every conversation, newest first, with its work state (unbeantwortet/wartet/erledigt), snippet and how long the newest line has waited. projectId is null for a conversation the client started without naming a booking — file_thread_to_project is what gives it one.',
      inputSchema: {},
      annotations: { readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false },
    },
    async (_args, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).GET('/inbox/threads')

      return apiResult(data, error, response)
    },
  )
}

function registerReplyInboxThread(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'reply_inbox_thread',
    {
      title: 'Reply on an inbox thread',
      description: "The studio's reply from the Posteingang screen, by thread id rather than project — for a conversation list_inbox_threads shows, including one with no project yet.",
      inputSchema: {
        thread: z.string().describe('The thread public id, from list_inbox_threads'),
        body: z.string().max(20000),
      },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: true },
    },
    async ({ thread, body }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/inbox/threads/{thread}/reply', {
        params: { path: { thread } },
        body: { body } as never,
        headers: writeHeaders('reply_inbox_thread', { thread, body }),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerCloseInboxThread(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'close_inbox_thread',
    {
      title: 'Close or reopen an inbox thread',
      description:
        "Toggles a conversation's erledigt state: closes an unanswered or waiting one, or reopens one already marked done. A client writing again reopens it on its own regardless.",
      inputSchema: { thread: z.string().describe('The thread public id, from list_inbox_threads') },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
    },
    async ({ thread }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/inbox/threads/{thread}/close', {
        params: { path: { thread } },
        headers: writeHeaders('close_inbox_thread', { thread }),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerAttachInboundMail(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'attach_inbound_mail',
    {
      title: 'Attach an inbound mail to a thread',
      description:
        'Files a mail the automatic ingest could not place onto the conversation it belongs to — the triage tray a studio works from list_inbox_threads. Writes it as a message from whichever of the thread\'s contacts sent it.',
      inputSchema: {
        inbound_message: z.string().describe('The inbound mail id, from the Posteingang triage tray'),
        thread: z.string().describe('The thread public id it belongs on'),
      },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
    },
    async ({ inbound_message: inboundMessage, thread }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/inbox/messages/{inboundMessage}/attach', {
        params: { path: { inboundMessage } },
        body: { thread } as never,
        headers: writeHeaders('attach_inbound_mail', { inboundMessage, thread }),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerFileThreadToProject(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'file_thread_to_project',
    {
      title: 'File a thread to a project',
      description:
        "Points a conversation that started without naming a booking (projectId null on list_inbox_threads) at the project it turned out to be about. One direction only — a thread that already names a project cannot be re-filed or detached.",
      inputSchema: {
        thread: z.string().describe('The thread public id, from list_inbox_threads'),
        project: z.string().describe('The project public id to file it under'),
      },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false },
    },
    async ({ thread, project }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/inbox/threads/{thread}/file', {
        params: { path: { thread } },
        body: { project } as never,
        headers: writeHeaders('file_thread_to_project', { thread, project }),
      })

      return apiResult(data, error, response)
    },
  )
}
