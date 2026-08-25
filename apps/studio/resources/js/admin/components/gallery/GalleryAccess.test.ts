import { describe, expect, it } from 'vitest'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import GalleryAccess from './GalleryAccess.vue'

// SSR string render through the real compiler and the real i18n catalog —
// pins that Zugang carries the link and the view password. The download PIN
// moved to the Download section (galerie-einstellungen redesign); a
// regression that brought it back here would go unnoticed without this.
function render(props: Record<string, unknown>) {
  const i18n = createI18n({ legacy: false, locale: 'de', messages: { de } })
  const app = createSSRApp(GalleryAccess, props)
  app.use(i18n)
  return renderToString(app)
}

const gallery = {
  id: 'g1',
  url: 'https://example.test/g/unser-tag',
  viewPassword: 'geheim',
  requiresVerifiedEmail: false,
}

describe('GalleryAccess', () => {
  it('renders the link, a copy action and the view password', async () => {
    const html = await render({ gallery, siblings: [] })

    expect(html).toContain(gallery.url)
    expect(html).toContain('geheim')
    expect(html).toContain(de.gallery.gateView)
    expect(html).toContain(de.gallery.copy)
  })

  it('carries no download PIN field — that moved to the Download section', async () => {
    const html = await render({ gallery: { ...gallery, downloadPin: '4711' }, siblings: [] })

    expect(html).not.toContain(de.gallery.gateDownload)
    expect(html).not.toContain('4711')
  })

  it('offers the E-Mail-Nachweis switch', async () => {
    const html = await render({ gallery, siblings: [] })

    expect(html).toContain(de.galleryRecord.emailGate)
  })
})
