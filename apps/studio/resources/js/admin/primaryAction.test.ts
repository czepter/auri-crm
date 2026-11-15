// @vitest-environment happy-dom
import { createApp, h, type Component } from 'vue'
import { createI18n } from 'vue-i18n'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { setPage, usePage } from '../../../.storybook/inertia'
import de from '../locales/de.json'

vi.mock('@inertiajs/vue3', () => import('../../../.storybook/inertia'))

// Editor.js touches Element at import time and only runs on mount.
vi.mock('@editorjs/editorjs', () => ({ default: class { isReady = Promise.resolve(); destroy() {} } }))
vi.mock('@editorjs/header', () => ({ default: class {} }))
vi.mock('@editorjs/list', () => ({ default: class {} }))
vi.mock('@editorjs/quote', () => ({ default: class {} }))
vi.mock('@editorjs/delimiter', () => ({ default: class {} }))
vi.mock('@editorjs/table', () => ({ default: class {} }))

/*
 * Primary is the filled accent Button: data-variant "default" or "pill"
 * (ui/button/index.ts). data-slot is no use here, as-child triggers overwrite
 * it. Only the mounted screen is counted; dialog and sheet content is not in
 * the DOM until opened, so each of those is its own surface and is not checked.
 *
 * Stories are mounted directly instead of through Storybook's portable
 * stories: those import @storybook/vue3, which needs a window before the
 * first line of the test runs.
 */
const PRIMARY = ':is([data-variant="default"], [data-variant="pill"]):not(label)'

// Screen story → why it may show more than one primary.
const EXEMPT: Record<string, string> = {
  'pages/Kalender › ListeLeer': 'phone and desktop empty states are both in the DOM, CSS shows one',
}

type Meta = { component: Component, args?: Record<string, unknown>, parameters?: Record<string, any> }
type Story = { args?: Record<string, unknown>, parameters?: Record<string, any> }

const modules = import.meta.glob<{ default: Meta } & Record<string, Story>>('./pages/**/*.stories.ts', { eager: true })

const cases = Object.entries(modules).flatMap(([path, { default: meta, ...stories }]) =>
  Object.entries(stories).map(([name, story]) => ({ id: `${path.slice(2, -'.stories.ts'.length)} › ${name}`, meta, story })),
)

describe('admin screens', () => {
  afterEach(() => {
    document.body.replaceChildren()
  })

  it.each(cases)('$id shows at most one primary action', async ({ id, meta, story }) => {
    if (id in EXEMPT) return

    vi.stubGlobal('fetch', () => new Promise(() => {}))
    Object.defineProperty(document, 'fonts', { value: { ready: Promise.resolve() }, configurable: true })
    const parameters = { ...meta.parameters, ...story.parameters }
    setPage(parameters.page ?? {}, parameters.url ?? '/')

    const root = document.createElement('div')
    document.body.append(root)

    const app = createApp({ render: () => h(meta.component, { ...meta.args, ...story.args }) })
    app.config.globalProperties.$page = usePage() as never
    app.use(createI18n({ legacy: false, locale: 'de', fallbackLocale: 'de', messages: { de } }))
    app.mount(root)

    const labels = [...root.querySelectorAll(PRIMARY)].map((button) => button.textContent?.trim())

    app.unmount()

    expect(labels).toSatisfy((found: unknown[]) => found.length <= 1)
  })
})
