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

async function render(props: Record<string, unknown>) {
  const i18n = createI18n({ legacy: false, locale: 'de', messages: { de } })
  const app = createSSRApp(RecordPage, props)
  // Only $page.props.brand is read (AdminLayout's wordmark fallback) — the
  // rest of Inertia's Page<PageProps> shape is irrelevant to this stub.
  app.config.globalProperties.$page = { props: { brand: 'Atelier Nordlicht' } } as typeof app.config.globalProperties.$page
  app.use(i18n)

  // The More menu's own content is a reka-ui Teleport: renderToString(app)
  // alone answers only the anchor comments, never the teleported subtree —
  // the real content lands in the render context's own teleports map
  // instead (Vue's documented SSR behaviour for Teleport). Force-mounted
  // (DropdownMenuContent force-mount, see Record.vue) so it exists here at
  // all, closed-state hidden by data-[state=closed]:hidden for the browser.
  const context: { teleports?: Record<string, string> } = {}
  const html = await renderToString(app, context)

  return html + Object.values(context.teleports ?? {}).join('')
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
  prerelease: false,
  canPublish: true,
}

// The shape every gallery actually starts in: one set, Gallery::booted()'s
// own default, no pictures yet.
const sets = [{ id: 'a', name: 'Highlights', count: 0, sortKey: 'taken_at', sortDir: 'asc', density: 'medium' }]

const base = {
  gallery,
  siblings: [],
  presets: [],
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

  // Production's two starting states for the preview action bar: a brand
  // new draft with nothing uploaded yet, and one with pictures but still no
  // GalleryVersion (viola-chris) — Vorschau must render in both, since it
  // is the one control that answers "no version" by design.
  it('offers Vorschau and the More menu with no pictures at all', async () => {
    const html = await render(base)

    expect(html).toContain(de.galleryRecord.preview)
    expect(html).toContain(de.galleryRecord.more)
    expect(html).toContain(de.galleryRecord.copyLink)
    expect(html).toContain(de.galleryRecord.createPreviewLink)
  })

  it('offers Vorschau and the More menu with pictures and no version', async () => {
    const html = await render({ ...base, figures: { ...base.figures, assets: 22 } })

    expect(html).toContain(de.galleryRecord.preview)
    expect(html).toContain(de.galleryRecord.more)
  })

  it('hides Vorschaulink erzeugen without canPublish', async () => {
    const html = await render({ ...base, gallery: { ...gallery, canPublish: false } })

    expect(html).not.toContain(de.galleryRecord.createPreviewLink)
  })

  it('hides Vorlage anwenden with no presets to apply', async () => {
    const html = await render(base)

    expect(html).not.toContain(de.galleryRecord.applyPreset)
  })

  it('offers Vorlage anwenden once a preset exists', async () => {
    const html = await render({ ...base, presets: [{ id: 'p1', name: 'Hochzeit', design: null, expiryMode: 'inherit', expiryDays: null, requiresSettledBalance: null, downloadsEnabled: null, listsEnabled: null, commentsEnabled: null }] })

    expect(html).toContain(de.galleryRecord.applyPreset)
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

  // R39/R32: the More menu's own entry point into pre-release, offered only
  // before the first publish — the same condition GalleryPrereleaseController
  // enforces server-side.
  it('offers Pre-release in the More menu before any version exists', async () => {
    const html = await render(base)

    expect(html).toContain(de.galleryRecord.prerelease)
    expect(html).not.toContain(de.galleryRecord.prereleaseStop)
  })

  it('hides Pre-release once a version has been published', async () => {
    const html = await render({ ...base, figures: { ...base.figures, published: 1, assets: 2 } })

    expect(html).not.toContain(de.galleryRecord.prerelease)
  })

  // The head is the one place the studio can see this state at a glance —
  // without it, pre-release is invisible outside the More menu.
  it('shows the pre-release chip in the head while active, and offers to stop it', async () => {
    const html = await render({ ...base, gallery: { ...gallery, prerelease: true } })

    expect(html).toContain(de.galleryRecord.prereleaseActive)
    expect(html).toContain(de.galleryRecord.prereleaseStop)
  })
})
