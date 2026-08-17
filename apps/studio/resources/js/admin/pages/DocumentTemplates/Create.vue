<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
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
    <div class="hrow">
      <div>
        <div class="kick">{{ $t('documentTemplates.title') }}</div>
        <h1>{{ $t('documentTemplates.createTitle') }}</h1>
      </div>
      <Button as="a" href="/dokumentvorlagen" variant="ghost">{{ $t('common.cancel') }}</Button>
      <!-- Nothing to save until a starting point is chosen, and a back that
           returns to the choice rather than leaving the screen. -->
      <Button v-if="chosen !== null" type="button" variant="ghost" @click="chosen = null">
        {{ $t('documentTemplates.starterBack') }}
      </Button>
      <Button v-if="chosen !== null" type="button" :disabled="form.processing" @click="submit">
        {{ $t('common.save') }}
      </Button>
    </div>
    <p v-if="form.errors.blocks" class="err">{{ form.errors.blocks }}</p>
    <p v-if="form.errors.body" class="err">{{ form.errors.body }}</p>

    <div class="doc-meta">
      <div class="fld">
        <Label for="type">{{ $t('documentTemplates.type') }}</Label>
        <Select :model-value="form.type" @update:model-value="(value) => (form.type = String(value))">
          <SelectTrigger id="type"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem v-for="option in types" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
          </SelectContent>
        </Select>
        <p v-if="form.errors.type" class="err">{{ form.errors.type }}</p>
      </div>
      <div v-if="chosen !== null" class="fld">
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
      <div v-if="chosen !== null" class="fld">
        <Label for="name">{{ $t('documentTemplates.name') }}</Label>
        <Input id="name" v-model="form.name" type="text" required />
        <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
      </div>
    </div>

    <!-- The screen opens on a choice rather than on an empty page. Each card
         states its scope, because "14 Blöcke · 9 Platzhalter" is what says
         whether this is a page or a contract. The blank one is the last card
         and carries no surface: it is a choice, not the default. -->
    <section v-if="chosen === null" data-theme="crm">
      <div class="starters">
        <a
          v-for="starter in starters" :key="starter.key"
          class="starter" href="#"
          @click.prevent="choose(starter)"
        >
          <span class="nm">{{ starter.name }}</span>
          <span class="ty">{{ types.find((t) => t.value === form.type)?.label }}</span>
          <span class="de">{{ starter.description }}</span>
          <span class="bl">
            {{ $t('documentTemplates.starterScope', {
              blocks: scopeOf(starter).blocks,
              fields: scopeOf(starter).fields,
            }) }}
          </span>
        </a>

        <a class="starter blank" href="#" @click.prevent="chooseBlank">
          <span class="nm">{{ $t('documentTemplates.starterBlank') }}</span>
          <span class="de">{{ $t('documentTemplates.starterBlankHint') }}</span>
        </a>
      </div>
    </section>

    <div v-else class="doc-split">
      <section class="doc-pane">
        <div class="doc-panehead">
          <span class="kick">{{ $t('documentTemplates.body') }}</span>
          <span class="hint">{{ $t('documentTemplates.bodyHint') }}</span>
        </div>
        <div class="doc-sheet">
          <div class="doc-chrome">{{ $t('documentTemplates.letterhead') }}</div>
          <DocumentEditor v-model="doc" :palette="palette" :merge-fields="mergeFields" />
          <div class="doc-chrome foot">{{ $t('documentTemplates.footer') }}</div>
        </div>

        <!-- C-16 (#252): what this document type may not have stands dimmed
             with its reason rather than missing. Editor.js owns its own plus
             menu, so the list sits under the sheet instead of inside it —
             replacing that inserter is a rewrite of the editor shell, not
             this issue's subject. -->
        <div class="blocklegend">
          <span
            v-for="entry in palette.filter((block) => !block.inline)"
            :key="entry.type"
            :class="{ off: entry.allowed === false }"
          >
            {{ entry.label }}
            <template v-if="entry.allowed === false"> · {{ $t('documentTemplates.blockOnly', { types: entry.only }) }}</template>
          </span>
        </div>
      </section>

      <div class="doc-splitbar"></div>

      <DocumentPreviewPane :type="form.type" :doc="doc" :samples="samples" :project="project">
        <select v-model="project" class="form">
          <option :value="null">{{ $t('documentTemplates.previewSamples') }}</option>
          <option v-for="option in projects" :key="option.id" :value="option.id">{{ option.name }}</option>
        </select>
      </DocumentPreviewPane>
    </div>
  </AdminLayout>
</template>

<style scoped>
/* Cards in the mockup's own vocabulary — the same border, radius and hover
   the rest of the admin surface uses, so nothing here invents a component. */
.startergrid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: var(--s3);
  margin-top: var(--s3);
}

.startercard {
  display: flex;
  flex-direction: column;
  gap: 4px;
  text-align: left;
  padding: var(--s3);
  border: 1px solid var(--line);
  border-radius: var(--r2, 8px);
  background: var(--surface, #fff);
  cursor: pointer;
  font: inherit;
}

.startercard:hover {
  border-color: var(--accent, #b45309);
}

.startercard b {
  font: 600 14px/1.3 var(--font);
}

.startercard span {
  font: 400 13px/1.5 var(--font);
  color: var(--muted);
}
</style>
