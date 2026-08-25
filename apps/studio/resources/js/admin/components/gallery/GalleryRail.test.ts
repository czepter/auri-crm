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

const offers = { downloads: true, lists: true, comments: true }

describe('GalleryRail', () => {
  it('offers + Set on a fresh gallery, before a second set can exist', async () => {
    const html = await render({ coverSrc: null, tab: 'bilder', sets: oneSet, activeSetId: 'a', error: null, settingsSection: 'allgemein', offers })

    expect(html).toContain(de.galleryRecord.addSet)
    expect(html).not.toContain(de.gallery.all)
  })

  it('shows Alle with the gallery total once a second set exists', async () => {
    const html = await render({ coverSrc: null, tab: 'bilder', sets: twoSets, activeSetId: null, error: null, settingsSection: 'allgemein', offers })

    expect(html).toContain(de.gallery.all)
    expect(html).toContain('8')
  })

  // An empty grey rectangle is not worth the rail's best position — without
  // a cover, the rail begins with the icon tabs instead.
  it('renders no cover thumbnail when there is no cover', async () => {
    const html = await render({ coverSrc: null, tab: 'bilder', sets: oneSet, activeSetId: 'a', error: null, settingsSection: 'allgemein', offers })

    expect(html).not.toContain('<img')
  })

  it('renders the cover thumbnail once a cover exists', async () => {
    const html = await render({ coverSrc: '/bild/cover', tab: 'bilder', sets: oneSet, activeSetId: 'a', error: null, settingsSection: 'allgemein', offers })

    expect(html).toContain('<img')
  })

  it('shows the settings sub-navigation only while ⚙ is the active tab', async () => {
    const html = await render({ coverSrc: null, tab: 'bilder', sets: oneSet, activeSetId: 'a', error: null, settingsSection: 'allgemein', offers })

    expect(html).not.toContain(de.galleryRecord.settingsNav.zugang)
  })

  it('lists Allgemein, Zugang, Download, Listen and Kommentare in order once ⚙ is open', async () => {
    const html = await render({ coverSrc: null, tab: 'einstellungen', sets: oneSet, activeSetId: 'a', error: null, settingsSection: 'allgemein', offers })

    const positions = ['allgemein', 'zugang', 'download', 'listen', 'kommentare']
      .map((name) => html.indexOf(de.galleryRecord.settingsNav[name as keyof typeof de.galleryRecord.settingsNav]))

    expect(positions.every((position) => position !== -1)).toBe(true)
    expect(positions).toEqual([...positions].sort((a, b) => a - b))
  })

  // Asserted against the pill's own class + text, not a bare 'An'/'Aus' —
  // both are two- and three-letter German words the wider catalog is full
  // of, so a naive toContain would turn red for the wrong reason the moment
  // any other label on this rail happened to carry one as a substring.
  it('shows Download, Listen and Kommentare as An once their switch is on', async () => {
    const html = await render({ coverSrc: null, tab: 'einstellungen', sets: oneSet, activeSetId: 'a', error: null, settingsSection: 'allgemein', offers })

    expect(html).toMatch(new RegExp(`bg-ok-bg text-ok[^<]*>${de.galleryPresets.on}<`))
    expect(html).not.toMatch(new RegExp(`bg-sunk-2 text-faint[^<]*>${de.galleryPresets.off}<`))
  })

  it('shows Download, Listen and Kommentare as Aus once their switch is off', async () => {
    const html = await render({
      coverSrc: null, tab: 'einstellungen', sets: oneSet, activeSetId: 'a', error: null, settingsSection: 'allgemein',
      offers: { downloads: false, lists: false, comments: false },
    })

    expect(html).toMatch(new RegExp(`bg-sunk-2 text-faint[^<]*>${de.galleryPresets.off}<`))
    expect(html).not.toMatch(new RegExp(`bg-ok-bg text-ok[^<]*>${de.galleryPresets.on}<`))
  })
})
