import { defineConfig } from 'astro/config'
import node from '@astrojs/node'

// G-08: the browser never gets a direct storage URL (G-01a's delivery rule),
// and which GalleryVersion is "current" can change the moment a studio owner
// republishes — so a gallery page can't be built once at deploy time the way
// a static Astro site would. Server output, rendering on every request,
// against the api surface's read routes (routes/api.php).
export default defineConfig({
  output: 'server',
  adapter: node({ mode: 'standalone' }),
})
