<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import FormShell from '@/ui/page/FormShell.vue'
import Card from '@/ui/page/Card.vue'
import { Button } from '@/ui/button'
import { DropArea } from '@/ui/drop-area'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Table, TableCell, TableHead, TableRow } from '@/ui/table'

type Row = { line: number; name: string; email: string | null; status: 'create' | 'existing' | 'repeated' | 'invalid'; reason: string | null }

const props = defineProps<{ socialPlatforms: { value: string; label: string }[] }>()

const { t } = useI18n()

const IGNORE = 'none'

const fields = computed(() => [
  ...['name', 'firstName', 'lastName', 'email', 'phone', 'street', 'postcode', 'city'].map((key) => ({
    value: key.replace(/[A-Z]/g, (letter) => `_${letter.toLowerCase()}`),
    label: t(`contacts.${key}`),
  })),
  ...props.socialPlatforms,
])

const form = useForm({ file: null as File | null, mapping: [] as string[] })
const headers = ref<string[]>([])
const samples = ref<(string | null)[]>([])
const rows = ref<Row[]>([])
const error = ref<string | null>(null)

const created = computed(() => rows.value.filter((row) => row.status === 'create').length)
const skipped = computed(() => rows.value.filter((row) => row.status === 'existing' || row.status === 'repeated').length)
const invalid = computed(() => rows.value.filter((row) => row.status === 'invalid').length)

// A later answer wins even if it lands first.
let requestId = 0

async function analyze(withMapping: boolean) {
  const id = ++requestId
  const body = new FormData()

  body.append('file', form.file!)

  if (withMapping) {
    form.mapping.forEach((field) => body.append('mapping[]', field))
  }

  const response = await fetch('/kontakte/import/vorschau', {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
    },
    body,
  })

  if (id !== requestId) return

  const json = response.headers.get('content-type')?.includes('json')
  const data = json ? await response.json() : null

  if (!response.ok || !data) {
    error.value = data?.errors?.file?.[0] ?? data?.message ?? t('contacts.importFailed')
    rows.value = []
    return
  }

  error.value = null
  headers.value = data.headers
  samples.value = data.samples
  form.mapping = data.mapping
  rows.value = data.rows
}

function pick([file]: File[]) {
  form.file = file
  headers.value = []
  analyze(false)
}

function assign(column: number, value: string) {
  const field = value === IGNORE ? '' : value

  form.mapping = form.mapping.map((current, index) => (index === column ? field : field !== '' && current === field ? '' : current))
  analyze(true)
}

function label(row: Row) {
  return row.status === 'invalid' ? row.reason : t(`contacts.import${row.status[0].toUpperCase()}${row.status.slice(1)}`)
}

function submit() {
  form.post('/kontakte/import')
}
</script>

<template>
  <AdminLayout active="kontakte">
    <FormShell :kicker="$t('contacts.title')" kicker-href="/kontakte" :title="$t('contacts.import')" wide>
      <form class="flex max-w-[720px] flex-col gap-3" @submit.prevent="submit">
        <Card :title="$t('contacts.importFile')">
          <DropArea accept=".csv,.txt,text/csv" @files="pick">
            <span class="text-ui">{{ form.file?.name ?? $t('contacts.importChoose') }}</span>
          </DropArea>
          <p v-if="error ?? form.errors.file" class="mt-2 text-small text-destructive">{{ error ?? form.errors.file }}</p>
        </Card>

        <Card v-if="headers.length" :title="$t('contacts.importColumns')">
          <div
            v-for="(header, column) in headers"
            :key="column"
            class="flex items-center gap-4 py-2 border-b border-rule"
          >
            <span class="min-w-0 flex-1">
              <span class="block truncate text-ui font-semibold">{{ header }}</span>
              <span class="block truncate text-small text-ink-2">{{ samples[column] }}</span>
            </span>
            <Select :model-value="form.mapping[column] || IGNORE" @update:model-value="assign(column, String($event))">
              <SelectTrigger class="w-48" :aria-label="header"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem :value="IGNORE">{{ $t('contacts.importIgnore') }}</SelectItem>
                <SelectItem v-for="field in fields" :key="field.value" :value="field.value">{{ field.label }}</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </Card>

        <Card v-if="rows.length" :title="$t('contacts.importRows')">
          <template #meta>
            <span class="text-small tabular-nums text-ink-2">{{ created }} {{ $t('contacts.importCreate') }} · {{ skipped }} {{ $t('contacts.importSkipped') }} · {{ invalid }} {{ $t('contacts.importInvalid') }}</span>
          </template>
          <Table>
            <thead>
              <tr>
                <TableHead>{{ $t('contacts.importLine') }}</TableHead>
                <TableHead>{{ $t('contacts.name') }}</TableHead>
                <TableHead>{{ $t('contacts.email') }}</TableHead>
                <TableHead />
              </tr>
            </thead>
            <tbody>
              <TableRow v-for="row in rows" :key="row.line">
                <TableCell tone="muted" class="tabular-nums">{{ row.line }}</TableCell>
                <TableCell tone="name">{{ row.name }}</TableCell>
                <TableCell tone="muted">{{ row.email ?? '—' }}</TableCell>
                <TableCell :class="row.status === 'invalid' ? 'text-destructive' : row.status === 'create' ? '' : 'text-ink-2'">{{ label(row) }}</TableCell>
              </TableRow>
            </tbody>
          </Table>
        </Card>

        <div class="mt-2 flex items-center gap-3 pt-4 border-t border-rule [&>button:last-child]:ml-auto">
          <Link
            class="text-small tracking-[-0.01em] whitespace-nowrap text-ring no-underline hover:underline"
            href="/kontakte"
          >{{ $t('common.cancel') }}</Link>
          <Button variant="default" type="submit" :disabled="form.processing || created === 0">{{ $t('contacts.importSubmit', created) }}</Button>
        </div>
      </form>
    </FormShell>
  </AdminLayout>
</template>
