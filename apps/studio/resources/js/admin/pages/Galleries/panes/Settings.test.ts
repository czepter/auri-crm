import { describe, expect, it } from 'vitest'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import Settings from './Settings.vue'

// SSR string render through the real compiler and the real i18n catalog —
// three defects already shipped in this stage from a control that never
// rendered at all. This pins that each of the five sections actually
// reaches the page.
function render(props: Record<string, unknown>) {
  const i18n = createI18n({ legacy: false, locale: 'de', messages: { de } })
  const app = createSSRApp(Settings, props)
  app.use(i18n)
  return renderToString(app)
}

// The shape every gallery actually starts in: a slug already generated from
// the title, no version yet, no lists.
const gallery = {
  id: 'g1',
  title: 'Hochzeit Bahlmann',
  slug: 'hochzeit-bahlmann',
  url: 'http://localhost:4321/g/hochzeit-bahlmann',
  eventDate: null,
  viewPassword: null,
  downloadPin: null,
  requiresVerifiedEmail: false,
  expiresAt: null,
  balanceSettled: true,
  requiresSettledBalance: false,
  offers: { downloads: true, lists: true, comments: true },
}

const base = { gallery, siblings: [], versions: [], lists: [] }

describe('Settings', () => {
  it('Allgemein shows the address, wrapped in the URL it produces', async () => {
    const html = await render({ ...base, settingsSection: 'allgemein' })

    expect(html).toContain('http://localhost:4321/g/')
    expect(html).toContain('hochzeit-bahlmann')
    expect(html).toContain(de.galleryRecord.slugHint)
    expect(html).toContain(de.galleryRecord.saveAsPreset)
  })

  it('Zugang carries the link and the view password', async () => {
    const html = await render({ ...base, settingsSection: 'zugang' })

    expect(html).toContain(gallery.url)
    expect(html).toContain(de.gallery.gateView)
  })

  it('Download carries its switch and the download PIN — no longer in Zugang', async () => {
    const html = await render({ ...base, settingsSection: 'download' })

    expect(html).toContain(de.gallerySettings.downloadsLabel)
    expect(html).toContain(de.gallery.gateDownload)
  })

  it('Listen carries its switch and the empty state once nobody has picked anything', async () => {
    const html = await render({ ...base, settingsSection: 'listen' })

    expect(html).toContain(de.gallerySettings.listsLabel)
    expect(html).toContain(de.gallery.listEmpty)
  })

  it('Kommentare carries its own switch, and none of the other sections’ controls', async () => {
    const html = await render({ ...base, settingsSection: 'kommentare' })

    expect(html).toContain(de.gallerySettings.commentsLabel)
    expect(html).not.toContain(de.gallerySettings.downloadsLabel)
    expect(html).not.toContain(de.galleryRecord.slugLabel)
  })

  it('shows the studio-owed notice when a balance is due, on every section', async () => {
    const html = await render({
      ...base,
      gallery: { ...gallery, requiresSettledBalance: true, balanceSettled: false },
      settingsSection: 'kommentare',
    })

    expect(html).toContain(de.galleryRecord.blockedByBalance)
  })
})
