import { describe, expect, it } from 'vitest'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import GalleryAccess from './GalleryAccess.vue'

// SSR string render through the real compiler and the real i18n catalog —
// pins that the ⚙ pane's access block carries the link and both secret
// fields once it moved out of the record head (galerie-fundament layout fix).
function render(props: Record<string, unknown>) {
  const i18n = createI18n({ legacy: false, locale: 'de', messages: { de } })
  const app = createSSRApp(GalleryAccess, props)
  app.use(i18n)
  return renderToString(app)
}

const gallery = {
  id: 'g1',
  url: 'https://example.test/galerien/g1',
  viewPassword: 'geheim',
  downloadPin: '4711',
  expiresAt: null,
}

describe('GalleryAccess', () => {
  it('renders the link and both secret fields', async () => {
    const html = await render({ gallery, siblings: [] })

    expect(html).toContain(gallery.url)
    expect(html).toContain('geheim')
    expect(html).toContain('4711')
    expect(html).toContain(de.gallery.gateView)
    expect(html).toContain(de.gallery.gateDownload)
  })
})
