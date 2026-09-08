<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import ContactTypeahead from '@/admin/components/ContactTypeahead.vue'
import ConflictNote from '@/admin/components/ConflictNote.vue'
import { Button } from '@/ui/button'
import { DatePicker } from '@/ui/date-picker'
import { Input } from '@/ui/input'
import { ChoiceGroup } from '@/ui/choice-group'
import { Field } from '@/ui/field'
import { Textarea } from '@/ui/textarea'

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
  // Empty is now. Filled only when the call happened before this form was
  // opened — the funnel is counted on it, so today's default cannot be a
  // guess made silently.
  captured_at: '',
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
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('leads.createKicker')" :title="$t('leads.createTitle')" />

        <form class="max-w-[820px] px-8 pt-7 pb-8" @submit.prevent="submit">
          <div class="mb-5">
            <Field :label="$t('leads.contact')" :error="form.errors.contact_name">
              <ContactTypeahead v-model="contact" @create-new="startCreating" />
            </Field>
          </div>

          <div v-if="creating" class="mb-5">
            <div class="flex gap-3 [&+&]:mt-3 [&>*]:min-w-[148px] [&>*]:flex-1">
              <Field v-slot="{ id }" :label="$t('leads.name')">
                <Input
                  :id="id"
                  v-model="form.contact_name"
                  variant="filled"
                  type="text"
                  @input="proposeProjectName"
                />
              </Field>
              <Field v-slot="{ id }" :label="$t('leads.email')">
                <Input :id="id" v-model="form.contact_email" variant="filled" type="email" />
              </Field>
              <Field v-slot="{ id }" :label="$t('leads.phone')">
                <Input :id="id" v-model="form.contact_phone" variant="filled" type="tel" />
              </Field>
            </div>

            <div v-for="(person, index) in form.people" :key="index" class="flex gap-3 [&+&]:mt-3 [&>*]:min-w-[148px] [&>*]:flex-1">
              <Field v-slot="{ id }" :label="$t('leads.name')">
                <Input :id="id" v-model="person.name" variant="filled" type="text" />
              </Field>
              <Field v-slot="{ id }" :label="$t('leads.email')">
                <Input :id="id" v-model="person.email" variant="filled" type="email" />
              </Field>
              <Field v-slot="{ id }" :label="$t('leads.phone')">
                <Input :id="id" v-model="person.phone" variant="filled" type="tel" />
              </Field>
            </div>

            <button
              class="mt-2 cursor-pointer rounded-[var(--r-pill)] border-0 bg-sunk px-[11px] py-[5px] text-[12px] leading-[1.4] font-normal text-ink-2 hover:bg-sunk-2 [&_b]:font-semibold [&_b]:text-foreground"
              type="button"
              @click="addPerson"
            >
              {{ $t('leads.addPerson') }}
            </button>
          </div>

          <div class="mb-5">
            <Field v-slot="{ id }" :label="$t('leads.projectName')" :error="form.errors.project_name">
              <Input
                :id="id"
                v-model="form.project_name"
                variant="filled"
                type="text"
                @input="nameTouched = true"
              />
            </Field>
          </div>

          <div class="mb-5">
            <!-- Button rows instead of dropdowns: three to five values are hit
                 faster than a menu is opened. -->
            <ChoiceGroup
              v-model="form.type"
              :label="$t('leads.type')"
              :options="types"
              @update:model-value="proposeProjectName"
            />

            <ChoiceGroup
              v-model="form.source"
              class="mt-4"
              :label="$t('leads.source')"
              :options="sources"
            />

            <!-- CaptureChannel without web_form: what somebody types in here did
                 not arrive through the form. The controller filters it. -->
            <ChoiceGroup
              v-model="form.captured_via"
              class="mt-4"
              :label="$t('leads.capturedVia')"
              :options="channels"
            />
          </div>

          <!-- The main date is the field the whole intake hangs off
               (Shoot.is_primary, vision §3.1), so it is the one control that
               gets extra weight. Typed as TT.MM.JJJJ or picked from the
               calendar — both reach the same ISO day. -->
          <div class="mb-5">
            <Field v-slot="{ id }" :label="$t('leads.mainDate')">
              <DatePicker :id="id" v-model="form.date" size="lead" />
              <ConflictNote :conflicts="conflicts" />
            </Field>
          </div>

          <div class="mb-5">
            <Field v-slot="{ id }" :label="$t('leads.inquiryText')">
              <Textarea :id="id" v-model="form.inquiry_text" variant="filled" rows="8" />
            </Field>
          </div>

          <details class="mb-5">
            <summary
              class="w-fit cursor-pointer text-[12.5px] tracking-[-0.01em] text-ink-2 marker:text-ink-2"
            >{{ $t('leads.advanced') }}</summary>

            <div class="mt-3">
              <Field v-slot="{ id }" :label="$t('leads.capturedAt')" :error="form.errors.captured_at">
                <DatePicker :id="id" v-model="form.captured_at" />
              </Field>
            </div>
          </details>

          <div class="mt-5 flex items-center gap-3 pt-4 shadow-[inset_0_1px_0_var(--rule)] [&>button:last-child]:ml-auto">
            <a
              class="text-[12.5px] tracking-[-0.01em] whitespace-nowrap text-action no-underline hover:underline"
              href="/anfragen"
            >{{ $t('common.cancel') }}</a>
            <Button variant="pill-muted" type="submit" :disabled="form.processing" @click="again = true">
              {{ $t('leads.submitAndNext') }}
            </Button>
            <Button variant="pill" type="submit" :disabled="form.processing" @click="again = false">
              {{ $t('leads.submit') }}
            </Button>
          </div>
        </form>
      </div>
    </Page>
  </AdminLayout>
</template>
