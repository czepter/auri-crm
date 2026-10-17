import type { Preview } from '@storybook/vue3-vite'
import { setup } from '@storybook/vue3-vite'
import { createI18n } from 'vue-i18n'
import { setPage, usePage } from './inertia'
import de from '../resources/js/locales/de.json'
import '../resources/css/admin.css'
import '../resources/css/theme-auri.css'

/*
 * Dieselbe Einrichtung wie admin/main.ts, minus Inertia: die Bausteine lesen
 * ihre Beschriftungen aus demselben Katalog, sonst zeigt der Katalog Schlüssel
 * statt Wörter. Ein fehlender Schlüssel wirft hier nicht — in einer Story ist
 * er ein Tippfehler, kein Produktionsfehler.
 */
setup((app) => {
  // Inertias Plugin legt sonst $page an; die Bildschirme lesen es in ihren
  // Vorlagen, und ohne das hier wirft jede Story, die eine solche Vorlage
  // rendert.
  app.config.globalProperties.$page = usePage()

  app.use(
    createI18n({
      legacy: false,
      locale: 'de',
      fallbackLocale: 'de',
      messages: { de },
    }),
  )
})

const preview: Preview = {
  // Auri steht als Kandidat neben Nordlicht, bis es entschieden ist. Auf
  // <html>, nicht auf einem Wrapper: Dialoge teleportieren nach <body>.
  globalTypes: {
    theme: {
      description: 'Theme',
      toolbar: {
        icon: 'paintbrush',
        dynamicTitle: true,
        items: [
          { value: 'nordlicht', title: 'Nordlicht' },
          { value: 'auri', title: 'Auri hell' },
          { value: 'auri-dark', title: 'Auri dunkel' },
        ],
      },
    },
  },
  initialGlobals: { theme: 'nordlicht' },
  // Was der Server auf jeden Bildschirm legt, legt hier der Dekorator. Eine
  // Story ergänzt ihre eigenen Werte über parameters.page.
  decorators: [
    (story, context) => {
      setPage(
        (context.parameters.page ?? {}) as Record<string, unknown>,
        (context.parameters.url ?? '/') as string,
      )

      const theme = context.globals.theme as string
      const root = document.documentElement
      if (theme === 'nordlicht') delete root.dataset.theme
      else root.dataset.theme = 'auri'
      root.classList.toggle('dark', theme === 'auri-dark')

      return story()
    },
  ],
  parameters: {
    layout: 'centered',
    controls: { expanded: true },
  },
}

export default preview
