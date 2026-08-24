import { describe, expect, it } from 'vitest'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import GalleryRail from './GalleryRail.vue'

// SSR string render through the real compiler and the real i18n catalog —
// vue-tsc cannot see whether a v-if actually leaves a control reachable at a
// given prop shape. This does: it renders GalleryRail exactly as Record.vue
// would, at the two prop shapes that gate the rail's own conditionals.
function render(props: Record<string, unknown>) {
  const i18n = createI18n({ legacy: false, locale: 'de', messages: { de } })
  const app = createSSRApp(GalleryRail, props)
  app.use(i18n)
  return renderToString(app)
}

const oneSet = [{ id: 'a', name: 'Highlights', count: 3, density: 'medium' }]
const twoSets = [
  { id: 'a', name: 'Highlights', count: 3, density: 'medium' },
  { id: 'b', name: 'Trauung', count: 5, density: 'medium' },
]

describe('GalleryRail', () => {
  it('offers + Set on a fresh gallery, before a second set can exist', async () => {
    const html = await render({ coverSrc: null, tab: 'bilder', sets: oneSet, activeSetId: 'a', error: null })

    expect(html).toContain(de.galleryRecord.addSet)
    expect(html).not.toContain(de.gallery.all)
  })

  it('shows Alle with the gallery total once a second set exists', async () => {
    const html = await render({ coverSrc: null, tab: 'bilder', sets: twoSets, activeSetId: null, error: null })

    expect(html).toContain(de.gallery.all)
    expect(html).toContain('8')
  })

  // An empty grey rectangle is not worth the rail's best position — without
  // a cover, the rail begins with the icon tabs instead.
  it('renders no cover thumbnail when there is no cover', async () => {
    const html = await render({ coverSrc: null, tab: 'bilder', sets: oneSet, activeSetId: 'a', error: null })

    expect(html).not.toContain('<img')
  })

  it('renders the cover thumbnail once a cover exists', async () => {
    const html = await render({ coverSrc: '/bild/cover', tab: 'bilder', sets: oneSet, activeSetId: 'a', error: null })

    expect(html).toContain('<img')
  })
})
