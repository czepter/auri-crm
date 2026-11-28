import { callApi } from './apiClient'
import type { GalleryCover } from './design'

export interface DownloadLinkPage {
  gallery: {
    id: string
    title: string
    count: number
    cover: (GalleryCover & { key: string; version: string; pass: string | null }) | null
  }
  size: 'original' | 'web'
  bytes: number
  parts: { number: number; bytes: number; path: string }[]
}

export type DownloadLinkResult = 'sent' | 'pin' | 'throttled' | 'failed'

/** Startet den Bau; die fertige Mail geht an die Adresse, einen Code gibt es nicht. */
export async function requestDownloadLink(
  apiBase: string,
  galleryId: string,
  requestId: string,
  payload: { email: string; size: string; pin: string | null },
  viewPass: string | null,
): Promise<DownloadLinkResult> {
  const response = await callApi(
    apiBase,
    `/api/galleries/${galleryId}/download/link`,
    requestId,
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(payload),
    },
    viewPass,
  )

  if (response.ok) return 'sent'
  if (response.status === 429) return 'throttled'

  if (response.status === 422) {
    const body: { errors?: Record<string, string[]> } = await response.json().catch(() => ({}))

    return body.errors && 'pin' in body.errors ? 'pin' : 'failed'
  }

  return 'failed'
}

/** Die Teile hinter dem Token aus der Mail; null heißt abgelaufen oder unbekannt. */
export async function readDownloadLink(
  apiBase: string,
  galleryId: string,
  requestId: string,
  token: string,
): Promise<DownloadLinkPage | null> {
  const response = await callApi(
    apiBase,
    `/api/galleries/${galleryId}/download-mail/${encodeURIComponent(token)}`,
    requestId,
    { headers: { Accept: 'application/json' } },
  )

  return response.ok ? await response.json() : null
}

export function formatBytes(bytes: number): string {
  if (bytes >= 1024 ** 3) return `${(bytes / 1024 ** 3).toFixed(1).replace('.', ',')} GB`

  return `${Math.max(1, Math.round(bytes / 1024 ** 2))} MB`
}
