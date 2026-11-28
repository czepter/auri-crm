import { defineConfig } from 'astro/config'
import node from '@astrojs/node'
import tailwindcss from '@tailwindcss/vite'

// G-08: the browser never gets a direct storage URL (G-01a's delivery rule),
// and which GalleryVersion is "current" can change the moment a studio owner
// republishes — so a gallery page can't be built once at deploy time the way
// a static Astro site would. Server output, rendering on every request,
// against the api surface's read routes (routes/api.php).
export default defineConfig({
  output: 'server',
  adapter: node({ mode: 'standalone' }),
  // TLS endet am Proxy. Ohne vertrautes X-Forwarded-Proto rekonstruiert Astro
  // http://… und die Origin-Prüfung weist jedes POST-Formular ab. Jede Studio-
  // Domain ist erlaubt, weil der Container nur hinter dem Proxy erreichbar ist.
  security: { allowedDomains: [{}] },
  // Dasselbe Tailwind wie im Studio, damit die beiden Oberflächen nicht auf
  // zwei Systemen für eine Gestaltung auseinanderlaufen.
  vite: { plugins: [tailwindcss()] },
})
