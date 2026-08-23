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

describe('Images', () => {
  // useVirtualList only fills its window against a real element size, which
  // an SSR string render never has — the grid body renders empty either
  // way, so these check the header (title, count) and the density control
  // rather than the (virtualized) pictures themselves.
  it('Alle: heads with the gallery total and leaves the density control out — it is not a set (R21)', async () => {
    const html = await render({ gallery, sets, assets, activeSetId: null })

    expect(html).toContain(de.gallery.all)
    expect(html).toContain('>2<')
    expect(html).not.toContain(de.galleryRecord.density.small)
  })

  it('a single set: heads with its own name, count and the density control', async () => {
    const html = await render({ gallery, sets, assets, activeSetId: 'a' })

    expect(html).toContain('Highlights')
    expect(html).toContain('>1<')
    expect(html).toContain(de.galleryRecord.density.small)
  })

  it('leaves the selection bar out while nothing is selected', async () => {
    const html = await render({ gallery, sets, assets, activeSetId: null })

    expect(html).not.toContain(de.galleryRecord.moveTo)
  })
})
