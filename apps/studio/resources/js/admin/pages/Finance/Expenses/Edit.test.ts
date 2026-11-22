// @vitest-environment happy-dom
import { createApp, h, nextTick, ref } from 'vue'
import { createI18n } from 'vue-i18n'
import { afterEach, expect, it, vi } from 'vitest'
import { setPage, usePage } from '../../../../../../.storybook/inertia'
import de from '../../../../locales/de.json'
import Edit from './Edit.vue'
import meta, { Entwurf, Neu } from './Edit.stories'

vi.mock('@inertiajs/vue3', () => import('../../../../../../.storybook/inertia'))

afterEach(() => document.body.replaceChildren())

it('rebuilds the form for the saved expense instead of keeping the create form', async () => {
  vi.stubGlobal('fetch', () => new Promise(() => {}))
  Object.defineProperty(document, 'fonts', { value: { ready: Promise.resolve() }, configurable: true })
  setPage({ auth: { user: { id: 'u', name: 'A', email: 'a@b.c' }, can: ['manage_finances'] } }, '/finanzen/ausgaben/neu')

  const props = ref<Record<string, unknown>>({ ...meta.args, ...Neu.args })
  const root = document.createElement('div')
  document.body.append(root)
  const app = createApp({ render: () => h(Edit, props.value as never) })
  app.config.globalProperties.$page = usePage() as never
  app.use(createI18n({ legacy: false, locale: 'de', fallbackLocale: 'de', messages: { de } }))
  app.mount(root)

  const buttons = () => [...root.querySelectorAll('button')].map((button) => button.textContent?.trim())
  expect(buttons()).toContain('Buchen')
  expect(buttons()).not.toContain('Verwerfen')

  props.value = { ...props.value, ...Entwurf.args }
  await nextTick()

  expect(root.querySelector('h1')?.textContent).toContain('Ausgabe')
  expect(root.querySelector('h1')?.textContent).not.toContain('Neue')
  expect(buttons()).toContain('Verwerfen')
  expect(buttons()).toContain('Löschen')

  props.value = { ...props.value, expense: { ...Entwurf.args!.expense!, status: 'booked' } }
  await nextTick()

  expect(buttons()).toContain('Als Entwurf')
  expect(buttons()).not.toContain('Buchen')
  expect(buttons()).not.toContain('Löschen')

  props.value = { ...props.value, expense: { ...Entwurf.args!.expense!, status: 'ignored' } }
  await nextTick()

  expect(buttons()).toContain('Wiederherstellen')
  expect(root.querySelector('fieldset')?.disabled).toBe(true)
  app.unmount()
})
