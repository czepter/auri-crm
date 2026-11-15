import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

// Its own config rather than reusing vite.config.ts, which loads
// laravel-vite-plugin. That plugin refuses to start in CI ("You should not run
// the Vite HMR server in CI environments"), so vitest died in its startup phase
// before collecting a single file.
//
// Suppressing the check with LARAVEL_BYPASS_ENV_CHECK would have worked and is
// the wrong shape: unit tests have no use for the dev server, the manifest or
// the refresh watcher. Same reasoning as withoutVite() on the PHP side — the
// test run should not depend on the asset pipeline. Building the assets for
// real stays the e2e job's business.
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
      '@inertiajs/vue3': fileURLToPath(new URL('./.storybook/inertia.ts', import.meta.url)),
    },
  },
  test: {
    environment: 'node',
    include: ['resources/**/*.{test,spec}.ts'],
    setupFiles: ['./vitest.setup.ts'],
  },
})
