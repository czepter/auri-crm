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
  url: 'http://localhost:4321/bahlmann/hochzeit-bahlmann',
  eventDate: null,
  viewPassword: null,
  downloadPin: null,
  requiresVerifiedEmail: false,
  expiresAt: null,
  offers: { downloads: true, lists: true, comments: true },
  permissions: {
    client: { favorites: true, comments: true, hide: true, share: true },
    guest: { favorites: true, comments: false, hide: false, share: true },
  },
  introEnabled: false,
  studioName: 'Atelier Nordlicht',
  viewMode: 'none' as const,
  watermarkText: null,
  downloadRule: 'open' as const,
  downloadInvoiceId: null,
  downloadSizes: ['original', 'web'] as ('original' | 'web')[],
  expiryReminder: false,
  invoices: [{ id: 'inv1', number: 'R-2026-004', status: 'open' }],
  followsPlan: false,
}

const base = { gallery, siblings: [], versions: [], lists: [] }

describe('Settings', () => {
  it('Allgemein shows the address, wrapped in the URL it produces', async () => {
    const html = await render({ ...base, settingsSection: 'allgemein' })

    expect(html).toContain('http://localhost:4321/bahlmann/')
    expect(html).toContain('hochzeit-bahlmann')
    expect(html).toContain(de.galleryRecord.slugHint)
  })

  it('Zugang carries the link and the view password', async () => {
    const html = await render({ ...base, settingsSection: 'zugang' })

    expect(html).toContain(gallery.url)
    expect(html).toContain(de.gallery.gateView)
  })

  it('Download carries the download PIN — no longer in Zugang', async () => {
    const html = await render({ ...base, settingsSection: 'download' })

    expect(html).toContain(de.gallery.gateDownload)
    expect(html).not.toContain(de.gallerySettings.downloadsLabel)
  })

  it('Allgemein carries the expiry reminder switch', async () => {
    const html = await render({ ...base, settingsSection: 'allgemein' })

    expect(html).toContain(de.galleryRecord.expiryReminder)
  })

  it('Listen carries the empty state once nobody has picked anything', async () => {
    const html = await render({ ...base, settingsSection: 'listen' })

    expect(html).toContain(de.gallery.listEmpty)
  })

  it('Freigabe carries the matrix with a dash for guests hiding, and the intro switch', async () => {
    const html = await render({ ...base, settingsSection: 'freigabe' })

    expect(html).toContain(de.galleryRecord.permission.comments)
    expect(html).toContain(de.galleryRecord.intro)
    expect(html.match(/id="cb-perm-/g)).toHaveLength(7)
    expect(html).not.toContain('cb-perm-guest-hide')
    expect(html).not.toContain(de.gallerySettings.downloadsLabel)
  })

  it('Freigabe offers the view modes and download rules, and the portal summary', async () => {
    const html = await render({ ...base, settingsSection: 'freigabe' })

    for (const mode of Object.values(de.galleryRecord.viewMode)) expect(html).toContain(mode)
    for (const rule of Object.values(de.galleries.download)) expect(html).toContain(rule)
    expect(html).toContain(de.galleryRecord.inPortal)
    expect(html).not.toContain('download-invoice')
    expect(html).not.toContain('placeholder="Atelier Nordlicht"')
  })

  it('Freigabe asks for the invoice once downloads wait for payment, and for the text once it is a watermark', async () => {
    const html = await render({
      ...base,
      settingsSection: 'freigabe',
      gallery: { ...gallery, downloadRule: 'after_payment', viewMode: 'text' },
    })

    expect(html).toContain('id="download-invoice"')
    expect(html).toContain('placeholder="Atelier Nordlicht"')
  })
})
