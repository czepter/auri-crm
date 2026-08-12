<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ContactTypeahead from '@/admin/components/ContactTypeahead.vue'
import ConflictNote from '@/admin/components/ConflictNote.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Textarea } from '@/ui/textarea'
import { Label } from '@/ui/label'

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
  <AdminLayout active="anfragen">
    <div style="max-width: 680px">
      <div class="kick">{{ $t('leads.createKicker') }}</div>
      <h1>{{ $t('leads.createTitle') }}</h1>

      <form style="margin-top: var(--s5)" @submit.prevent="submit">
        <div class="grp">
          <div class="fld">
            <Label>{{ $t('leads.contact') }}</Label>
            <ContactTypeahead v-model="contact" @create-new="startCreating" />
            <p v-if="form.errors.contact_name" class="err">{{ form.errors.contact_name }}</p>
          </div>
        </div>

        <div v-if="creating" class="fgrid grp">
          <div class="fld">
            <Label for="contact_name">{{ $t('leads.name') }}</Label>
            <Input
              id="contact_name"
              v-model="form.contact_name"
              type="text"
              @input="proposeProjectName" />
          </div>
          <div class="fld">
            <Label for="partner_name">{{ $t('leads.partner') }}</Label>
            <Input id="partner_name" v-model="form.partner_name" type="text" />
          </div>
          <div class="fld">
            <Label for="contact_email">{{ $t('leads.email') }}</Label>
            <Input id="contact_email" v-model="form.contact_email" type="email" />
          </div>
          <div class="fld">
            <Label for="contact_phone">{{ $t('leads.phone') }}</Label>
            <Input id="contact_phone" v-model="form.contact_phone" type="tel" />
          </div>
          <p class="hint" style="grid-column: 1 / -1">{{ $t('leads.noEmailHint') }}</p>
        </div>

        <div class="grp">
          <div class="fld">
            <Label for="project_name">{{ $t('leads.projectName') }}</Label>
            <Input
              id="project_name"
              v-model="form.project_name"
              type="text"
              @input="nameTouched = true" />
            <p class="hint">{{ $t('leads.projectNameHint') }}</p>
            <p v-if="form.errors.project_name" class="err">{{ form.errors.project_name }}</p>
          </div>
        </div>

        <div class="fgrid grp">
          <div class="fld">
            <Label for="type">{{ $t('leads.type') }}</Label>
            <Select :model-value="form.type" @update:model-value="(value) => (form.type = String(value))">
              <SelectTrigger id="type">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="option in types" :key="option.value" :value="option.value">
                  {{ option.label }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="fld">
            <Label for="source">{{ $t('leads.source') }}</Label>
            <Select :model-value="form.source" @update:model-value="(value) => (form.source = String(value))">
              <SelectTrigger id="source">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="option in sources" :key="option.value" :value="option.value">
                  {{ option.label }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="fld">
            <Label for="captured_via">{{ $t('leads.capturedVia') }}</Label>
            <Select
              :model-value="form.captured_via"
              @update:model-value="(value) => (form.captured_via = String(value))"
            >
              <SelectTrigger id="captured_via">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="option in channels" :key="option.value" :value="option.value">
                  {{ option.label }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>

        <!-- The main date is the field the whole intake hangs off
             (Shoot.is_primary, vision §3.1), so it is the one control that
             gets extra weight. Native date input: renders dd.mm.yyyy under
             de-DE with no picker library and no parsing code. -->
        <div class="grp">
          <div class="fld lead">
            <Label for="date">{{ $t('leads.mainDate') }}</Label>
            <Input id="date" v-model="form.date" size="lead" type="date" />
            <ConflictNote :conflicts="conflicts" />
          </div>
        </div>

        <div class="grp">
          <div class="fld">
            <Label for="inquiry_text">{{ $t('leads.inquiryText') }}</Label>
            <Textarea id="inquiry_text" v-model="form.inquiry_text" rows="8" />
            <p class="hint">{{ $t('leads.inquiryTextHint') }}</p>
          </div>
        </div>

        <div class="formfoot">
          <Button type="submit" :disabled="form.processing">
            {{ $t('leads.submit') }}
          </Button>
          <Button as="a" href="/anfragen" variant="ghost">{{ $t('common.cancel') }}</Button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
