<script setup lang="ts">
import { ref } from 'vue'
import { Button } from '@/ui/button'

// T-12's own acceptance criterion: a template is checked before it reaches a
// client. Posts type+body as currently typed — saved or not — to the one
// preview endpoint DocumentTemplateController exposes, and shows back
// whatever it rendered against sample data. A plain fetch() rather than
// Inertia's router: the response is a raw HTML document meant for an
// <iframe>, not a page visit.
const props = defineProps<{ type: string; body: string }>()

const html = ref<string | null>(null)
const loading = ref(false)

async function preview() {
  loading.value = true

  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''

  const response = await fetch('/dokumentvorlagen/vorschau', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'text/html' },
    body: JSON.stringify({ type: props.type, body: props.body }),
  })

  html.value = response.ok ? await response.text() : null
  loading.value = false
}
</script>

<template>
  <div class="fld grp">
    <Button variant="ghost" type="button" :disabled="loading" @click="preview">
      {{ $t('documentTemplates.preview') }}
    </Button>
    <!-- sandbox="" (no allow-* tokens at all): a template body is
         staff-authored HTML, not trusted script — an empty sandbox strips
         script execution, form submission and top-level navigation, so a
         <script> in a preview can never run with this session's origin. -->
    <iframe
      v-if="html"
      :srcdoc="html"
      sandbox=""
      style="width: 100%; height: 420px; margin-top: var(--s3); border: 1px solid var(--border, #ddd)"
    />
  </div>
</template>
