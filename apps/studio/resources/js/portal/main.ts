import { createApp, h, type DefineComponent } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { createI18n } from 'vue-i18n'
import de from '../locales/de.json'
import '../../css/portal.css'

const i18n = createI18n({
  legacy: false,
  locale: 'de',
  fallbackLocale: 'de',
  messages: { de },
  // Same rule the admin entrypoint carries: loud in dev, harmless in production.
  missing: (_locale, key) => {
    if (import.meta.env.DEV) {
      throw new Error(`Fehlender Übersetzungsschlüssel: ${key}`)
    }

    return key
  },
})

// The glob is relative to this file, so the portal bundle can only ever
// resolve portal pages — surface isolation holds in the build, not just at
// runtime. The admin's entrypoint says the same thing about its own.
const pages = import.meta.glob<{ default: DefineComponent }>('./pages/**/*.vue')

createInertiaApp({
  title: (title) => (title ? `${title} · Studio` : 'Studio'),
  resolve: (name) => {
    const page = pages[`./pages/${name}.vue`]

    if (!page) {
      throw new Error(`Unknown portal page: ${name}`)
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
