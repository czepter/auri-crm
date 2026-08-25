import { describe, expect, it, vi } from 'vitest'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import RecordPage from './Record.vue'

// AdminLayout (which every Galleries page wraps) and CommandPalette both read
// usePage().props and the template global $page directly — neither exists
// without a real Inertia app mounted around the render. Stubbing both is
// what makes this component SSR-renderable at all; nothing here asserts
// against the stub itself.
vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@inertiajs/vue3')>()

  return {
    ...actual,
    usePage: () => ({
      props: {
        auth: { user: { name: 'Team Nordlicht' }, can: ['manage_portal_access'] },
        nav: {},
        brand: 'Atelier Nordlicht',
        recent: [],
      },
    }),
  }
})

function render(props: Record<string, unknown>) {
  const i18n = createI18n({ legacy: false, locale: 'de', messages: { de } })
  const app = createSSRApp(RecordPage, props)
  // Only $page.props.brand is read (AdminLayout's wordmark fallback) — the
  // rest of Inertia's Page<PageProps> shape is irrelevant to this stub.
  app.config.globalProperties.$page = { props: { brand: 'Atelier Nordlicht' } } as typeof app.config.globalProperties.$page
  app.use(i18n)
  return renderToString(app)
}

const gallery = {
  id: 'g1',
  title: 'Hochzeit Bahlmann',
  slug: 'hochzeit-bahlmann',
  url: 'https://example.test/g/hochzeit-bahlmann',
  eventDate: null,
  viewPassword: null,
  downloadPin: null,
  requiresVerifiedEmail: false,
  expiresAt: null,
  balanceSettled: true,
  requiresSettledBalance: false,
  design: null,
  offers: { downloads: true, lists: true, comments: true },
  deviates: true,
  withdrawn: false,
  canPublish: true,
}

// The shape every gallery actually starts in: one set, Gallery::booted()'s
// own default, no pictures yet.
const sets = [{ id: 'a', name: 'Highlights', count: 0, sortKey: 'taken_at', sortDir: 'asc', density: 'medium' }]

const base = {
  gallery,
  siblings: [],
  sets,
  assets: [],
  versions: [],
  builds: [],
  lists: [],
  activity: { downloads: [], marks: [], comments: [], views: [] },
  failedAssets: [],
  pendingUploads: [],
  figures: { published: null, assets: 0, expiresAt: null, marked: 0, downloaded: 0, viewed: 0 },
}

describe('Record', () => {
  // The coordinator's fix: PublishGallery refuses an empty draft, so a
  // control that can only fail must not be offered — the first thing anyone
  // does with a fresh gallery is try this button.
  it('does not offer Veröffentlichen while the draft has no pictures', async () => {
    const html = await render(base)

    expect(html).not.toContain(de.galleryRecord.publish)
  })

  it('offers Veröffentlichen once the draft has pictures', async () => {
    const html = await render({ ...base, figures: { ...base.figures, assets: 2 } })

    expect(html).toContain(de.galleryRecord.publish)
  })

  // The old head kept a password field and a six-column counter band in
  // clear on every gallery, ~290px of cold data before anything you could
  // act on. Both moved into the ⚙ pane (galerie-fundament layout fix).
  it('the header renders no password field and no counter band', async () => {
    const html = await render({
      ...base,
      gallery: { ...gallery, viewPassword: 'geheim' },
      figures: { ...base.figures, marked: 3, downloaded: 2, viewed: 5 },
    })

    expect(html).not.toContain('geheim')
    expect(html).not.toContain(de.galleryRecord.marked)
    expect(html).not.toContain(de.galleryRecord.downloaded)
    expect(html).not.toContain(de.galleryRecord.viewed)
  })
})
