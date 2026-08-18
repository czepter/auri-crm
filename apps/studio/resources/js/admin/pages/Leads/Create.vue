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
  // C-09 (#244): one row per person, because `contacts` holds name, mail and
  // phone in one row. Split into stacked blocks the table is cut crosswise
  // and it stops being clear whose address is whose — with a couple holding
  // two addresses that is wrongly enterable.
  people: [] as { name: string; email: string; phone: string }[],
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

function addPerson() {
  form.people.push({ name: '', email: '', phone: '' })
}

// Capture rarely happens singly, so the second button comes back here with
// an empty form instead of opening the case that was just created.
const again = ref(false)

function submit() {
  form.post('/anfragen', {
    onSuccess: () => {
      if (again.value) {
        form.reset()
        contact.value = null
        creating.value = false
        again.value = false
      }
    },
  })
}
</script>

<template>
  <AdminLayout active="anfragen">
    <div data-theme="crm" class="form" style="padding: var(--s6) var(--s7)">
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

        <div v-if="creating" class="grp">
          <div class="line">
            <label class="field grow">
              <span>{{ $t('leads.name') }}</span>
              <input v-model="form.contact_name" type="text" @input="proposeProjectName" />
            </label>
            <label class="field grow">
              <span>{{ $t('leads.email') }}</span>
              <input v-model="form.contact_email" type="email" />
            </label>
            <label class="field grow">
              <span>{{ $t('leads.phone') }}</span>
              <input v-model="form.contact_phone" type="tel" />
            </label>
          </div>

          <div v-for="(person, index) in form.people" :key="index" class="line">
            <label class="field grow">
              <span>{{ $t('leads.name') }}</span>
              <input v-model="person.name" type="text" />
            </label>
            <label class="field grow">
              <span>{{ $t('leads.email') }}</span>
              <input v-model="person.email" type="email" />
            </label>
            <label class="field grow">
              <span>{{ $t('leads.phone') }}</span>
              <input v-model="person.phone" type="tel" />
            </label>
          </div>

          <button class="ghost" type="button" style="margin-top: var(--s2)" @click="addPerson">
            {{ $t('leads.addPerson') }}
          </button>
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

        <div class="grp">
          <!-- Button rows instead of dropdowns: three to five values are hit
               faster than a menu is opened. -->
          <div class="fld">
            <Label>{{ $t('leads.type') }}</Label>
            <div class="choice">
              <label v-for="option in types" :key="option.value">
                <input v-model="form.type" type="radio" :value="option.value" @change="proposeProjectName" />
                <span>{{ option.label }}</span>
              </label>
            </div>
          </div>

          <div class="fld" style="margin-top: var(--s4)">
            <Label>{{ $t('leads.source') }}</Label>
            <div class="choice">
              <label v-for="option in sources" :key="option.value">
                <input v-model="form.source" type="radio" :value="option.value" />
                <span>{{ option.label }}</span>
              </label>
            </div>
          </div>

          <!-- CaptureChannel without web_form: what somebody types in here did
               not arrive through the form. The controller filters it. -->
          <div class="fld" style="margin-top: var(--s4)">
            <Label>{{ $t('leads.capturedVia') }}</Label>
            <div class="choice">
              <label v-for="option in channels" :key="option.value">
                <input v-model="form.captured_via" type="radio" :value="option.value" />
                <span>{{ option.label }}</span>
              </label>
            </div>
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
          <a class="link" href="/anfragen">{{ $t('common.cancel') }}</a>
          <button class="btn-2" type="submit" :disabled="form.processing" @click="again = true">
            {{ $t('leads.submitAndNext') }}
          </button>
          <button class="btn" type="submit" :disabled="form.processing" @click="again = false">
            {{ $t('leads.submit') }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
