import { createApp, h, type DefineComponent } from 'vue'
import { createInertiaApp, router } from '@inertiajs/vue3'
import { createI18n } from 'vue-i18n'
import de from '../locales/de.json'
import { followLiveUpdates } from './lib/liveReload'
import '../../css/admin.css'
import 'vue-sonner/style.css'

// German is the only locale. The catalog exists so a second one would be a
// file rather than a rewrite, not because one is planned — and so the German
// compound nouns that break column widths are in the markup from the start
// rather than arriving in a late translation pass.
const i18n = createI18n({
  legacy: false,
  locale: 'de',
  fallbackLocale: 'de',
  messages: { de },
  // A key with no entry rendered as itself — 'lead.group.xyz' in the middle of
  // a screen — and shipped that way. In dev it stops the render instead; in
  // production the key still stands rather than taking the page down with it.
  missing: (_locale, key) => {
    if (import.meta.env.DEV) {
      throw new Error(`Fehlender Übersetzungsschlüssel: ${key}`)
    }

    return key
  },
})

// The glob is relative to this file, so the admin bundle can only ever
// resolve admin pages. Surface isolation holds in the build, not just at
// runtime.
const pages = import.meta.glob<{ default: DefineComponent }>('./pages/**/*.vue')

router.on('navigate', (event) => followLiveUpdates(event.detail.page.props.live))

// The browser's validation bubble stays off; Field words the reason itself.
document.addEventListener('invalid', (event) => event.preventDefault(), true)

createInertiaApp({
  title: (title) => (title ? `${title} · Studio` : 'Studio'),
  resolve: (name) => {
    const page = pages[`./pages/${name}.vue`]

    if (!page) {
      throw new Error(`Unknown admin page: ${name}`)
    }

    return page().then((module) => module.default)
  },
  setup({ el, App, props, plugin }) {
    followLiveUpdates(props.initialPage.props.live)

    createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(i18n)
      .mount(el)
  },
})
