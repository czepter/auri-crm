<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
import DocumentEditor from '@/admin/components/DocumentEditor.vue'
import DocumentPreviewPane from '@/admin/components/DocumentPreviewPane.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { NONE, fromOption, toOption } from '@/lib/select'
import { serialize } from '@/admin/documents/blocks/serialize'
import type { EditorDocument, PaletteEntry } from '@/admin/documents/blocks/types'

const props = defineProps<{
  types: { value: string; label: string }[]
  shootKinds: { value: string; label: string }[]
  palettes: Record<string, PaletteEntry[]>
  mergeFields: { path: string; sample: string }[]
  starters: Record<string, Starter[]>
  projects: { id: string; name: string }[]
}>()

type Starter = {
  key: string
  name: string
  description: string
  shootKind: string | null
  blocks: EditorDocument
}

// C-16 (#252): the preview reckons with a chosen project rather than sample
// values — only then does an empty placeholder show up here rather than in
// front of the client.
const project = ref<string | null>(null)

// The scope of a starter, in the two numbers that say whether it is a page or
// a contract: how many blocks, and how many placeholders it will ask the
// project for.
function scopeOf(starter: Starter) {
  const text = JSON.stringify(starter.blocks)

  return {
    blocks: starter.blocks.blocks.length,
    fields: new Set(text.match(/\{\{\s*[\w.]+\s*\}\}/g) ?? []).size,
  }
}

const doc = ref<EditorDocument>({ blocks: [] })
const form = useForm({ name: '', body: '', blocks: doc.value, type: 'contract', shoot_kind: '' })

// T-16: the screen opens on a choice rather than on an empty canvas. A
// starter is a starting point and not a link — choosing one copies its blocks
// into an ordinary unsaved draft, and nothing afterwards remembers which one
// it came from.
const chosen = ref<string | null>(null)
const starters = computed(() => props.starters[form.type] ?? [])

function choose(starter: Starter) {
  chosen.value = starter.key
  // Deep copy: the prop is the catalog's own document, and editing the draft
  // must not edit what the other cards would hand out.
  doc.value = JSON.parse(JSON.stringify(starter.blocks))
  form.name = starter.name
  form.shoot_kind = starter.shootKind ?? ''
}

function chooseBlank() {
  chosen.value = 'blank'
  doc.value = { blocks: [] }
  form.name = ''
  form.shoot_kind = ''
}

const palette = computed(() => props.palettes[form.type] ?? [])
const samples = computed(() =>
  Object.fromEntries(props.mergeFields.map((field) => [field.path, field.sample])),
)

function submit() {
  form.body = serialize(doc.value, form.type === 'mail' ? 'mail' : 'print')
  form.blocks = doc.value
  form.transform((data) => ({ ...data, shoot_kind: data.shoot_kind || null })).post('/dokumentvorlagen')
}
</script>

<template>
  <AdminLayout active="dokumentvorlagen">
    <div data-tokens>
      <PageHead :kicker="$t('documentTemplates.title')" :title="$t('documentTemplates.createTitle')">
        <Button as="a" class="ml-auto" href="/dokumentvorlagen" variant="ghost">{{ $t('common.cancel') }}</Button>
        <!-- Nothing to save until a starting point is chosen, and a back that
             returns to the choice rather than leaving the screen. -->
        <Button v-if="chosen !== null" type="button" variant="ghost" @click="chosen = null">
          {{ $t('documentTemplates.starterBack') }}
        </Button>
        <Button v-if="chosen !== null" class="ml-auto" variant="pill" type="button" :disabled="form.processing" @click="submit">
          {{ $t('common.save') }}
        </Button>
      </PageHead>

      <div class="px-8 pt-7 pb-8">
        <p v-if="form.errors.blocks" class="text-[11.5px] leading-[1.45] text-destructive">{{ form.errors.blocks }}</p>
        <p v-if="form.errors.body" class="text-[11.5px] leading-[1.45] text-destructive">{{ form.errors.body }}</p>

        <div class="mt-4 flex gap-6">
          <div class="flex min-w-0 flex-col gap-1.5 max-w-[240px]">
            <Label for="type">{{ $t('documentTemplates.type') }}</Label>
            <Select :model-value="form.type" @update:model-value="(value) => (form.type = String(value))">
              <SelectTrigger id="type"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="option in types" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
              </SelectContent>
            </Select>
            <p v-if="form.errors.type" class="text-[11.5px] leading-[1.45] text-destructive">{{ form.errors.type }}</p>
          </div>
          <div v-if="chosen !== null" class="flex min-w-0 flex-col gap-1.5 max-w-[240px]">
            <Label for="shoot_kind">{{ $t('documentTemplates.shootKind') }}</Label>
            <!-- reka-ui reserves the empty string for "nothing selected", so the
                 "Beliebig" choice needs the NONE stand-in while the model keeps
                 storing '' — see resources/js/lib/select.ts. -->
            <Select :model-value="toOption(form.shoot_kind)" @update:model-value="(value) => (form.shoot_kind = fromOption(value))">
              <SelectTrigger id="shoot_kind"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem :value="NONE">{{ $t('documentTemplates.shootKindAny') }}</SelectItem>
                <SelectItem v-for="kind in shootKinds" :key="kind.value" :value="kind.value">{{ kind.label }}</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div v-if="chosen !== null" class="flex min-w-0 flex-col gap-1.5 max-w-[240px]">
            <Label for="name">{{ $t('documentTemplates.name') }}</Label>
            <Input id="name" v-model="form.name" type="text" required />
            <p v-if="form.errors.name" class="text-[11.5px] leading-[1.45] text-destructive">{{ form.errors.name }}</p>
          </div>
        </div>

        <!-- The screen opens on a choice rather than on an empty page. Each card
             states its scope, because "14 Blöcke · 9 Platzhalter" is what says
             whether this is a page or a contract. The blank one is the last card
             and carries no surface: it is a choice, not the default. -->
        <section v-if="chosen === null" data-tokens>
          <div class="grid grid-cols-3 gap-3">
            <a
              v-for="starter in starters" :key="starter.key"
              class="block rounded-[12px] bg-sunk px-4 py-3.5" href="#"
              @click.prevent="choose(starter)"
            >
              <span class="block text-[14px] leading-[1.3] font-semibold tracking-[-0.016em]">{{ starter.name }}</span>
              <span class="mt-0.5 block text-[10.5px] leading-[1.47] tracking-[0.05em] uppercase text-ink-2">{{ types.find((t) => t.value === form.type)?.label }}</span>
              <span class="mt-[7px] block text-[12px] leading-[1.45] text-ink-2">{{ starter.description }}</span>
              <span class="mt-[9px] block pt-2 text-[11.5px] text-ink-2 shadow-[inset_0_1px_0_#ECECEE]">
                {{ $t('documentTemplates.starterScope', {
                  blocks: scopeOf(starter).blocks,
                  fields: scopeOf(starter).fields,
                }) }}
              </span>
            </a>

            <a class="block rounded-[12px] px-4 py-3.5 shadow-[inset_0_0_0_1px_var(--rule)]" href="#" @click.prevent="chooseBlank">
              <span class="block text-[14px] leading-[1.3] font-semibold tracking-[-0.016em]">{{ $t('documentTemplates.starterBlank') }}</span>
              <span class="mt-[7px] block text-[12px] leading-[1.45] text-ink-2">{{ $t('documentTemplates.starterBlankHint') }}</span>
            </a>
          </div>
        </section>

        <div v-else class="mt-6 grid min-h-0 grid-cols-[minmax(0,1fr)_7px_minmax(0,1fr)] gap-0 max-[1100px]:grid-cols-[minmax(0,1fr)]">
          <section class="flex min-w-0 flex-col">
            <div class="flex items-center gap-2.5 border-b border-border pb-2.5">
              <span class="mb-[5px] text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint">{{ $t('documentTemplates.body') }}</span>
              <span class="ml-auto text-[11.5px] leading-[1.45] text-faint">{{ $t('documentTemplates.bodyHint') }}</span>
            </div>
            <div class="mx-auto mt-6 w-full max-w-[520px] rounded-[2px] border border-border bg-white px-10 pb-10">
              <div class="-mx-10 border-dashed border-line-hover bg-[#FCFBFA] px-3 py-[9px] text-[10px] leading-[1.4] font-medium tracking-[0.06em] uppercase text-faint mb-6 border-b">{{ $t('documentTemplates.letterhead') }}</div>
              <DocumentEditor v-model="doc" :palette="palette" :merge-fields="mergeFields" />
              <div class="-mx-10 border-dashed border-line-hover bg-[#FCFBFA] px-3 py-[9px] text-[10px] leading-[1.4] font-medium tracking-[0.06em] uppercase text-faint -mb-10 mt-6 border-t">{{ $t('documentTemplates.footer') }}</div>
            </div>

            <!-- C-16 (#252): what this document type may not have stands dimmed
                 with its reason rather than missing. Editor.js owns its own plus
                 menu, so the list sits under the sheet instead of inside it —
                 replacing that inserter is a rewrite of the editor shell, not
                 this issue's subject. -->
            <div class="flex flex-wrap gap-x-3.5 gap-y-1.5 px-0.5 pt-2.5 text-[11.5px] text-[#6E6E73]">
              <span
                v-for="entry in palette.filter((block) => !block.inline)"
                :key="entry.type"
                :class="entry.allowed === false && 'text-[#DEDEE1]'"
              >
                {{ entry.label }}
                <template v-if="entry.allowed === false"> · {{ $t('documentTemplates.blockOnly', { types: entry.only }) }}</template>
              </span>
            </div>
          </section>

          <div
            class="flex cursor-col-resize items-center justify-center before:block before:h-full before:w-px before:bg-border before:content-[''] max-[1100px]:hidden"
          />

          <DocumentPreviewPane :type="form.type" :doc="doc" :samples="samples" :project="project">
            <Select
              :model-value="toOption(project)"
              @update:model-value="(value) => project = fromOption(value) || null"
            >
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem :value="NONE">{{ $t('documentTemplates.previewSamples') }}</SelectItem>
                <SelectItem v-for="option in projects" :key="option.id" :value="option.id">{{ option.name }}</SelectItem>
              </SelectContent>
            </Select>
          </DocumentPreviewPane>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

