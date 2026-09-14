import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'
import { z } from 'zod'

import type { ApiClientFactory } from '../client.js'
import { apiResult, errorResult, jsonResult } from '../results.js'
import { silentField, writeHeaders } from './write.js'

/**
 * #401: what an MCP audit found missing — booking, completion and tasks. The
 * write half mirrors write.ts's shape (writeHeaders/silentField, reused
 * rather than duplicated), the read half tools.ts's, both through
 * results.ts, each a thin call onto the matching api-surface route added
 * alongside these tools — see routes/api.php.
 */
export function registerBookingTaskTools(server: McpServer, clientFor: ApiClientFactory): void {
  registerListProjects(server, clientFor)
  registerListJobs(server, clientFor)
  registerBookProject(server, clientFor)
  registerCompleteProject(server, clientFor)
  registerReopenProject(server, clientFor)
  registerListTasks(server, clientFor)
  registerCreateTask(server, clientFor)
  registerCompleteTask(server, clientFor)
  registerReopenTask(server, clientFor)
  registerAssignTask(server, clientFor)
  registerSetTaskDependency(server, clientFor)
  registerDeleteTask(server, clientFor)
}

const projectSchema = z.object({
  id: z.string(),
  name: z.string(),
  type: z.string(),
  portalVisible: z.boolean(),
  fields: z.record(z.string(), z.unknown()),
  updatedAt: z.string(),
})

const jobSchema = z.object({
  id: z.string(),
  projectId: z.string(),
  bookedAt: z.string(),
  bookedVia: z.string(),
  updatedAt: z.string(),
})

const taskSchema = z.object({
  id: z.string(),
  projectId: z.string(),
  title: z.string(),
  audience: z.string(),
  dueOn: z.string().nullable(),
  isDone: z.boolean(),
  completedOn: z.string().nullable(),
  shootId: z.string().nullable(),
  dependsOnTaskId: z.string().nullable(),
  isBlocked: z.boolean(),
  assigneeId: z.string().nullable(),
  updatedAt: z.string(),
})

function registerListProjects(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'list_projects',
    {
      title: 'List projects',
      description:
        "Lists booked projects — the same set that answers /projekte. An unbooked pursuit is a lead, not a project; see list_leads for those. Every id is a public_id, never a row's internal integer id.",
      inputSchema: {
        cursor: z.string().optional().describe("Opaque cursor from a previous call's nextCursor."),
        since: z.string().optional().describe('ISO 8601 datetime — only projects updated at or after this instant.'),
      },
      annotations: { readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false },
    },
    async ({ cursor, since }, extra) => {
      const client = clientFor(extra.authInfo?.token)
      // '/projects' documents no query parameters — same Scramble blind spot
      // search_contacts's own comment in tools.ts explains for '/contacts'.
      const { data, error, response } = await client.GET('/projects', { params: { query: { cursor, since } as never } })
      if (!response.ok || data === undefined) {
        return errorResult(error, response)
      }

      return jsonResult({
        projects: data.data as unknown as z.infer<typeof projectSchema>[],
        nextCursor: data.meta.next_cursor,
      })
    },
  )
}

function registerListJobs(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'list_jobs',
    {
      title: 'List jobs',
      description: 'Lists bookings — one per booked project, with when and how it was booked. "Which projects are booked?" is this call.',
      inputSchema: {
        cursor: z.string().optional().describe("Opaque cursor from a previous call's nextCursor."),
        since: z.string().optional().describe('ISO 8601 datetime — only jobs updated at or after this instant.'),
      },
      annotations: { readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false },
    },
    async ({ cursor, since }, extra) => {
      const client = clientFor(extra.authInfo?.token)
      const { data, error, response } = await client.GET('/jobs', { params: { query: { cursor, since } as never } })
      if (!response.ok || data === undefined) {
        return errorResult(error, response)
      }

      return jsonResult({
        jobs: data.data as unknown as z.infer<typeof jobSchema>[],
        nextCursor: data.meta.next_cursor,
      })
    },
  )
}

function registerBookProject(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'book_project',
    {
      title: 'Book a project',
      description:
        'Commit the studio to a project: create its Job and advance every uncancelled shoot to the booked stage, the same move the project screen\'s Buchen button makes. A project already booked is a no-op — the existing job comes back.',
      inputSchema: { project: z.string().describe('The project public id'), ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false },
    },
    async ({ project, silent }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/projects/{project}/book', {
        params: { path: { project } },
        headers: writeHeaders('book_project', { project }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerCompleteProject(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'complete_project',
    {
      title: 'Complete a project',
      description:
        'Mark a project done, independent of what its shoots\' statuses stand at. Refused if it is already completed. Reversible with reopen_project.',
      inputSchema: { project: z.string().describe('The project public id'), ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false },
    },
    async ({ project, silent }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/projects/{project}/complete', {
        params: { path: { project } },
        headers: writeHeaders('complete_project', { project }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerReopenProject(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'reopen_project',
    {
      title: 'Reopen a project',
      description: 'Take back a completion entered by mistake. Refused if the project is not completed.',
      inputSchema: { project: z.string().describe('The project public id'), ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
    },
    async ({ project, silent }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).DELETE('/projects/{project}/complete', {
        params: { path: { project } },
        headers: writeHeaders('reopen_project', { project }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerListTasks(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'list_tasks',
    {
      title: 'List a project’s tasks',
      description: "A project's tasks, open first and by due date, undated last. `done` narrows to completed or open ones; left out, both come back.",
      inputSchema: {
        project: z.string().describe('The project public id'),
        done: z.boolean().optional().describe('true for completed tasks only, false for open ones'),
      },
      annotations: { readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false },
    },
    async ({ project, done }, extra) => {
      const client = clientFor(extra.authInfo?.token)
      const { data, error, response } = await client.GET('/projects/{project}/tasks', {
        params: { path: { project }, query: { done: done === undefined ? undefined : String(done) } as never },
      })
      if (!response.ok || data === undefined) {
        return errorResult(error, response)
      }

      return jsonResult({ tasks: data.data as unknown as z.infer<typeof taskSchema>[] })
    },
  )
}

function registerCreateTask(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'create_task',
    {
      title: 'Create a task',
      description:
        'Add a task to a project, due on a fixed date or hanging off an anchor (a shoot’s date, a shoot reaching a status, a lead reaching a stage) with an offset in days. Never both `due_on` and `anchor`.',
      inputSchema: {
        project: z.string().describe('The project public id'),
        title: z.string().max(255),
        audience: z.enum(['internal', 'client']),
        due_on: z.string().nullish().describe('YYYY-MM-DD. Mutually exclusive with anchor.'),
        anchor: z.string().nullish().describe("What the due date hangs off, e.g. 'shoot:<id>' or 'shoot:<id>:<status>' or 'stage:<key>'."),
        offset_days: z.number().int().min(-365).max(365).nullish().describe('Days after (or before, negative) the anchor. Only with anchor.'),
        shoot: z.string().nullish().describe('Shoot public id this task belongs to'),
        depends_on: z.string().nullish().describe('Public id of a task on the same project this one waits on'),
        assignee: z.string().nullish().describe('User public id. Left out: crew, then the whole studio.'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
    },
    async ({ project, silent, ...task }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/projects/{project}/tasks', {
        params: { path: { project } },
        body: task as never,
        headers: writeHeaders('create_task', { project, ...task }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerCompleteTask(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'complete_task',
    {
      title: 'Complete a task',
      description:
        "Mark a task done. Refused while it depends on a task that is not done itself. `completed_on` states the day the work actually happened, for one entered after the fact; sending it on a task already done corrects the date rather than reopening it.",
      inputSchema: {
        task: z.string().describe('The task public id'),
        completed_on: z.string().nullish().describe('YYYY-MM-DD, never in the future. Defaults to now.'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ task, completed_on: completedOn, silent }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/tasks/{task}/complete', {
        params: { path: { task } },
        body: { completed_on: completedOn } as never,
        headers: writeHeaders('complete_task', { task, completed_on: completedOn }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerReopenTask(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'reopen_task',
    {
      title: 'Reopen a task',
      description: 'Take a completion back, for the list that only shows what is done.',
      inputSchema: { task: z.string().describe('The task public id'), ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false },
    },
    async ({ task, silent }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/tasks/{task}/reopen', {
        params: { path: { task } },
        headers: writeHeaders('reopen_task', { task }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerAssignTask(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'assign_task',
    {
      title: 'Assign a task',
      description: 'Hand a task over to a staff member, or take it back by leaving assignee out.',
      inputSchema: {
        task: z.string().describe('The task public id'),
        assignee: z.string().nullish().describe('User public id. Null clears the assignment.'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ task, assignee, silent }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/tasks/{task}/assign', {
        params: { path: { task } },
        body: { assignee } as never,
        headers: writeHeaders('assign_task', { task, assignee }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerSetTaskDependency(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'set_task_dependency',
    {
      title: 'Set what a task waits on',
      description:
        "Change what a task depends on, or clear it by leaving depends_on out. Refused if the chosen task would wait on itself, directly or through a chain.",
      inputSchema: {
        task: z.string().describe('The task public id'),
        depends_on: z.string().nullish().describe('Public id of a task on the same project. Null clears the dependency.'),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ task, depends_on: dependsOn, silent }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/tasks/{task}/depend', {
        params: { path: { task } },
        body: { depends_on: dependsOn } as never,
        headers: writeHeaders('set_task_dependency', { task, depends_on: dependsOn }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerDeleteTask(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'delete_task',
    {
      title: 'Delete a task',
      description: 'Remove a task. There is no undo.',
      inputSchema: { task: z.string().describe('The task public id'), ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ task, silent }, extra) => {
      const { error, response } = await clientFor(extra.authInfo?.token).DELETE('/tasks/{task}', {
        params: { path: { task } },
        headers: writeHeaders('delete_task', { task }, silent),
      })

      return response.ok ? { content: [{ type: 'text', text: `Deleted task ${task}.` }] } : apiResult(undefined, error, response)
    },
  )
}
