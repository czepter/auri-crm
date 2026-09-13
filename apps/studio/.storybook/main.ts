import type { StorybookConfig } from '@storybook/vue3-vite'
import { fileURLToPath, URL } from 'node:url'

/*
 * Der Baukasten der Oberfläche, an der laufenden Anwendung vorbei.
 *
 * Storybook lädt vite.config.ts mit — und darin steckt laravel-vite-plugin,
 * das einen Hot-File schreibt und in CI den Dienst verweigert. Für einen
 * Katalog ohne Laravel ist es überflüssig, also fliegt es hier raus. Vue und
 * Tailwind bleiben: ohne sie rendert keine Komponente so, wie sie im Produkt
 * aussieht. Derselbe Grund, aus dem vitest.config.ts eine eigene Datei ist.
 */
const config: StorybookConfig = {
  stories: ['../resources/js/**/*.stories.@(ts|tsx)'],
  // fonts.css lädt Hanken Grotesk unter /fonts — im Produkt liefert Laravel
  // das aus public/. Ohne diese Zeile fällt der Katalog auf system-ui zurück
  // und zeigt eine Schrift, die das Produkt nicht hat.
  staticDirs: ['../public'],
  // Der Katalog eines Kundenverwaltungssystems schickt keine Nutzungsdaten
  // nach außen, auch keine anonymen.
  core: { disableTelemetry: true },
  framework: {
    name: '@storybook/vue3-vite',
    options: {},
  },
  viteFinal: async (config) => {
    // Die Bildschirme lesen ihre Werte über usePage() und schicken über
    // router — beides hängt an einer laufenden Anwendung. Der Alias zeigt auf
    // eine Attrappe, damit ein Bildschirm nicht wissen muss, dass er im
    // Katalog steht.
    config.resolve = {
      ...config.resolve,
      alias: {
        ...config.resolve?.alias,
        '@inertiajs/vue3': fileURLToPath(new URL('./inertia.ts', import.meta.url)),
      },
    }

    config.plugins = (config.plugins ?? []).filter(
      (plugin) => !(plugin && typeof plugin === 'object' && 'name' in plugin && String(plugin.name).startsWith('laravel')),
    )

    return config
  },
}

export default config
