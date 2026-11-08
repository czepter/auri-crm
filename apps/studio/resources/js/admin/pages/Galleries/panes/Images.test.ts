import { describe, expect, it } from 'vitest'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import Images from './Images.vue'

// SSR string render through the real compiler and the real i18n catalog —
// vue-tsc cannot see whether Alle (activeSetId === null) actually reaches a
// combined view, or whether the density control actually leaves the
// template while it is active. This does.
function render(props: Record<string, unknown>) {
  const i18n = createI18n({ legacy: false, locale: 'de', messages: { de } })
  const app = createSSRApp(Images, props)
  app.use(i18n)
  return renderToString(app)
}

const sets = [
  { id: 'a', name: 'Highlights', count: 1, density: 'medium' },
  { id: 'b', name: 'Trauung', count: 1, density: 'large' },
]

const assets = [
  { id: '1', key: 'a.jpg', setId: 'a', filename: 'a.jpg', src: '/bild/a', isCover: true },
  { id: '2', key: 'b.jpg', setId: 'b', filename: 'b.jpg', src: '/bild/b', isCover: false },
]

const gallery = { id: 'g1' }
const base = { gallery, sets, assets, failedAssets: [], pendingUploads: [] }

// The shape every gallery actually starts in (Gallery::booted() creates
// exactly one set) — R22 fixed Record.vue handing this pane activeSetId:
// null here, which left no way to reach the sole set's own controls.
const soloSet = [{ id: 'a', name: 'Highlights', count: 1, density: 'medium' }]
const soloAssets = [{ id: '1', key: 'a.jpg', setId: 'a', filename: 'a.jpg', src: '/bild/a', isCover: true }]
const solo = { gallery, sets: soloSet, assets: soloAssets, failedAssets: [], pendingUploads: [] }

describe('Images', () => {
  // R22: a freshly created gallery has exactly one set, and the record column
  // hides Alle along with the rest of its row list while there is only
  // one — so this is the state every gallery starts in, not an edge case.
  it('a gallery with only one set: heads with its name, offers the upload action and the density control', async () => {
    const html = await render({ ...solo, activeSetId: 'a' })

    expect(html).toContain('Highlights')
    expect(html).not.toContain(de.gallery.all)
    expect(html).toContain(de.media.upload)
    expect(html).toContain('role="combobox"')
  })

  // useVirtualList only fills its window against a real element size, which
  // an SSR string render never has — the grid body renders empty either
  // way, so these check the header (title, count) and the density control
  // rather than the (virtualized) pictures themselves.
  it('Alle: heads with the gallery total and leaves the density control out — it is not a set (R21)', async () => {
    const html = await render({ ...base, activeSetId: null })

    expect(html).toContain(de.gallery.all)
    expect(html).toContain('>2<')
    expect(html).not.toContain('role="combobox"')
  })

  it('a single set: heads with its own name, count and the density control', async () => {
    const html = await render({ ...base, activeSetId: 'a' })

    expect(html).toContain('Highlights')
    expect(html).toContain('>1<')
    expect(html).toContain('role="combobox"')
  })

  it('leaves the selection bar out while nothing is selected', async () => {
    const html = await render({ ...base, activeSetId: null })

    expect(html).not.toContain(de.galleryRecord.moveTo)
  })

  // Alle hat kein Zielset, nimmt Bilder aber trotzdem an — das Ziel klaert
  // uploadTarget, notfalls ueber den Set-Dialog.
  it('offers the upload action while Alle is open', async () => {
    const html = await render({ ...base, activeSetId: null })

    expect(html).toContain(de.media.upload)
  })

  it('offers the upload action once a real set is open', async () => {
    const html = await render({ ...base, activeSetId: 'a' })

    expect(html).toContain(de.media.upload)
  })

  // The one-set fixture, empty: an empty gallery starts here, and the drop
  // zone is the only thing there is to do — it must fill the picture area,
  // not sit under the grid's reserved height (galerie-fundament layout fix).
  it('an empty set renders the drop zone inside the picture area, not after a spacer', async () => {
    const empty = { ...solo, assets: [] }
    const html = await render({ ...empty, activeSetId: 'a' })

    expect(html).toContain(de.media.dropHere)
    // useVirtualList's container carries overflow-y: auto — its absence
    // means the grid's reserved-height wrapper never rendered.
    expect(html).not.toContain('overflow-y')
  })

  it('surfaces a Failed asset with its retry action', async () => {
    const html = await render({
      ...base,
      activeSetId: 'a',
      failedAssets: [{ publicId: 'f1', filename: 'IMG_1.jpg', error: 'defekt' }],
    })

    expect(html).toContain('IMG_1.jpg')
    expect(html).toContain(de.media.retryProcessing)
  })

  // The gap between "bytes are up" and "asset exists" — G-03's own ingest
  // queue, not yet an Asset row. Placeholder tiles, not a hidden picture,
  // so a gallery holding uploads that have not reached the grid yet does
  // not read as empty.
  it('shows a pending upload as a placeholder tile, not a picture', async () => {
    const html = await render({
      ...base,
      activeSetId: 'a',
      pendingUploads: [{ id: 'u1', setId: 'a', filename: 'IMG_9.jpg', stage: 'processing' }],
    })

    expect(html).toContain('IMG_9.jpg')
    expect(html).toContain(de.media.processing)
  })

  it('labels a still-transferring upload differently from one waiting on ingest', async () => {
    const html = await render({
      ...base,
      activeSetId: 'a',
      pendingUploads: [{ id: 'u1', setId: 'a', filename: 'IMG_9.jpg', stage: 'transferring' }],
    })

    expect(html).toContain(de.media.transferring)
    expect(html).not.toContain(de.media.processing)
  })

  // The set header count stays the truth about pictures that exist — a
  // pending upload sits beside it as a second, honest number rather than
  // inflating the first one before anything was actually ingested.
  it('keeps the picture count and the pending count apart in the header', async () => {
    const html = await render({
      ...base,
      activeSetId: 'a',
      pendingUploads: [{ id: 'u1', setId: 'a', filename: 'IMG_9.jpg', stage: 'processing' }],
    })

    expect(html).toContain('>1<')
    expect(html).toContain(de.media.pendingCount.split(' | ')[0])
  })

  it('leaves the pending count out while nothing is pending', async () => {
    const html = await render({ ...base, activeSetId: 'a' })

    expect(html).not.toContain(de.media.processing)
    expect(html).not.toContain(de.media.transferring)
  })

  // A set with no pictures yet but an upload in flight is not the empty
  // state — the drop zone must not swallow the whole picture area while
  // there is a placeholder tile to show.
  it('a set with only a pending upload does not fall back to the full-height drop zone', async () => {
    const html = await render({
      ...solo,
      activeSetId: 'a',
      assets: [],
      pendingUploads: [{ id: 'u1', setId: 'a', filename: 'IMG_9.jpg', stage: 'processing' }],
    })

    expect(html).toContain('IMG_9.jpg')
    expect(html).not.toContain(de.media.dropHere)
  })
})
