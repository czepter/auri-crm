import { describe, expect, it } from 'vitest'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import { DEFAULT_RELEASE } from '@/admin/lib/galleryPreset'
import GalleryReleaseFields from './GalleryReleaseFields.vue'

function render(props: Record<string, unknown>) {
  const app = createSSRApp(GalleryReleaseFields, props)
  app.use(createI18n({ legacy: false, locale: 'de', messages: { de } }))

  return renderToString(app)
}

const text = { ...DEFAULT_RELEASE, view_mode: 'text' as const, download_rule: 'after_payment' as const }

describe('GalleryReleaseFields', () => {
  it('asks for the invoice only where the gallery has invoices', async () => {
    expect(await render({ modelValue: text, invoices: [] })).toContain('id="download-invoice"')
    expect(await render({ modelValue: text })).not.toContain('id="download-invoice"')
  })

  it('leaves out the watermark text in a preset', async () => {
    expect(await render({ modelValue: text, placeholder: 'Atelier' })).toContain('placeholder="Atelier"')
    expect(await render({ modelValue: text, placeholder: 'Atelier', watermarkField: false })).not.toContain('placeholder="Atelier"')
  })
})
