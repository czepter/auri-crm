import { createApp, h, type DefineComponent } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { createI18n } from 'vue-i18n'
import de from '../locales/de.json'
import '../../css/admin.css'

// German is the only locale. The catalog exists so a second one would be a
// file rather than a rewrite, not because one is planned — and so the German
// compound nouns that break column widths are in the markup from the start
// rather than arriving in a late translation pass.
const i18n = createI18n({
  legacy: false,
  locale: 'de',
  fallbackLocale: 'de',
  messages: { de },
  datetimeFormats: {
    de: {
      short: { day: '2-digit', month: '2-digit', year: 'numeric' },
    },
  },
  numberFormats: {
    de: {
      currency: { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 },
    },
  },
})

// The glob is relative to this file, so the admin bundle can only ever
// resolve admin pages. Surface isolation holds in the build, not just at
// runtime.
const pages = import.meta.glob<{ default: DefineComponent }>('./pages/**/*.vue')

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
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(i18n)
      .mount(el)
  },
})
