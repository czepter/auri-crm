import { callApi } from './apiClient'

const COOKIE_PREFIX = 'gallery_download_'

export function downloadCookieName(galleryId: string): string {
  return COOKIE_PREFIX + galleryId
}

export const DOWNLOAD_MAX_AGE_SECONDS = 12 * 60 * 60

export interface DownloadBuild {
  size: 'original' | 'web'
  status: 'building' | 'ready' | 'failed'
  parts: { number: number; bytes: number; path: string }[]
}

export interface DownloadOffer {
  step: 'pin' | 'email' | 'ready'
  gallery: { id: string; title: string; count: number; expiresAt: string | null }
  sizes: Partial<Record<'original' | 'web', number>>
  builds: DownloadBuild[]
}

interface Passes {
  view: string | null
  guest: string | null
  download: string | null
}

function query(passes: Passes): string {
  const params = new URLSearchParams()

  if (passes.guest) params.set('guest_pass', passes.guest)
  if (passes.download) params.set('download_pass', passes.download)

  const encoded = params.toString()

  return encoded === '' ? '' : `?${encoded}`
}

export async function readOffer(
  apiBase: string,
  galleryId: string,
  requestId: string,
  passes: Passes,
): Promise<{ status: number; offer: DownloadOffer | null }> {
  const response = await callApi(
    apiBase,
    `/api/galleries/${galleryId}/download${query(passes)}`,
    requestId,
    { headers: { Accept: 'application/json' } },
    passes.view,
  )

  return { status: response.status, offer: response.ok ? await response.json() : null }
}

export async function buyDownloadPass(
  apiBase: string,
  galleryId: string,
  requestId: string,
  pin: string,
  viewPass: string | null,
): Promise<string | null> {
  const response = await callApi(
    apiBase,
    `/api/galleries/${galleryId}/download/pin`,
    requestId,
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ pin }),
    },
    viewPass,
  )

  return response.ok ? ((await response.json()) as { pass: string }).pass : null
}

export async function prepareDownload(
  apiBase: string,
  galleryId: string,
  requestId: string,
  size: string,
  passes: Passes,
): Promise<boolean> {
  const response = await callApi(
    apiBase,
    `/api/galleries/${galleryId}/download/vorbereiten`,
    requestId,
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ size, guest_pass: passes.guest, download_pass: passes.download }),
    },
    passes.view,
  )

  return response.ok
}

export function formatBytes(bytes: number): string {
  if (bytes >= 1024 ** 3) return `${(bytes / 1024 ** 3).toFixed(1).replace('.', ',')} GB`

  return `${Math.max(1, Math.round(bytes / 1024 ** 2))} MB`
}
