<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ContactTypeahead from '@/admin/components/ContactTypeahead.vue'
import ConflictNote from '@/admin/components/ConflictNote.vue'
import { Button } from '@/ui/button'
import { DatePicker } from '@/ui/date-picker'
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

/* crm.css's .form family, written out — the sheet reached input, textarea and
   select directly, and an element rule beats every utility. */
</script>

<template>
  <AdminLayout active="anfragen">
    <div data-tokens="crm" class="px-8 py-7">
      <div class="mb-[5px] text-[11px] leading-none font-semibold tracking-[0.05em] uppercase text-ink-2">{{ $t('leads.createKicker') }}</div>
      <h1 class="m-0 mt-0.5 text-[32px] leading-[1.08] font-semibold tracking-[-0.032em] max-[640px]:text-[25px] max-[640px]:leading-[1.08]">{{ $t('leads.createTitle') }}</h1>

      <form class="max-w-[820px] [&_:is(input,textarea,select)]:w-full [&_:is(input,textarea,select)]:rounded-[10px] [&_:is(input,textarea,select)]:border-0 [&_:is(input,textarea,select)]:bg-sunk [&_:is(input,textarea,select)]:px-[11px] [&_:is(input,textarea,select)]:py-[7px] [&_:is(input,textarea,select)]:text-[13.5px] [&_:is(input,textarea,select)]:leading-[1.4] [&_:is(input,textarea,select)]:font-normal [&_:is(input,textarea,select)]:tracking-[-0.01em] [&_:is(input,textarea,select)]:text-foreground [&_:is(input,textarea,select)]:outline-0 [&_:is(input,textarea)::placeholder]:text-[#B0B0B5] [&_:is(input,textarea,select):focus]:bg-sunk-2 [&_:is(input,textarea,select):focus]:shadow-[inset_0_0_0_2px_var(--accent)] [&_textarea]:min-h-[88px] [&_textarea]:resize-y mt-5" @submit.prevent="submit">
        <div class="mb-5">
          <div class="flex min-w-0 flex-col gap-1.5">
            <Label>{{ $t('leads.contact') }}</Label>
            <ContactTypeahead v-model="contact" @create-new="startCreating" />
            <p v-if="form.errors.contact_name" class="text-[11.5px] leading-[1.45] text-destructive">{{ form.errors.contact_name }}</p>
          </div>
        </div>

        <div v-if="creating" class="mb-5">
          <div class="flex gap-3 [&+&]:mt-3 [&>div]:min-w-[148px] [&>div]:flex-1">
            <div class="flex min-w-0 flex-col gap-1.5">
              <Label for="contact_name">{{ $t('leads.name') }}</Label>
              <Input
                id="contact_name"
                v-model="form.contact_name"
                type="text"
                @input="proposeProjectName" />
            </div>
            <div class="flex min-w-0 flex-col gap-1.5">
              <Label for="contact_email">{{ $t('leads.email') }}</Label>
              <Input id="contact_email" v-model="form.contact_email" type="email" />
            </div>
            <div class="flex min-w-0 flex-col gap-1.5">
              <Label for="contact_phone">{{ $t('leads.phone') }}</Label>
              <Input id="contact_phone" v-model="form.contact_phone" type="tel" />
            </div>
          </div>

          <div v-for="(person, index) in form.people" :key="index" class="flex gap-3 [&+&]:mt-3 [&>div]:min-w-[148px] [&>div]:flex-1">
            <div class="flex min-w-0 flex-col gap-1.5">
              <Label :for="`person_name_${index}`">{{ $t('leads.name') }}</Label>
              <Input :id="`person_name_${index}`" v-model="person.name" type="text" />
            </div>
            <div class="flex min-w-0 flex-col gap-1.5">
              <Label :for="`person_email_${index}`">{{ $t('leads.email') }}</Label>
              <Input :id="`person_email_${index}`" v-model="person.email" type="email" />
            </div>
            <div class="flex min-w-0 flex-col gap-1.5">
              <Label :for="`person_phone_${index}`">{{ $t('leads.phone') }}</Label>
              <Input :id="`person_phone_${index}`" v-model="person.phone" type="tel" />
            </div>
          </div>

          <button class="cursor-pointer rounded-[var(--r-pill)] border-0 bg-sunk px-[11px] py-[5px] text-[12px] leading-[1.4] font-normal text-ink-2 hover:bg-sunk-2 [&_b]:font-semibold [&_b]:text-foreground mt-2" type="button" @click="addPerson">
            {{ $t('leads.addPerson') }}
          </button>
        </div>

        <div class="mb-5">
          <div class="flex min-w-0 flex-col gap-1.5">
            <Label for="project_name">{{ $t('leads.projectName') }}</Label>
            <Input
              id="project_name"
              v-model="form.project_name"
              type="text"
              @input="nameTouched = true" />
            <p v-if="form.errors.project_name" class="text-[11.5px] leading-[1.45] text-destructive">{{ form.errors.project_name }}</p>
          </div>
        </div>

        <div class="mb-5">
          <!-- Button rows instead of dropdowns: three to five values are hit
               faster than a menu is opened. -->
          <div class="flex min-w-0 flex-col gap-1.5">
            <Label>{{ $t('leads.type') }}</Label>
            <div class="flex flex-wrap gap-1.5 [&_label]:relative [&_input]:pointer-events-none [&_input]:absolute [&_input]:opacity-0 [&_span]:inline-block [&_span]:cursor-pointer [&_span]:rounded-[var(--r-pill)] [&_span]:bg-sunk [&_span]:px-3 [&_span]:py-1.5 [&_span]:text-[12.5px] [&_span]:text-foreground [&_input:checked+span]:bg-foreground [&_input:checked+span]:text-paper">
              <label v-for="option in types" :key="option.value">
                <input v-model="form.type" type="radio" :value="option.value" @change="proposeProjectName" />
                <span>{{ option.label }}</span>
              </label>
            </div>
          </div>

          <div class="flex min-w-0 flex-col gap-1.5 mt-4">
            <Label>{{ $t('leads.source') }}</Label>
            <div class="flex flex-wrap gap-1.5 [&_label]:relative [&_input]:pointer-events-none [&_input]:absolute [&_input]:opacity-0 [&_span]:inline-block [&_span]:cursor-pointer [&_span]:rounded-[var(--r-pill)] [&_span]:bg-sunk [&_span]:px-3 [&_span]:py-1.5 [&_span]:text-[12.5px] [&_span]:text-foreground [&_input:checked+span]:bg-foreground [&_input:checked+span]:text-paper">
              <label v-for="option in sources" :key="option.value">
                <input v-model="form.source" type="radio" :value="option.value" />
                <span>{{ option.label }}</span>
              </label>
            </div>
          </div>

          <!-- CaptureChannel without web_form: what somebody types in here did
               not arrive through the form. The controller filters it. -->
          <div class="flex min-w-0 flex-col gap-1.5 mt-4">
            <Label>{{ $t('leads.capturedVia') }}</Label>
            <div class="flex flex-wrap gap-1.5 [&_label]:relative [&_input]:pointer-events-none [&_input]:absolute [&_input]:opacity-0 [&_span]:inline-block [&_span]:cursor-pointer [&_span]:rounded-[var(--r-pill)] [&_span]:bg-sunk [&_span]:px-3 [&_span]:py-1.5 [&_span]:text-[12.5px] [&_span]:text-foreground [&_input:checked+span]:bg-foreground [&_input:checked+span]:text-paper">
              <label v-for="option in channels" :key="option.value">
                <input v-model="form.captured_via" type="radio" :value="option.value" />
                <span>{{ option.label }}</span>
              </label>
            </div>
          </div>
        </div>

        <!-- The main date is the field the whole intake hangs off
             (Shoot.is_primary, vision §3.1), so it is the one control that
             gets extra weight. Typed as TT.MM.JJJJ or picked from the
             calendar — both reach the same ISO day. -->
        <div class="mb-5">
          <div class="flex min-w-0 flex-col gap-1.5">
            <Label for="date">{{ $t('leads.mainDate') }}</Label>
            <DatePicker id="date" v-model="form.date" size="lead" />
            <ConflictNote :conflicts="conflicts" />
          </div>
        </div>

        <div class="mb-5">
          <div class="flex min-w-0 flex-col gap-1.5">
            <Label for="inquiry_text">{{ $t('leads.inquiryText') }}</Label>
            <Textarea id="inquiry_text" v-model="form.inquiry_text" rows="8" />
          </div>
        </div>

        <div class="mt-5 flex items-center gap-3 pt-4 shadow-[inset_0_1px_0_var(--rule)] [&>button:last-child]:ml-auto">
          <a class="text-[12.5px] tracking-[-0.01em] whitespace-nowrap text-action no-underline hover:underline" href="/anfragen">{{ $t('common.cancel') }}</a>
          <Button variant="pill-muted" type="submit" :disabled="form.processing" @click="again = true">
            {{ $t('leads.submitAndNext') }}
          </Button>
          <Button variant="pill" type="submit" :disabled="form.processing" @click="again = false">
            {{ $t('leads.submit') }}
          </Button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
