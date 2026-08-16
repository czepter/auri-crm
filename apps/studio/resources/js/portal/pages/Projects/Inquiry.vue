<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import { Button } from '@/ui/button'
import { initials } from '@/portal/lib/initials'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
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

  <main class="letter">
    <div class="kick">{{ project.type }}</div>
    <h1>{{ project.name }}</h1>
    <p v-if="project.date || venue" class="when">
      {{ [project.date ? longDate(project.date) : $t('portal.dateOpen'), venue].filter(Boolean).join(' · ') }}
    </p>

    <div class="hair" />

    <!-- One piece of news, in a sentence. No status tiles: at this point
         there is nothing to put in them. -->
    <p class="statement">{{ $t('portal.inquiryReceived') }}</p>

    <section class="psec" style="margin-top: var(--s6)">
      <div style="display: flex; align-items: baseline; gap: var(--s4)">
        <p class="lab" style="flex: 1">{{ $t('portal.notedTitle') }}</p>
        <Button v-if="!editing" variant="ghost" type="button" @click="editing = true">
          {{ $t('common.edit') }}
        </Button>
      </div>

      <template v-if="!editing">
        <dl class="facts">
          <dt>{{ $t('portal.occasion') }}</dt>
          <dd>{{ project.type }}</dd>
          <dt>{{ $t('portal.preferredDate') }}</dt>
          <dd class="num">{{ project.date ? longDate(project.date) : $t('portal.dateOpen') }}</dd>
          <dt>{{ $t('portal.place') }}</dt>
          <dd>{{ venue || '—' }}</dd>
          <dt>{{ $t('portal.reachUs') }}</dt>
          <dd>{{ [project.contact.email, project.contact.phone].filter(Boolean).join(' · ') }}</dd>
        </dl>
        <blockquote v-if="project.inquiryText" class="quoted">{{ project.inquiryText }}</blockquote>
      </template>

      <!-- The occasion is not a field: a different occasion is a different
           booking. -->
      <form v-else @submit.prevent="save">
        <Label class="field">
          <span>{{ $t('portal.preferredDate') }}</span>
          <Input v-model="form.date" type="date" />
        </Label>
        <p v-if="form.errors.date" class="err">{{ form.errors.date }}</p>

        <Label class="field">
          <span>{{ $t('portal.venueName') }}</span>
          <Input v-model="form.venue_name" type="text" />
        </Label>

        <Label class="field">
          <span>{{ $t('portal.venueCity') }}</span>
          <Input v-model="form.venue_city" type="text" />
        </Label>

        <Label class="field">
          <span>{{ $t('portal.inquiryText') }}</span>
          <Textarea v-model="form.inquiry_text" rows="5" />
        </Label>

        <div class="formfoot">
          <Button type="submit" :disabled="form.processing">{{ $t('common.save') }}</Button>
          <Button variant="ghost" type="button" @click="editing = false">{{ $t('common.cancel') }}</Button>
        </div>
      </form>
    </section>

    <section class="psec" style="margin-top: var(--s7)">
      <p class="lab">{{ $t('portal.contactTitle') }}</p>
      <div class="person">
        <span class="av">{{ initials(studio.name) }}</span>
        <span>
          <b>{{ studio.name }}</b>
          <a v-if="studio.messagingEnabled" class="role" :href="`/nachrichten?betrifft=${project.id}`">
            {{ $t('portal.messagesLinkTitle') }}
          </a>
        </span>
      </div>
    </section>
  </main>

  <PortalFooter />
</template>
