import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'
import { z } from 'zod'

import type { ApiClientFactory } from '../client.js'
import { apiResult, errorResult, jsonResult } from '../results.js'
import { silentField, writeHeaders } from './write.js'

/**
 * #405: galleries reading and sharing, alongside tools.ts's other reads and
 * write.ts's other writes. Uploads, ordering, sets, cover and presets stay
 * admin-only — see routes/api.php's own doc comment on the scope split.
 */

const galleryRowSchema = z.object({
  id: z.string(),
  title: z.string(),
  projectId: z.string().nullable(),
  currentVersion: z.number().nullable(),
  draftAssetCount: z.number(),
  status: z.enum(['draft', 'prerelease', 'published', 'withdrawn']),
  updatedAt: z.string(),
})

const gallerySchema = galleryRowSchema.extend({
  slug: z.string(),
  url: z.string(),
  isExternal: z.boolean(),
  externalUrl: z.string().nullable(),
  expiresAt: z.string().nullable(),
  downloadsEnabled: z.boolean(),
  listsEnabled: z.boolean(),
  commentsEnabled: z.boolean(),
  favoriteCount: z.number(),
  downloadCount: z.number(),
  commentCount: z.number(),
  viewCount: z.number(),
})

export function registerGalleryReadTools(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'list_galleries',
    {
      title: 'List galleries',
      description: 'Lists galleries with their project, current published version and draft picture count.',
      inputSchema: {
        cursor: z.string().optional().describe("Opaque cursor from a previous call's nextCursor."),
        since: z.string().optional().describe('ISO 8601 datetime — only galleries updated at or after this instant.'),
      },
      outputSchema: { galleries: z.array(galleryRowSchema), nextCursor: z.string().nullable() },
      annotations: { readOnlyHint: true, openWorldHint: false },
    },
    async ({ cursor, since }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).GET('/galleries', { params: { query: { cursor, since } as never } })
      if (!response.ok || data === undefined) return errorResult(error, response)

      return jsonResult({
        galleries: data.data as unknown as z.infer<typeof galleryRowSchema>[],
        nextCursor: (data.meta as { next_cursor: string | null }).next_cursor,
      })
    },
  )

  server.registerTool(
    'get_gallery',
    {
      title: 'Get a gallery',
      description: 'Gets one gallery by its public_id, with status, slug, expiry, download/comment/favorite/view counts and the feature flags.',
      inputSchema: { gallery: z.string().describe("The gallery's public_id") },
      outputSchema: { gallery: gallerySchema },
      annotations: { readOnlyHint: true, openWorldHint: false },
    },
    async ({ gallery }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).GET('/gallery-records/{gallery}', { params: { path: { gallery } } })
      if (!response.ok || data === undefined) return errorResult(error, response)

      return jsonResult({ gallery: data.data as unknown as z.infer<typeof gallerySchema> })
    },
  )

  server.registerTool(
    'list_gallery_favorites',
    {
      title: 'List gallery favorites',
      description: "The filenames a client favorited in a gallery's currently published version, one per favorite. Empty before the first publish.",
      inputSchema: { gallery: z.string().describe("The gallery's public_id") },
      outputSchema: { filenames: z.array(z.string()) },
      annotations: { readOnlyHint: true, openWorldHint: false },
    },
    async ({ gallery }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).GET('/gallery-records/{gallery}/favorites', { params: { path: { gallery } } })
      if (!response.ok || data === undefined) return errorResult(error, response)

      return jsonResult({ filenames: data.data as unknown as string[] })
    },
  )
}

const galleryFeatureFields = {
  downloads_enabled: z.boolean().optional(),
  lists_enabled: z.boolean().optional(),
  comments_enabled: z.boolean().optional(),
}

function registerCreateGallery(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'create_gallery',
    {
      title: 'Create a gallery',
      description:
        'Start a delivery gallery, on a project or standing alone. is_external true links to pictures hosted elsewhere (external_url required) and owns no draft or version history of its own.',
      inputSchema: {
        project_id: z.string().nullish().describe('Project public_id'),
        shoot_id: z.string().nullish().describe('Shoot public_id, on that project'),
        title: z.string().max(200),
        is_external: z.boolean(),
        external_url: z.string().url().max(2000).nullish(),
        expires_at: z.string().nullish().describe('YYYY-MM-DD'),
        ...galleryFeatureFields,
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
    },
    async ({ silent, ...gallery }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/galleries', {
        body: gallery as never,
        headers: writeHeaders('create_gallery', gallery, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerUpdateGallery(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'update_gallery',
    {
      title: 'Update a gallery',
      description:
        "Change a gallery's expiry and feature flags. View password, download PIN and design stay admin-only.",
      inputSchema: {
        gallery: z.string().describe("The gallery's public_id"),
        expires_at: z.string().nullish().describe('YYYY-MM-DD'),
        ...galleryFeatureFields,
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ gallery, silent, ...fields }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).PATCH('/gallery-records/{gallery}', {
        params: { path: { gallery } },
        body: fields as never,
        headers: writeHeaders('update_gallery', { gallery, ...fields }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerPublishGallery(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'publish_gallery',
    {
      title: 'Publish a gallery',
      description:
        'Snapshot the draft into a new published version. Every guest already inside sees the new version immediately. Given a subject, also mails the project\'s primary contact — omit it to publish without notifying anyone.',
      inputSchema: {
        gallery: z.string().describe("The gallery's public_id"),
        subject: z.string().max(255).nullish(),
        message: z.string().nullish(),
        ...silentField,
      },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: true },
    },
    async ({ gallery, silent, ...body }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/gallery-records/{gallery}/publish', {
        params: { path: { gallery } },
        body: body as never,
        headers: writeHeaders('publish_gallery', { gallery, ...body }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerWithdrawGallery(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'withdraw_gallery',
    {
      title: 'Withdraw a gallery',
      description:
        'Stop showing a gallery to anyone outside the studio. Rotates the preview pass, so a preview link already sent stops opening the draft too.',
      inputSchema: { gallery: z.string().describe("The gallery's public_id"), ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ gallery, silent }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/gallery-records/{gallery}/withdraw', {
        params: { path: { gallery } },
        headers: writeHeaders('withdraw_gallery', { gallery }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerStartGalleryPrerelease(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'start_gallery_prerelease',
    {
      title: 'Start gallery pre-release',
      description:
        "Share a gallery's address before it has anything published — its own pre-release page collects addresses to notify at first publish. Refused once a version exists, or for an external gallery.",
      inputSchema: { gallery: z.string().describe("The gallery's public_id"), ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false },
    },
    async ({ gallery, silent }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/gallery-records/{gallery}/prerelease/start', {
        params: { path: { gallery } },
        headers: writeHeaders('start_gallery_prerelease', { gallery }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerStopGalleryPrerelease(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'stop_gallery_prerelease',
    {
      title: 'Stop gallery pre-release',
      description: 'End pre-release. Deletes everyone still on the waiting list — none of them were notified yet, so there is nothing to keep.',
      inputSchema: { gallery: z.string().describe("The gallery's public_id"), ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async ({ gallery, silent }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/gallery-records/{gallery}/prerelease/stop', {
        params: { path: { gallery } },
        headers: writeHeaders('stop_gallery_prerelease', { gallery }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

function registerCreateGalleryPreviewLink(server: McpServer, clientFor: ApiClientFactory): void {
  server.registerTool(
    'create_gallery_preview_link',
    {
      title: 'Create a gallery preview link',
      description:
        'Mint a time-limited link that opens the draft without a password, for handing the work in progress to a couple or a second shooter. Rotates the pass, so any earlier preview link stops working the moment this one is issued.',
      inputSchema: { gallery: z.string().describe("The gallery's public_id"), ...silentField },
      annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false },
    },
    async ({ gallery, silent }, extra) => {
      const { data, error, response } = await clientFor(extra.authInfo?.token).POST('/gallery-records/{gallery}/preview-link', {
        params: { path: { gallery } },
        headers: writeHeaders('create_gallery_preview_link', { gallery }, silent),
      })

      return apiResult(data, error, response)
    },
  )
}

export function registerGalleryWriteTools(server: McpServer, clientFor: ApiClientFactory): void {
  registerCreateGallery(server, clientFor)
  registerUpdateGallery(server, clientFor)
  registerPublishGallery(server, clientFor)
  registerWithdrawGallery(server, clientFor)
  registerStartGalleryPrerelease(server, clientFor)
  registerStopGalleryPrerelease(server, clientFor)
  registerCreateGalleryPreviewLink(server, clientFor)
}
