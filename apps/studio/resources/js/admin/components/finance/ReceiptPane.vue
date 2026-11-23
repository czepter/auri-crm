<script setup lang="ts">
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { FileText, X } from 'lucide-vue-next'
import { DropArea } from '@/ui/drop-area'
import { Button } from '@/ui/button'
import PdfViewer from './PdfViewer.vue'
import { xsrfHeader } from '@/admin/lib/multipartUpload'

type Doc = App.Finance.Data.FinanceDocumentData

const props = defineProps<{ expenseId?: string | null; readonly?: boolean }>()
const documents = defineModel<Doc[]>('documents', { required: true })

const selected = ref<string | null>(documents.value[0]?.id ?? null)
const error = ref<string | null>(null)
const duplicateOf = ref<string | null>(null)
const uploading = ref(false)

const current = () => documents.value.find((doc) => doc.id === selected.value) ?? documents.value[0] ?? null

async function upload(files: File[]) {
  error.value = null
  duplicateOf.value = null
  uploading.value = true

  const body = new FormData()
  body.append('file', files[0])

  try {
    const response = await fetch('/finanzen/belege', {
      method: 'POST',
      body,
      headers: { Accept: 'application/json', ...xsrfHeader() },
      credentials: 'same-origin',
    })
    const json = await response.json()

    if (!response.ok) {
      error.value = json.errors?.file?.[0] ?? json.message ?? null

      return
    }

    if (!documents.value.some((doc) => doc.id === json.document.id)) documents.value = [...documents.value, json.document]
    selected.value = json.document.id
    duplicateOf.value = json.duplicateOfExpenseId && json.duplicateOfExpenseId !== props.expenseId ? json.duplicateOfExpenseId : null
  } finally {
    uploading.value = false
  }
}

function remove(id: string) {
  documents.value = documents.value.filter((doc) => doc.id !== id)
  if (selected.value === id) selected.value = documents.value[0]?.id ?? null
}
</script>

<template>
  <div class="flex min-w-0 flex-col gap-3">
    <PdfViewer v-if="current()?.mime === 'application/pdf'" :key="current()?.id" :url="current()!.url" :title="current()!.filename" />
    <div v-else class="flex min-h-[420px] items-center justify-center overflow-hidden rounded-lg border border-border bg-sunk max-[900px]:min-h-[280px]" :class="!current() && 'max-[999px]:hidden'">
      <img v-if="current()" :key="current()?.id" :src="current()?.url" :alt="current()?.filename" class="max-h-[640px] max-w-full object-contain">
      <FileText v-else class="size-9 stroke-ink-2 stroke-[1.5]" aria-hidden="true" />
    </div>

    <ul v-if="documents.length > 1 || (documents.length && !readonly)" class="flex flex-wrap gap-2">
      <li v-for="doc in documents" :key="doc.id" class="flex items-center gap-1 rounded-md bg-sunk py-1 pr-1 pl-2.5 text-small" :class="doc.id === selected && 'ring-1 ring-foreground'">
        <button type="button" class="max-w-[180px] truncate" @click="selected = doc.id">{{ doc.filename }}</button>
        <Button v-if="!readonly" variant="ghost" size="icon" type="button" class="size-6" :aria-label="$t('common.remove')" :title="$t('common.remove')" @click="remove(doc.id)"><X /></Button>
      </li>
    </ul>

    <template v-if="!readonly">
      <DropArea accept="application/pdf,image/jpeg,image/png,image/webp" @files="upload">
        <span class="text-small text-ink-2">{{ uploading ? $t('common.saving') : $t('financeExpense.upload') }}</span>
      </DropArea>
      <p v-if="error" class="text-small text-destructive">{{ error }}</p>
      <p v-if="duplicateOf" class="flex flex-wrap items-center gap-2 text-small">
        {{ $t('financeExpense.receiptExists') }}
        <Link class="font-medium underline" :href="`/finanzen/ausgaben/${duplicateOf}`">{{ $t('financeExpense.toExpense') }}</Link>
      </p>
    </template>
  </div>
</template>
