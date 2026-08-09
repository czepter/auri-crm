import { createApp, h, type DefineComponent } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import '../../css/admin.css'

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
      .mount(el)
  },
})
