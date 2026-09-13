import type { Preview } from '@storybook/vue3-vite'
import { setup } from '@storybook/vue3-vite'
import { createI18n } from 'vue-i18n'
import { setPage, usePage } from './inertia'
import de from '../resources/js/locales/de.json'
import '../resources/css/admin.css'

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
  // Was der Server auf jeden Bildschirm legt, legt hier der Dekorator. Eine
  // Story ergänzt ihre eigenen Werte über parameters.page.
  decorators: [
    (story, context) => {
      setPage(
        (context.parameters.page ?? {}) as Record<string, unknown>,
        (context.parameters.url ?? '/') as string,
      )

      return story()
    },
  ],
  parameters: {
    layout: 'centered',
    controls: { expanded: true },
  },
}

export default preview
