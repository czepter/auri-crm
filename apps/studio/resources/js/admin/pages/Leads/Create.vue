<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import Card from '@/ui/page/Card.vue'
import ContactTypeahead from '@/admin/components/crm/ContactTypeahead.vue'
import ConflictNote from '@/admin/components/crm/ConflictNote.vue'
import ProjectFields from '@/admin/components/crm/ProjectFields.vue'
import { Button } from '@/ui/button'
import { DatePicker } from '@/ui/date-picker'
import { TimePicker } from '@/ui/time-picker'
import { Input } from '@/ui/input'
import { ChoiceGroup } from '@/ui/choice-group'
import { Field } from '@/ui/field'
import { Textarea } from '@/ui/textarea'
import { dayWithWeekday, isoDate } from '@/admin/lib/dates'
import { useLiveProps } from '@/admin/lib/liveReload'

type Option = { value: string; label: string }

type FieldDefinition = InstanceType<typeof ProjectFields>['$props']['fields'][number]

const props = defineProps<{
  date: string | null
  types: Option[]
  sources: Option[]
  channels: Option[]
  fieldsByType: Record<string, FieldDefinition[]>
  presets: { type: string[]; source: string[]; channel: string[] }
}>()
useLiveProps([])

// ProjectType::isCatchAll() und die beiden Other-Fälle der Enums hängen alle
// an diesem einen Schlüssel; der Anzeigename daneben ist umbenennbar.
const CATCH_ALL = 'other'

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
  // Was „Sonstige" war. Project und Lead räumen den Wert beim Speichern weg,
  // sobald die Wahl daneben eine andere ist.
  type_other: '',
  source: null as string | null,
  source_other: '',
  captured_via: null as string | null,
  captured_via_other: '',
  date: props.date ?? '',
  call_time: '',
  end_time: '',
  venue_name: '',
  venue_city: '',
  inquiry_text: '',
  // Empty is now. Filled only when the call happened before this form was
  // opened — the funnel is counted on it, so today's default cannot be a
  // guess made silently.
  captured_at: '',
  // C-25: leer, bis der Typ Felder mitbringt und jemand sie ausfüllt.
  fields: {} as Record<string, string | number | boolean | string[] | null>,
})

const typeFields = computed(() => props.fieldsByType[form.type] ?? [])

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

// Ein Wert unter einem Schlüssel, den der neue Typ nicht kennt, lässt
// WriteProjectFieldValues die Erfassung ablehnen.
watch(() => form.type, () => {
  form.fields = {}
  proposeProjectName()
})

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
  { immediate: true },
)

function addPerson() {
  form.people.push({ name: '', email: '', phone: '' })
}

// Zwei gerahmte Datumsfelder nebeneinander sind der Weg, auf dem der
// Haupttermin im Eingangsdatum landet: solange niemand das Eingangsdatum
// ändert, steht es als Text da.
const backdating = ref(false)

const capturedAtLabel = computed(() => dayWithWeekday(form.captured_at || isoDate(new Date())))

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
        backdating.value = false
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
        <div class="px-6 pt-5 max-[640px]:px-5 max-[640px]:pt-4">
          <div class="text-small text-ink-2">
            <Link href="/anfragen">{{ $t('board.title') }}</Link>
          </div>
          <h1 class="m-0 mt-0.5 text-[20px] leading-7 font-semibold tracking-[-0.02em]">
            {{ $t('leads.createTitle') }}
          </h1>
        </div>

        <form class="flex max-w-[820px] flex-col gap-3 px-6 pt-5 pb-8 max-[640px]:px-5" @submit.prevent="submit">
          <Card :title="$t('leads.contact')">
            <Field :error="form.errors.contact_name">
              <ContactTypeahead v-model="contact" @create-new="startCreating" />
            </Field>

            <div v-if="creating" class="mt-3">
              <div class="flex gap-3 [&+&]:mt-3 [&>*]:min-w-[148px] [&>*]:flex-1">
                <Field v-slot="{ id }" :label="$t('leads.name')">
                  <Input
                    :id="id"
                    v-model="form.contact_name"
                    type="text"
                    @input="proposeProjectName"
                  />
                </Field>
                <Field v-slot="{ id }" :label="$t('leads.email')">
                  <Input :id="id" v-model="form.contact_email" type="email" />
                </Field>
                <Field v-slot="{ id }" :label="$t('leads.phone')">
                  <Input :id="id" v-model="form.contact_phone" type="tel" />
                </Field>
              </div>

              <div v-for="(person, index) in form.people" :key="index" class="flex gap-3 [&+&]:mt-3 [&>*]:min-w-[148px] [&>*]:flex-1">
                <Field v-slot="{ id }" :label="$t('leads.name')">
                  <Input :id="id" v-model="person.name" type="text" />
                </Field>
                <Field v-slot="{ id }" :label="$t('leads.email')">
                  <Input :id="id" v-model="person.email" type="email" />
                </Field>
                <Field v-slot="{ id }" :label="$t('leads.phone')">
                  <Input :id="id" v-model="person.phone" type="tel" />
                </Field>
              </div>

              <Button class="mt-2 self-start" variant="outline" type="button" @click="addPerson">
                {{ $t('leads.addPerson') }}
              </Button>
            </div>
          </Card>

          <Card :title="$t('project.lead')">
            <Field v-slot="{ id }" :label="$t('leads.projectName')" :error="form.errors.project_name">
              <Input
                :id="id"
                v-model="form.project_name"
                type="text"
                @input="nameTouched = true"
              />
            </Field>

            <!-- Button rows instead of dropdowns: three to five values are hit
                 faster than a menu is opened. -->
            <ChoiceGroup
              v-model="form.type"
              class="mt-4"
              :label="$t('leads.type')"
              :options="types"
              variant="segmented"
              @update:model-value="proposeProjectName"
            />

            <Field
              v-if="form.type === CATCH_ALL"
              v-slot="{ id }"
              class="mt-2 max-w-[320px]"
              :error="form.errors.type_other"
            >
              <Input :id="id" v-model="form.type_other" type="text" :aria-label="$t('leads.type')" list="type-presets" />
              <datalist id="type-presets">
                <option v-for="value in presets.type" :key="value" :value="value" />
              </datalist>
            </Field>

            <Field
              v-if="backdating"
              v-slot="{ id }"
              class="mt-4 max-w-[220px]"
              :label="$t('leads.capturedAt')"
              :error="form.errors.captured_at"
            >
              <DatePicker :id="id" v-model="form.captured_at" />
            </Field>

            <div v-else class="mt-4">
              <div class="text-small leading-none font-semibold text-faint">
                {{ $t('leads.capturedAt') }}
              </div>
              <div class="mt-1.5 flex items-center gap-2.5 text-ui leading-[1.35]">
                {{ capturedAtLabel }}
                <a
                  class="text-small tracking-[-0.01em] text-ring no-underline hover:underline"
                  href="#"
                  @click.prevent="backdating = true"
                >{{ $t('common.edit') }}</a>
              </div>
            </div>

            <!-- C-25: was dieser Typ über den Namen hinaus mitbringt. Die
                 Felder gab es im Modell längst, nur nicht bei der Erfassung. -->
            <ProjectFields
              v-if="typeFields.length"
              v-model="form.fields"
              class="mt-4"
              editing
              :errors="form.errors"
              :fields="typeFields"
            />

            <div class="mt-4 flex items-end gap-3 max-[640px]:flex-wrap">
              <Field v-slot="{ id }" class="w-[280px] flex-none" :label="$t('leads.mainDate')">
                <DatePicker :id="id" v-model="form.date" />
              </Field>
              <div class="min-w-0 flex-1">
                <ConflictNote :conflicts="conflicts" />
              </div>
            </div>

            <div class="mt-3 flex gap-3">
              <Field v-slot="{ id }" :label="$t('project.callTime')" :error="form.errors.call_time">
                <TimePicker :id="id" v-model="form.call_time" />
              </Field>
              <Field v-slot="{ id }" :label="$t('project.endTime')" :error="form.errors.end_time">
                <TimePicker :id="id" v-model="form.end_time" />
              </Field>
            </div>

            <div class="mt-3 flex gap-3 [&>*]:min-w-[148px] [&>*]:flex-1">
              <Field v-slot="{ id }" :label="$t('project.venueName')" :error="form.errors.venue_name">
                <Input :id="id" v-model="form.venue_name" type="text" />
              </Field>
              <Field v-slot="{ id }" :label="$t('project.venueCity')" :error="form.errors.venue_city">
                <Input :id="id" v-model="form.venue_city" type="text" />
              </Field>
            </div>

            <Field v-slot="{ id }" class="mt-4" :label="$t('leads.inquiryText')">
              <Textarea :id="id" v-model="form.inquiry_text" rows="8" />
            </Field>

            <!-- Statistik, keine Arbeit (#457): zuletzt und ohne Vorauswahl. -->
            <ChoiceGroup
              v-model="form.source"
              class="mt-4"
              :label="$t('leads.source')"
              :options="sources"
              variant="segmented"
            />

            <Field
              v-if="form.source === CATCH_ALL"
              v-slot="{ id }"
              class="mt-2 max-w-[320px]"
              :error="form.errors.source_other"
            >
              <Input :id="id" v-model="form.source_other" type="text" :aria-label="$t('leads.source')" list="source-presets" />
              <datalist id="source-presets">
                <option v-for="value in presets.source" :key="value" :value="value" />
              </datalist>
            </Field>

            <!-- CaptureChannel without web_form: what somebody types in here did
                 not arrive through the form. The controller filters it. -->
            <ChoiceGroup
              v-model="form.captured_via"
              class="mt-4"
              :label="$t('leads.capturedVia')"
              :options="channels"
              variant="segmented"
            />

            <Field
              v-if="form.captured_via === CATCH_ALL"
              v-slot="{ id }"
              class="mt-2 max-w-[320px]"
              :error="form.errors.captured_via_other"
            >
              <Input :id="id" v-model="form.captured_via_other" type="text" :aria-label="$t('leads.capturedVia')" list="channel-presets" />
              <datalist id="channel-presets">
                <option v-for="value in presets.channel" :key="value" :value="value" />
              </datalist>
            </Field>
          </Card>

          <div class="mt-2 flex items-center gap-3 pt-4 shadow-[inset_0_1px_0_var(--rule)] [&>button:last-child]:ml-auto">
            <a
              class="text-small tracking-[-0.01em] whitespace-nowrap text-ring no-underline hover:underline"
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
