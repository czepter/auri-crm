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
  url: 'https://example.test/bahlmann/hochzeit-bahlmann',
  eventDate: null,
  viewPassword: null,
  downloadPin: null,
  requiresVerifiedEmail: false,
  expiresAt: null,
  design: null,
  coverFocal: null,
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
  deviates: true,
  withdrawn: false,
  prerelease: false,
  isExternal: false,
  externalUrl: null,
  externalPassword: null,
  info: null,
  canPublish: true,
  canDelete: true,
  copy: null,
  recipient: null,
}

// The shape every gallery actually starts in: one set, Gallery::booted()'s
// own default, no pictures yet.
const sets = [{ id: 'a', name: 'Highlights', count: 0, sortKey: 'taken_at', sortDir: 'asc', density: 'medium', access: 'internal', url: 'https://example.test/bahlmann/hochzeit-bahlmann?set=a' }]

const base = {
  gallery,
  siblings: [],
  presets: [],
  sets,
  assets: [],
  versions: [],
  builds: [],
  lists: [],
  activity: { counts: { favorites: 0, comments: 0, hidden: 0, downloads: 0 }, events: [], favorites: [], hidden: [], task: { available: false, url: null } },
  failedAssets: [],
  pendingUploads: [],
  figures: { published: null, assets: 0, expiresAt: null, marked: 0, downloaded: 0, viewed: 0 },
  area: 'bilder',
  activeSetId: 's1',
  settingsSection: 'allgemein',
  nav: {
    cover: null,
    tabs: [
      { key: 'bilder', label: 'galleryRecord.tab.bilder', href: '/galerien/g1/bilder', count: null, countTo: null, children: [] },
      { key: 'gestaltung', label: 'galleryRecord.tab.gestaltung', href: '/galerien/g1/gestaltung', count: null, countTo: null, children: [] },
      { key: 'einstellungen', label: 'galleryRecord.tab.einstellungen', href: '/galerien/g1/einstellungen/allgemein', count: null, countTo: null, children: [] },
      { key: 'aktivitaet', label: 'galleryRecord.tab.aktivitaet', href: '/galerien/g1/aktivitaet', count: null, countTo: null, children: [] },
    ],
  },
}

const picture = (id: string, isCover = false) => ({
  id, key: `k/${id}`, setId: 's1', src: `/galerien/g1/bilder/${id}/vorschau`,
  filename: `${id}.jpg`, width: 400, height: 300, isCover,
})

describe('Record', () => {
  const activity = {
    counts: { favorites: 3, comments: 1, hidden: 1, downloads: 0 },
    events: [
      { type: 'hide', at: '2026-10-01T18:50:00+02:00', id: 'h1', who: 'Jana', photo: { n: 8, src: '/x' } },
      { type: 'views', at: '2026-10-01T00:00:00+02:00', count: 4 },
    ],
    favorites: [{ n: 3, src: '/y' }],
    hidden: [{ id: 'h1', who: 'Jana', photo: { n: 8, src: '/x' } }],
    task: { available: true, url: null },
  }

  it('offers Antworten on a customer comment and names the studio reply', async () => {
    const comments = [
      { type: 'comment', at: '2026-10-01T18:50:00+02:00', id: 'c1', studio: false, who: 'Jana', photo: { n: 8, src: '/x' }, text: 'Schön' },
      { type: 'comment', at: '2026-10-01T19:00:00+02:00', id: 'c2', studio: true, who: 'Christian', photo: { n: 8, src: '/x' }, text: 'Danke' },
    ]
    const html = await render({ ...base, area: 'aktivitaet', activity: { ...activity, events: comments } })

    expect(html.match(new RegExp(`>${de.galleryRecord.activity.reply}<`, 'g'))).toHaveLength(1)
    expect(html).toContain('Christian antwortet bei Foto 8')
  })

  it('shows the counted pills, the timeline with Zurückholen and the favourites card', async () => {
    const html = await render({ ...base, area: 'aktivitaet', activity })

    expect(html).toContain('Favoriten (3)')
    expect(html).toContain('Jana blendet Foto 8 aus')
    expect(html).toContain('4 Aufrufe')
    expect(html).toContain(de.galleryRecord.activity.restore)
    expect(html).toContain(de.galleryRecord.activity.createTask)
    expect(html).toContain('/galerien/g1/bilder?favoriten=1')
  })

  it('links the open task instead of offering a second one', async () => {
    const html = await render({ ...base, area: 'aktivitaet', activity: { ...activity, task: { available: true, url: '/auftraege/c1/aufgaben' } } })

    expect(html).toContain('/auftraege/c1/aufgaben')
    expect(html).not.toContain(de.galleryRecord.activity.createTask)
  })

  // Die Vorschau zeigte assets[0] und log damit, sobald jemand ein anderes
  // Bild zum Titelbild machte.
  it('shows the chosen cover at the top of Gestaltung, not the first picture', async () => {
    const html = await render({
      ...base,
      area: 'gestaltung',
      assets: [picture('a'), picture('b', true), picture('c')],
    })

    expect(html).toContain('/galerien/g1/bilder/b/vorschau?w=1200')
    expect(html).not.toContain('/galerien/g1/bilder/a/vorschau?w=1200')
  })

  it('falls back to the first picture while nothing is chosen', async () => {
    const html = await render({ ...base, area: 'gestaltung', assets: [picture('a'), picture('b')] })

    expect(html).toContain('/galerien/g1/bilder/a/vorschau?w=1200')
  })

  // Das Titelbild steht nicht zweimal da: oben gross, darunter der Rest.
  it('leaves the cover out of the row beneath it', async () => {
    const html = await render({
      ...base,
      area: 'gestaltung',
      assets: [picture('a'), picture('b', true), picture('c')],
    })

    expect(html).not.toContain('/galerien/g1/bilder/b/vorschau?w=400')
    expect(html).toContain('/galerien/g1/bilder/c/vorschau?w=400')
  })
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

  it('offers the three ways out of a draft in the Veröffentlichen menu', async () => {
    const html = await render({ ...base, gallery: { ...gallery, canMail: true }, figures: { ...base.figures, assets: 2 } })

    expect(html).toContain(de.galleryRecord.publishAndSend)
    expect(html).toContain(de.galleryRecord.publishOnly)
    expect(html).toContain(de.galleryRecord.copyLinkAndPassword)
  })

  it('offers Erneut senden only for a standing, unchanged version', async () => {
    const online = { ...base, gallery: { ...gallery, canMail: true, deviates: false }, figures: { ...base.figures, assets: 2, published: 1 } }

    expect(await render(online)).toContain(de.galleryRecord.resend)
    expect(await render({ ...online, gallery: { ...online.gallery, deviates: true } })).not.toContain(de.galleryRecord.resend)
  })

  it('does not offer Duplizieren for an external gallery', async () => {
    expect(await render(base)).toContain(de.galleryRecord.duplicate)
    expect(await render({ ...base, gallery: { ...gallery, isExternal: true } })).not.toContain(de.galleryRecord.duplicate)
  })

  it('offers Löschen only to those who may delete', async () => {
    expect(await render(base)).toContain(de.common.delete)
    expect(await render({ ...base, gallery: { ...gallery, canDelete: false } })).not.toContain(de.common.delete)
  })

  it('shows copy progress with a way to resume', async () => {
    const html = await render({ ...base, gallery: { ...gallery, copy: { done: 12, total: 40 } } })

    expect(html).toContain('Kopiert 12 von 40')
    expect(html).toContain(de.galleryRecord.copyResume)
    expect(await render(base)).not.toContain(de.galleryRecord.copyResume)
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
    const html = await render({ ...base, presets: [{ id: 'p1', name: 'Hochzeit', design: null, expiryMode: 'inherit', expiryDays: null, downloadsEnabled: null, listsEnabled: null, commentsEnabled: null }] })

    expect(html).toContain(de.galleryRecord.applyPreset)
  })

  // The old head kept a password field and a six-column counter band in
  // clear on every gallery; both live in the ⚙ pane.
  it('the header renders no password field', async () => {
    const html = await render({ ...base, gallery: { ...gallery, viewPassword: 'geheim' } })

    expect(html).not.toContain('geheim')
  })

  it('shows the figures in the rail beside the Bilder grid', async () => {
    const html = await render({ ...base, figures: { ...base.figures, published: 1, assets: 2, marked: 3, downloaded: 2, viewed: 5 } })

    expect(html).toMatch(new RegExp(`${de.galleryRecord.viewed}</dt>\\s*<dd[^>]*>5<`))
    expect(html).toMatch(new RegExp(`${de.galleryRecord.marked}</dt>\\s*<dd[^>]*>3<`))
    expect(html).toMatch(new RegExp(`${de.galleryRecord.downloaded}</dt>\\s*<dd[^>]*>2<`))
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

  // Minor fix round 1: the control 422s for an external gallery
  // (GalleryPrereleaseController::store()'s own guard) — offering a
  // control that cannot succeed is the defect R25 already ruled out once.
  it('hides Pre-release for an external gallery', async () => {
    const html = await render({ ...base, gallery: { ...gallery, isExternal: true } })

    expect(html).not.toContain(de.galleryRecord.prerelease)
  })

  // The head is the one place the studio can see this state at a glance —
  // without it, pre-release is invisible outside the More menu.
  it('shows the pre-release chip in the head while active, and offers to stop it', async () => {
    const html = await render({ ...base, gallery: { ...gallery, prerelease: true } })

    // bg-due-bg is the chip's own class, not shared with the More menu's
    // "Pre-release beenden" item — both contain the substring "Pre-release",
    // so the plain label alone would pass whether or not the chip rendered.
    expect(html).toContain('bg-due-bg')
    expect(html).toContain(de.galleryRecord.prereleaseStop)
  })

  it('shows the state word with the expiry date instead of the version chip', async () => {
    const html = await render({ ...base, figures: { ...base.figures, published: 1, assets: 2, expiresAt: '2026-11-17' } })

    expect(html).toContain('Online bis 17.11.2026')
  })

  it('keeps Zurücknehmen in the More menu, not among the head buttons', async () => {
    const html = await render({ ...base, figures: { ...base.figures, published: 1, assets: 2 } })

    expect(html).toContain(de.galleryRecord.withdraw)
    expect(html).not.toMatch(new RegExp(`<button[^>]*>\\s*${de.galleryRecord.withdraw}`))
  })
})
