import { describe, expect, it } from 'vitest'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import Settings from './Settings.vue'

// SSR string render through the real compiler and the real i18n catalog —
// three defects already shipped in this stage from a control that never
// rendered at all. This pins that each of the three sections actually
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
  isExternal: false,
  listedOnHome: false,
  homePinned: false,
  homeBlocker: null,
  homeMode: 'overview' as const,
  categoryId: null,
  categories: [{ id: 'c1', name: 'Hochzeit' }],
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

const base = { gallery, siblings: [] }

describe('Settings', () => {
  it('Zugang carries the link, the view password, the expiry and its reminder', async () => {
    const html = await render({ ...base, settingsSection: 'zugang' })

    expect(html).toContain(gallery.url)
    expect(html).toContain(de.gallery.gateView)
    expect(html).toContain(de.galleryRecord.expiryReminder)
  })

  it('folds the rare settings into details that show their value', async () => {
    const html = await render({ ...base, settingsSection: 'zugang', gallery: { ...gallery, categoryId: 'c1' } })

    expect(html.match(/<details/g)).toHaveLength(5)
    expect(html).not.toContain('<details open')
    expect(html).toContain(de.galleryRecord.slugHint)
    expect(html).toMatch(/Kategorie<\/span>.*Hochzeit/)
    expect(html.match(/id="cb-perm-/g)).toHaveLength(7)
    expect(html).not.toContain('cb-perm-guest-hide')
  })

  it('an external gallery keeps neither category nor home-page settings', async () => {
    const html = await render({ ...base, settingsSection: 'zugang', gallery: { ...gallery, isExternal: true } })

    expect(html.match(/<details/g)).toHaveLength(2)
    expect(html).toContain('id="gallery-url"')
  })

  it('Download carries the rule, the sizes and the PIN', async () => {
    const html = await render({ ...base, settingsSection: 'download' })

    for (const rule of Object.values(de.galleries.download)) expect(html).toContain(rule)
    expect(html).toContain(de.gallery.gateDownload)
    expect(html).toContain('cb-size-web')
    expect(html).not.toContain('download-invoice')
    expect(html).not.toContain('cb-perm-')
  })

  it('Download asks for the invoice once downloads wait for payment', async () => {
    const html = await render({ ...base, settingsSection: 'download', gallery: { ...gallery, downloadRule: 'after_payment' } })

    expect(html).toContain('id="download-invoice"')
  })

  it('Darstellung carries the view mode and the intro, and the watermark text once it is one', async () => {
    const html = await render({ ...base, settingsSection: 'darstellung' })

    for (const mode of Object.values(de.galleryRecord.viewMode)) expect(html).toContain(mode)
    expect(html).toContain(de.galleryRecord.intro)
    expect(html).not.toContain('placeholder="Atelier Nordlicht"')
    expect(await render({ ...base, settingsSection: 'darstellung', gallery: { ...gallery, viewMode: 'text' } })).toContain('placeholder="Atelier Nordlicht"')
  })
})
