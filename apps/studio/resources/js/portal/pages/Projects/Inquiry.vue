<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import PortalField from '@/portal/components/PortalField.vue'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalLabel from '@/portal/components/PortalLabel.vue'
import PortalPerson from '@/portal/components/PortalPerson.vue'
import PortalRow from '@/portal/components/PortalRow.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import { reachable, type Studio } from '@/portal/lib/studio'
import { Button } from '@/ui/button'
import { DatePicker } from '@/ui/date-picker'
import { Input } from '@/ui/input'
import { Textarea } from '@/ui/textarea'

const props = defineProps<{
  contact: { name: string }
  showSwitcher: boolean
  studio: Studio
  project: {
    id: string
    name: string
    type: string
    date: string | null
    venueName: string | null
    venueCity: string | null
    inquiryText: string | null
    questionnaires: { id: string; name: string; count: number; openCount: number }[]
    contact: { email: string | null; phone: string | null }
  }
}>()

// P-03: editability follows the phase, and this whole screen only renders
// while the booking is still an inquiry — so the form is a mode, not a
// permission check of its own.
const editing = ref(false)

const form = useForm({
  date: props.project.date ?? '',
  venue_name: props.project.venueName ?? '',
  venue_city: props.project.venueCity ?? '',
  inquiry_text: props.project.inquiryText ?? '',
})

function save() {
  form.patch(`/projekte/${props.project.id}/angaben`, {
    preserveScroll: true,
    onSuccess: () => (editing.value = false),
  })
}

function longDate(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
  })
}

const venue = [props.project.venueName, props.project.venueCity].filter(Boolean).join(', ')


const messageHref = computed(() => `/nachrichten?betrifft=${props.project.id}`)
</script>

<template>
  <PortalShell>
    <PortalHeader :switcher="{ label: project.name, toAll: showSwitcher }" />

    <!-- The first day in the portal. There are two names, a wished-for date and
         a kind; there are no pictures, appointments, quote or
         payments. So this is a letter, not a dashboard with five empty boxes —
         a narrower measure than any other screen here, on purpose. -->
    <main class="mx-auto w-full max-w-[668px] grow px-6 pt-40 pb-28 max-[700px]:pt-[var(--band)]">
      <p class="animate-rise motion-reduce:animate-none mb-6 text-[11px] leading-none font-semibold tracking-[0.17em] uppercase text-muted-foreground">{{ project.type }}</p>
      <h1 class="animate-rise [animation-delay:140ms] motion-reduce:animate-none font-serif text-[clamp(42px,6vw,62px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
        {{ project.name }}
      </h1>
      <p class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-6 flex flex-wrap items-center gap-2.5 text-[15px] tabular-nums text-muted-foreground">
        {{ [project.date ? longDate(project.date) : $t('portal.dateOpen'), venue].filter(Boolean).join(' · ') }}
        <span class="inline-flex items-center rounded-[var(--r-pill)] bg-[var(--accent-bg)] px-[11px] py-[5px] text-[11.5px] leading-none font-semibold tracking-[0.02em] text-action">
          {{ $t('portal.notReserved') }}
        </span>
      </p>

      <div class="animate-rise [animation-delay:320ms] motion-reduce:animate-none">
        <div class="my-18 h-px bg-border" />

        <!-- The only piece of news there is at this point, in a sentence. The
             same place the countdown stands once it is booked. -->
        <section>
          <h2 class="font-serif text-[clamp(24px,3vw,32px)] leading-[1.15] font-normal tracking-[-0.015em]">
            {{ $t('portal.inquiryReceivedTitle') }}
          </h2>
          <p class="mt-4 max-w-[48ch] text-[17px] leading-[1.55] text-muted-foreground">{{ $t('portal.inquiryReceived') }}</p>
        </section>

        <section class="mt-18">
          <div class="flex items-baseline gap-4">
            <PortalLabel>{{ $t('portal.notedTitle') }}</PortalLabel>
            <Button
              v-if="!editing"
              class="ml-auto py-0 underline decoration-line-hover underline-offset-[3px] hover:decoration-current"
              type="button"
              variant="ghost"
              @click="editing = true"
            >
              {{ $t('common.edit') }}
            </Button>
          </div>

          <template v-if="!editing">
            <dl class="m-0 mt-4 grid grid-cols-[132px_1fr] gap-x-6 gap-y-3 max-[700px]:grid-cols-1 max-[700px]:gap-y-0">
              <dt class="text-[13px] text-muted-foreground max-[700px]:mt-3">{{ $t('portal.occasion') }}</dt>
              <dd class="m-0 text-[14.5px]">{{ project.type }}</dd>
              <dt class="text-[13px] text-muted-foreground max-[700px]:mt-3">{{ $t('portal.preferredDate') }}</dt>
              <dd class="m-0 text-[14.5px] tabular-nums">{{ project.date ? longDate(project.date) : $t('portal.dateOpen') }}</dd>
              <dt class="text-[13px] text-muted-foreground max-[700px]:mt-3">{{ $t('portal.place') }}</dt>
              <dd class="m-0 text-[14.5px]">{{ venue || '—' }}</dd>
              <dt class="text-[13px] text-muted-foreground max-[700px]:mt-3">{{ $t('portal.reachUs') }}</dt>
              <dd class="m-0 text-[14.5px]">{{ [project.contact.email, project.contact.phone].filter(Boolean).join(' · ') }}</dd>
            </dl>
            <blockquote v-if="project.inquiryText" class="mt-6 border-l-2 border-line-hover pl-4 text-[16px] leading-[1.6] whitespace-pre-wrap text-muted-foreground">{{ project.inquiryText }}</blockquote>
          </template>

          <!-- Editing is the same block, not a form somewhere else: the rows
               stay where they were and only the value becomes a field. The
               occasion is not one — a different occasion is a different
               booking. -->
          <form v-else class="mt-4 grid gap-4" @submit.prevent="save">
            <PortalField :label="$t('portal.preferredDate')">
              <DatePicker v-model="form.date" />
              <span v-if="form.errors.date" class="mt-[7px] block text-[12.5px] leading-[1.45] text-destructive">{{ form.errors.date }}</span>
            </PortalField>

            <PortalField :label="$t('portal.venueName')">
              <Input v-model="form.venue_name" type="text" />
            </PortalField>

            <PortalField :label="$t('portal.venueCity')">
              <Input v-model="form.venue_city" type="text" />
            </PortalField>

            <PortalField :label="$t('portal.inquiryText')">
              <Textarea v-model="form.inquiry_text" rows="5" />
            </PortalField>

            <div class="mt-2 flex flex-wrap items-center gap-4">
              <Button variant="portal" type="submit" :disabled="form.processing">{{ $t('common.save') }}</Button>
              <Button
                class="py-0 text-[14px]"
                type="button"
                variant="ghost"
                @click="editing = false"
              >
                {{ $t('common.cancel') }}
              </Button>
            </div>
          </form>
        </section>

        <section v-if="reachable(studio)" class="mt-18">
          <PortalLabel class="mb-4">{{ $t('portal.contactTitle') }}</PortalLabel>
          <PortalPerson :studio="studio" :message-href="messageHref" />
        </section>

        <!-- P-03 (#222): the questionnaire the inquiry screen has always linked
             to and nobody could fill in. Absent until the studio shares one.
             Drawn as the row the booked project's paperwork uses, so nothing
             has to be relearned later. -->
        <section v-if="project.questionnaires.length" class="mt-18 border-t border-border">
          <PortalRow
            v-for="sheet in project.questionnaires"
            :key="sheet.id"
            :href="`/projekte/${project.id}/fragebogen/${sheet.id}`"
            :title="sheet.name"
            :state="sheet.openCount ? 'open' : 'done'"
            :note="sheet.openCount
              ? $t('portal.questionsOpen', { open: sheet.openCount, total: sheet.count })
              : $t('portal.questionsDone')"
          />
        </section>
      </div>
    </main>

    <PortalFooter />
  </PortalShell>
</template>
