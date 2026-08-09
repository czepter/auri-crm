<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import ContactTypeahead from '@/admin/components/ContactTypeahead.vue'
import ConflictNote from '@/admin/components/ConflictNote.vue'

type Option = { value: string; label: string }

const props = defineProps<{ types: Option[]; sources: Option[]; channels: Option[] }>()

const contact = ref<App.Crm.Data.ContactSuggestionData | null>(null)
// Only true once a search has run and the studio chose "create new" — the name
// fields do not exist before that, which is what stops a second Familie
// Osterhoff being typed into existence.
const creating = ref(false)
const conflicts = ref<App.Crm.Data.DateConflictData[]>([])

const form = useForm({
  contact_id: null as string | null,
  contact_name: '',
  contact_email: '',
  contact_phone: '',
  partner_name: '',
  project_name: '',
  type: props.types[0].value,
  source: props.sources[0].value,
  captured_via: props.channels[0].value,
  date: '',
  inquiry_text: '',
})

// The project name proposes itself from contact and type, so the "Betreff"
// costs nothing — but never overwrites a value the studio has typed.
const nameTouched = ref(false)

function proposeProjectName() {
  if (nameTouched.value) return

  const who = contact.value?.name ?? form.contact_name
  const label = props.types.find((t) => t.value === form.type)?.label ?? ''

  form.project_name = who && label ? `${who} — ${label}` : ''
}

watch(contact, (value) => {
  form.contact_id = value?.id ?? null
  if (value) creating.value = false
  proposeProjectName()
})

watch(() => form.type, proposeProjectName)

function startCreating(term: string) {
  creating.value = true
  form.contact_name = term
  proposeProjectName()
}

watch(
  () => form.date,
  async (date) => {
    if (!date) {
      conflicts.value = []
      return
    }

    const response = await fetch(`/verfuegbarkeit?datum=${date}`, {
      headers: { Accept: 'application/json' },
    })
    conflicts.value = (await response.json()).conflicts
  },
)

function submit() {
  form.post('/anfragen')
}
</script>

<template>
  <main class="mx-auto max-w-2xl space-y-6 p-6">
    <div>
      <p class="text-xs uppercase tracking-wide text-muted-foreground">
        {{ $t('leads.createKicker') }}
      </p>
      <h1 class="text-2xl font-semibold tracking-tight">{{ $t('leads.createTitle') }}</h1>
    </div>

    <form class="space-y-5" @submit.prevent="submit">
      <div class="space-y-2">
        <Label>{{ $t('leads.contact') }}</Label>
        <ContactTypeahead v-model="contact" @create-new="startCreating" />
        <p v-if="form.errors.contact_name" class="text-sm text-destructive">
          {{ form.errors.contact_name }}
        </p>
      </div>

      <div v-if="creating" class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
          <Label for="contact_name">{{ $t('leads.name') }}</Label>
          <Input id="contact_name" v-model="form.contact_name" @input="proposeProjectName" />
        </div>
        <div class="space-y-2">
          <Label for="partner_name">{{ $t('leads.partner') }}</Label>
          <Input id="partner_name" v-model="form.partner_name" />
        </div>
        <div class="space-y-2">
          <Label for="contact_email">{{ $t('leads.email') }}</Label>
          <Input id="contact_email" v-model="form.contact_email" type="email" />
        </div>
        <div class="space-y-2">
          <Label for="contact_phone">{{ $t('leads.phone') }}</Label>
          <Input id="contact_phone" v-model="form.contact_phone" type="tel" />
        </div>
        <p class="text-xs text-muted-foreground sm:col-span-2">{{ $t('leads.noEmailHint') }}</p>
      </div>

      <div class="space-y-2">
        <Label for="project_name">{{ $t('leads.projectName') }}</Label>
        <Input id="project_name" v-model="form.project_name" @input="nameTouched = true" />
        <p class="text-xs text-muted-foreground">{{ $t('leads.projectNameHint') }}</p>
        <p v-if="form.errors.project_name" class="text-sm text-destructive">
          {{ form.errors.project_name }}
        </p>
      </div>

      <div class="grid gap-4 sm:grid-cols-3">
        <div class="space-y-2">
          <Label for="type">{{ $t('leads.type') }}</Label>
          <select
            id="type"
            v-model="form.type"
            class="h-9 w-full rounded-md border bg-transparent px-3 text-sm"
          >
            <option v-for="option in types" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>
        <div class="space-y-2">
          <Label for="source">{{ $t('leads.source') }}</Label>
          <select
            id="source"
            v-model="form.source"
            class="h-9 w-full rounded-md border bg-transparent px-3 text-sm"
          >
            <option v-for="option in sources" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>
        <div class="space-y-2">
          <Label for="captured_via">{{ $t('leads.capturedVia') }}</Label>
          <select
            id="captured_via"
            v-model="form.captured_via"
            class="h-9 w-full rounded-md border bg-transparent px-3 text-sm"
          >
            <option v-for="option in channels" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>
      </div>

      <div class="space-y-2">
        <Label for="date">{{ $t('leads.mainDate') }}</Label>
        <!-- Native date input: renders dd.mm.yyyy under de-DE with no picker
             library and no parsing code. -->
        <Input id="date" v-model="form.date" type="date" />
        <ConflictNote :conflicts="conflicts" />
      </div>

      <div class="space-y-2">
        <Label for="inquiry_text">{{ $t('leads.inquiryText') }}</Label>
        <textarea
          id="inquiry_text"
          v-model="form.inquiry_text"
          rows="8"
          class="w-full rounded-md border bg-transparent p-3 text-sm"
        />
        <p class="text-xs text-muted-foreground">{{ $t('leads.inquiryTextHint') }}</p>
      </div>

      <div class="flex items-center gap-4">
        <Button type="submit" :disabled="form.processing">{{ $t('leads.submit') }}</Button>
        <a href="/anfragen" class="text-sm underline">{{ $t('common.cancel') }}</a>
      </div>
    </form>
  </main>
</template>
