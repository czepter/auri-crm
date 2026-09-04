/**
 * G-02: the browser side of app-proxied multipart ingest. Plain fetch, no
 * upload library — the app has none installed, and a resumable-upload
 * client is a handful of functions, not a dependency.
 *
 * The bytes never go to S3 directly: every request here is to this
 * application's own /galerien and /uploads routes
 * (App\Media\Http\Controllers\MultipartUploadController), which relay each
 * chunk to the bucket server-side.
 */

/** What identifies "the same upload" to a returning tab — never a storage key, which the browser is never given. */
export interface FileFingerprint {
  filename: string
  file_size: number
  file_modified_at: number
}

export function fingerprintOf(file: File): FileFingerprint {
  return { filename: file.name, file_size: file.size, file_modified_at: file.lastModified }
}

export interface UploadSession {
  publicId: string
  partSize: number
  /** Part numbers (1-based) the bucket already has, per ListParts — see StartOrResumeMultipartUpload. */
  donePartNumbers: Set<number>
  /** The set this session actually targets — a resumed row keeps the one it was started against, which can differ from the set requested here. */
  gallerySetId: string
}

/** The XSRF-TOKEN cookie Laravel's session middleware sets, copied into the header it looks for on a raw fetch — the same thing axios (and so Inertia's own requests) does automatically. Every plain `fetch` in this app needs it; PreventRequestForgery answers a request without it with a 419 HTML body. */
export function xsrfHeader(): Record<string, string> {
  const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)

  return match ? { 'X-XSRF-TOKEN': decodeURIComponent(match[1]) } : {}
}

async function asJson<T>(response: Response): Promise<T> {
  if (!response.ok) {
    throw new Error(`${response.status} ${response.statusText}`)
  }

  return response.json() as Promise<T>
}

/** The pre-upload duplicate check (G-02): filename + size against the gallery's whole draft, before a single byte travels. */
export async function checkDuplicates(
  galleryId: string,
  files: { filename: string; size: number }[],
): Promise<{ filename: string; size: number; assetId: string }[]> {
  const response = await fetch(`/galerien/${galleryId}/uploads/pruefen`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...xsrfHeader() },
    credentials: 'same-origin',
    body: JSON.stringify({ files }),
  })

  const data = await asJson<{ duplicates: { filename: string; size: number; assetId: string }[] }>(response)

  return data.duplicates
}

/** How many parts $partSize chunks $fileSize bytes into — the last one is whatever remainder is left, same as the server's own fixed part_size. */
export function partCount(fileSize: number, partSize: number): number {
  return Math.max(1, Math.ceil(fileSize / partSize))
}

/** Byte range [start, end) for one 1-based part number, against a fixed part size. */
export function partRange(partNumber: number, partSize: number, fileSize: number): [number, number] {
  const start = (partNumber - 1) * partSize
  return [start, Math.min(start + partSize, fileSize)]
}

/** Every session action beyond "start or resume" is scoped by the upload's own public id, not by the gallery — routes/admin.php's galleries.uploads.parts/complete/destroy. */
const UPLOADS_BASE = '/uploads'

/** Starts a fresh session, or resumes the one already in progress for this exact file — see the fingerprint's own doc comment. $createUrl is the gallery-scoped POST route (galleries.uploads.store). */
export async function startOrResumeUpload(
  createUrl: string,
  file: File,
  gallerySetId: string,
  replacesAssetId?: string,
): Promise<UploadSession> {
  const response = await fetch(createUrl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...xsrfHeader() },
    credentials: 'same-origin',
    body: JSON.stringify({ ...fingerprintOf(file), gallery_set_id: gallerySetId, replaces_asset_id: replacesAssetId }),
  })

  const data = await asJson<{
    upload: { public_id: string; part_size: number; gallery_set_id: string }
    parts: { part_number: number }[]
  }>(response)

  return {
    publicId: data.upload.public_id,
    partSize: data.upload.part_size,
    gallerySetId: data.upload.gallery_set_id,
    donePartNumbers: new Set(data.parts.map((part) => part.part_number)),
  }
}

/** Relays one chunk straight from the File's own Blob slice — never read into a JS string or array buffer first, so a 30 GB selection never sits in memory beyond whatever one part costs. */
async function uploadPart(publicId: string, partNumber: number, chunk: Blob): Promise<void> {
  const response = await fetch(`${UPLOADS_BASE}/${publicId}/teile/${partNumber}`, {
    method: 'PUT',
    headers: xsrfHeader(),
    credentials: 'same-origin',
    body: chunk,
  })

  await asJson(response)
}

async function completeUpload(publicId: string): Promise<void> {
  const response = await fetch(`${UPLOADS_BASE}/${publicId}/abschluss`, {
    method: 'POST',
    headers: xsrfHeader(),
    credentials: 'same-origin',
  })

  await asJson(response)
}

export async function abortUpload(publicId: string): Promise<void> {
  await fetch(`${UPLOADS_BASE}/${publicId}`, {
    method: 'DELETE',
    headers: xsrfHeader(),
    credentials: 'same-origin',
  })
}

export interface UploadProgress {
  partsDone: number
  partsTotal: number
}

/**
 * Starts or resumes, uploads every part the bucket does not already have,
 * then completes — the whole client-side half of the resume story. A
 * dropped connection or a killed tab simply leaves some parts undone; the
 * next call with the same File (the browser's own re-picked or re-dropped
 * folder, mockup 05-medien's "denselben Ordner") asks the bucket again via
 * startOrResumeUpload and only sends the gap, never re-sending a part
 * already there.
 *
 * ponytail: one part at a time, no parallel chunk uploads. Add bounded
 * concurrency if a 30 GB transfer over a single stream measures too slow in
 * practice — nothing here forecloses it, since each part is independent.
 *
 * Returns the set the session actually landed in — see UploadSession's own
 * doc comment. A caller compares this against the set it asked for to catch
 * a resumed upload quietly finishing somewhere else.
 */
export async function uploadFile(
  createUrl: string,
  file: File,
  onProgress: (progress: UploadProgress) => void,
  gallerySetId: string,
  replacesAssetId?: string,
): Promise<{ gallerySetId: string }> {
  const session = await startOrResumeUpload(createUrl, file, gallerySetId, replacesAssetId)
  const total = partCount(file.size, session.partSize)

  let done = session.donePartNumbers.size
  onProgress({ partsDone: done, partsTotal: total })

  for (let partNumber = 1; partNumber <= total; partNumber++) {
    if (session.donePartNumbers.has(partNumber)) {
      continue
    }

    const [start, end] = partRange(partNumber, session.partSize, file.size)
    await uploadPart(session.publicId, partNumber, file.slice(start, end))

    done += 1
    onProgress({ partsDone: done, partsTotal: total })
  }

  await completeUpload(session.publicId)

  return { gallerySetId: session.gallerySetId }
}
