import { describe, expect, it } from 'vitest'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import RecordNav from './RecordNav.vue'

// Was ein v-if bei einer gegebenen Prop-Form wirklich stehen lässt, sieht
// vue-tsc nicht. Ein SSR-Render durch den echten Compiler und den echten
// Katalog sieht es.
function render(props: Record<string, unknown>) {
  const i18n = createI18n({ legacy: false, locale: 'de', messages: { de } })
  const app = createSSRApp(RecordNav, props)
  app.use(i18n)
  return renderToString(app)
}

const child = (key: string, label: string, count: number) => ({
  key, label, translate: false, href: `/galerien/g/bilder/${key}`, count, reorderable: true,
})

const tabs = (children: ReturnType<typeof child>[], count: number | null = null, countTo: number | null = null) => [
  { key: 'bilder', label: 'galleryRecord.tab.bilder', href: '/galerien/g/bilder', count, countTo, children },
  { key: 'gestaltung', label: 'galleryRecord.tab.gestaltung', href: '/galerien/g/gestaltung', count: null, countTo: null, children: [] },
]

describe('RecordNav', () => {
  it('leaves the children folded away while another area is open', async () => {
    const open = await render({ tabs: tabs([child('a', 'Trauung', 3)]), active: 'bilder' })
    const shut = await render({ tabs: tabs([child('a', 'Trauung', 3)]), active: 'gestaltung' })

    expect(open).toContain('Trauung')
    expect(shut).not.toContain('Trauung')
  })

  it('writes a set name as it stands and a key through the catalogue', async () => {
    const html = await render({
      tabs: tabs([
        { key: 'alle', label: 'gallery.all', translate: true, href: '/galerien/g/bilder', count: 8, reorderable: false },
        child('a', 'Trauung', 3),
      ]),
      active: 'bilder',
    })

    expect(html).toContain(de.gallery.all)
    expect(html).toContain('Trauung')
    expect(html).not.toContain('gallery.all')
  })

  it('sets the second number beside the first only once the draft parts', async () => {
    expect(await render({ tabs: tabs([], 220, 230), active: 'bilder' })).toContain('230')
    expect(await render({ tabs: tabs([], 220, null), active: 'bilder' })).not.toContain('→')
  })

  it('says nothing where there is no number', async () => {
    const html = await render({ tabs: tabs([], null, null), active: 'bilder' })

    expect(html).toContain(de.galleryRecord.tab.bilder)
    expect(html).not.toContain('tabular-nums')
  })

  it('hangs a grip on a reorderable child and none on Alle', async () => {
    const html = await render({
      tabs: tabs([
        { key: 'alle', label: 'gallery.all', translate: true, href: '/galerien/g/bilder', count: 8, reorderable: false },
        child('a', 'Trauung', 3),
      ]),
      active: 'bilder',
    })

    expect(html.match(/draggable="true"/g)).toHaveLength(1)
  })

  it('takes the width it is given', async () => {
    expect(await render({ tabs: tabs([]), active: 'bilder', width: 212 })).toContain('width:212px')
    expect(await render({ tabs: tabs([]), active: 'bilder' })).toContain('width:150px')
  })
})
