<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalField from '@/portal/components/PortalField.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalPerson from '@/portal/components/PortalPerson.vue'
import { Button } from '@/ui/button'
import { DatePicker } from '@/ui/date-picker'
import { Input } from '@/ui/input'
import { Textarea } from '@/ui/textarea'

const props = defineProps<{
  contact: { name: string }
  showSwitcher: boolean
  studio: { name: string | null; messagingEnabled: boolean }
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
</script>

<template>
  <PortalHeader :contact="contact" :show-switcher="showSwitcher" />

  <main data-tokens class="mx-auto max-w-[620px]">
    <div class="mb-[5px] text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ project.type }}</div>
    <h1 class="m-0 text-[34px] leading-none font-bold tracking-[-0.04em]">{{ project.name }}</h1>
    <p v-if="project.date || venue" class="mt-3.5 text-[15px] leading-[1.5] font-normal text-muted-foreground tabular-nums">
      {{ [project.date ? longDate(project.date) : $t('portal.dateOpen'), venue].filter(Boolean).join(' · ') }}
    </p>

    <div class="my-11 h-px bg-border" />

    <!-- One piece of news, in a sentence. No status tiles: at this point
         there is nothing to put in them. -->
    <p class="m-0 text-[22px] leading-[1.45] font-normal tracking-[-0.02em]">{{ $t('portal.inquiryReceived') }}</p>

    <section class="mt-11">
      <div class="flex items-baseline gap-5">
        <p class="mb-5 text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint flex-1">{{ $t('portal.notedTitle') }}</p>
        <Button v-if="!editing" variant="ghost" type="button" @click="editing = true">
          {{ $t('common.edit') }}
        </Button>
      </div>

      <template v-if="!editing">
        <dl class="m-0 grid grid-cols-[150px_minmax(0,1fr)] gap-x-5 gap-y-3.5 text-[14px] leading-[1.55] font-normal max-[560px]:grid-cols-[minmax(0,1fr)] max-[560px]:gap-y-0.5">
          <dt class="text-muted-foreground">{{ $t('portal.occasion') }}</dt>
          <dd class="m-0 max-[560px]:mb-3.5">{{ project.type }}</dd>
          <dt class="text-muted-foreground">{{ $t('portal.preferredDate') }}</dt>
          <dd class="m-0 tabular-nums max-[560px]:mb-3.5">{{ project.date ? longDate(project.date) : $t('portal.dateOpen') }}</dd>
          <dt class="text-muted-foreground">{{ $t('portal.place') }}</dt>
          <dd class="m-0 max-[560px]:mb-3.5">{{ venue || '—' }}</dd>
          <dt class="text-muted-foreground">{{ $t('portal.reachUs') }}</dt>
          <dd class="m-0 max-[560px]:mb-3.5">{{ [project.contact.email, project.contact.phone].filter(Boolean).join(' · ') }}</dd>
        </dl>
        <blockquote v-if="project.inquiryText" class="mt-8 border-l-2 border-border pl-5 text-[14.5px] leading-[1.65] font-normal whitespace-pre-wrap text-foreground">{{ project.inquiryText }}</blockquote>
      </template>

      <!-- The occasion is not a field: a different occasion is a different
           booking. -->
      <form v-else @submit.prevent="save">
        <PortalField :label="$t('portal.preferredDate')">
          <DatePicker v-model="form.date" />
        </PortalField>
        <p v-if="form.errors.date" class="text-[11.5px] leading-[1.45] text-destructive">{{ form.errors.date }}</p>

        <PortalField :label="$t('portal.venueName')">
          <Input v-model="form.venue_name" type="text" />
        </PortalField>

        <PortalField :label="$t('portal.venueCity')">
          <Input v-model="form.venue_city" type="text" />
        </PortalField>

        <PortalField :label="$t('portal.inquiryText')">
          <Textarea v-model="form.inquiry_text" rows="5" />
        </PortalField>

        <div class="mt-8 flex items-center gap-5 [&_[data-slot=button][data-variant=default]]:ml-0">
          <Button type="submit" :disabled="form.processing">{{ $t('common.save') }}</Button>
          <Button variant="ghost" type="button" @click="editing = false">{{ $t('common.cancel') }}</Button>
        </div>
      </form>
    </section>


    <!-- P-03 (#222): the questionnaire the inquiry screen has always linked
         to and nobody could fill in. Absent until the studio shares one. -->
    <section v-if="project.questionnaires.length" class="mt-11">
      <p class="mb-5 text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('portal.questionnairesTitle') }}</p>
      <div class="border-t border-border">
        <a
          v-for="sheet in project.questionnaires"
          :key="sheet.id"
          :href="`/projekte/${project.id}/fragebogen/${sheet.id}`"
          class="flex items-center gap-5 border-b border-border py-5 text-foreground no-underline hover:bg-muted"
        >
          <span class="flex-1">
            <h3 class="m-0 text-[14px] leading-[1.3] font-semibold">{{ sheet.name }}</h3>
            <div class="mt-0.5 text-[12.5px] leading-[1.4] font-normal text-muted-foreground">
              {{ sheet.openCount
                ? $t('portal.questionsOpen', { open: sheet.openCount, total: sheet.count })
                : $t('portal.questionsDone') }}
            </div>
          </span>
        </a>
      </div>
    </section>

    <section class="mt-16">
      <p class="mb-5 text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('portal.contactTitle') }}</p>
      <PortalPerson :name="studio.name">
        <a v-if="studio.messagingEnabled" :href="`/nachrichten?betrifft=${project.id}`">
          {{ $t('portal.messagesLinkTitle') }}
        </a>
      </PortalPerson>
    </section>
  </main>

  <PortalFooter />
</template>
