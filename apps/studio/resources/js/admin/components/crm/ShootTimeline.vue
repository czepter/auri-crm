<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { deleteWithUndo, isHidden } from '@/admin/lib/deferredDelete'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { TimePicker } from '@/ui/time-picker'

defineProps<{ shoot: App.Crm.Data.ShootData; manageable: boolean }>()

const { t } = useI18n()

// Der Ablauf wächst zeilenweise: Uhrzeit und Titel reichen für den Regelfall,
// Ort/Hinweis/Fahrt stehen hinter "…ergänzen" statt bei jeder Zeile mit.
const addingTimelineFor = ref<string | null>(null)
const timelineDetails = ref(false)
const timelineForm = useForm({
  time: '',
  title: '',
  location: '',
  note: '',
  is_transit: false,
})

function startTimelineEntry(shoot: App.Crm.Data.ShootData) {
  addingTimelineFor.value = shoot.id
  timelineDetails.value = false
  timelineForm.reset()
}

function addTimelineEntry(shoot: App.Crm.Data.ShootData) {
  timelineForm.post(`/shootings/${shoot.id}/ablauf`, {
    preserveScroll: true,
    onSuccess: () => {
      timelineForm.reset()
      timelineDetails.value = false
    },
  })
}

// Eine Zeile ändern heißt: an Ort und Stelle zum Formular werden, nicht
// löschen und neu anlegen.
const editingTimelineEntry = ref<string | null>(null)
const editTimelineForm = useForm({
  time: '',
  title: '',
  location: '',
  note: '',
  is_transit: false,
})

function startEditTimelineEntry(entry: App.Crm.Data.TimelineEntryData) {
  editingTimelineEntry.value = entry.id
  editTimelineForm.time = entry.time
  editTimelineForm.title = entry.title
  editTimelineForm.location = entry.location ?? ''
  editTimelineForm.note = entry.note ?? ''
  editTimelineForm.is_transit = entry.isTransit
}

function saveTimelineEntry(entry: App.Crm.Data.TimelineEntryData) {
  editTimelineForm.patch(`/ablauf/${entry.id}`, {
    preserveScroll: true,
    onSuccess: () => (editingTimelineEntry.value = null),
  })
}

function removeTimelineEntry(entry: App.Crm.Data.TimelineEntryData) {
  deleteWithUndo(`/ablauf/${entry.id}`, { deleted: t('common.deleted'), undo: t('common.undo'), failed: t('common.deleteFailed') })
}
</script>

<template>
              <div v-if="shoot.date || shoot.timeline.length" class="mt-4 pt-3 shadow-[inset_0_1px_0_var(--rule)]">
                <div class="text-small leading-none font-semibold text-faint">{{ $t('project.timeline') }}</div>

                <!-- Der Ablauf als Zeitstrahl: eine Linie verbindet die
                     Punkte, ein Fahrt/Übergang-Eintrag steht gedimmt und
                     kursiv dazwischen statt wie ein echter Programmpunkt. -->
                <div class="mt-1.5 ml-[3px] flex flex-col border-l border-rule pl-3.5">
                  <div v-for="entry in shoot.timeline.filter((e) => !isHidden(`/ablauf/${e.id}`))" :key="entry.id" class="py-1.5">
                    <form
                      v-if="editingTimelineEntry === entry.id"
                      class="flex flex-col gap-2 rounded-md bg-sunk p-2.5"
                      @submit.prevent="saveTimelineEntry(entry)"
                    >
                      <div class="flex gap-2">
                        <Label :for="`tl-edit-time-${entry.id}`" class="sr-only">{{ $t('project.timelineTime') }}</Label>
                        <TimePicker :id="`tl-edit-time-${entry.id}`" v-model="editTimelineForm.time" class="w-[104px]" required />
                        <Label :for="`tl-edit-title-${entry.id}`" class="sr-only">{{ $t('project.timelineTitle') }}</Label>
                        <Input :id="`tl-edit-title-${entry.id}`" v-model="editTimelineForm.title" class="min-w-0 flex-1" type="text" required />
                      </div>
                      <div class="flex gap-2">
                        <Label :for="`tl-edit-location-${entry.id}`" class="sr-only">{{ $t('project.timelineLocation') }}</Label>
                        <Input :id="`tl-edit-location-${entry.id}`" v-model="editTimelineForm.location" class="min-w-0 flex-1" type="text" :placeholder="$t('project.timelineLocation')" />
                        <Label :for="`tl-edit-note-${entry.id}`" class="sr-only">{{ $t('project.timelineNote') }}</Label>
                        <Input :id="`tl-edit-note-${entry.id}`" v-model="editTimelineForm.note" class="min-w-0 flex-1" type="text" :placeholder="$t('project.timelineNote')" />
                      </div>
                      <div class="flex items-center gap-1.5">
                        <Checkbox id="tl-edit-transit" v-model="editTimelineForm.is_transit" />
                        <Label variant="inline" for="tl-edit-transit">{{ $t('project.timelineTransit') }}</Label>
                      </div>
                      <div class="flex items-center gap-3 [&_[data-slot=button][data-variant=default]]:ml-0">
                        <Button class="ml-auto" type="submit" :disabled="editTimelineForm.processing">{{ $t('common.save') }}</Button>
                        <Button type="button" variant="ghost" @click="editingTimelineEntry = null">{{ $t('common.cancel') }}</Button>
                      </div>
                    </form>

                    <div v-else class="group flex items-center gap-3 text-small leading-[1.35]" :class="entry.isTransit && 'text-ink-2 italic'">
                      <span class="w-[44px] flex-none tabular-nums">{{ entry.time }}</span>
                      <span class="min-w-0 flex-1">
                        {{ entry.title }}
                        <span v-if="entry.location" class="ml-1.5 text-ink-2">{{ entry.location }}</span>
                        <span v-if="entry.note" class="ml-1.5 text-faint">{{ entry.note }}</span>
                      </span>
                      <span v-if="manageable" class="hidden flex-none items-center gap-2.5 group-hover:flex">
                        <button type="button" class="cursor-pointer text-small text-ring" @click="startEditTimelineEntry(entry)">{{ $t('common.edit') }}</button>
                        <button type="button" class="cursor-pointer text-small text-ink-2" @click="removeTimelineEntry(entry)">{{ $t('project.timelineRemove') }}</button>
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Uhrzeit und Titel reichen für die meisten Zeilen; Ort,
                     Hinweis und Fahrt/Übergang stehen erst hinter dem Link,
                     damit ein ganzer Ablauf nicht ein Formular pro Zeile ist. -->
                <form
                  v-if="shoot.date && addingTimelineFor === shoot.id"
                  class="mt-2.5 flex flex-col gap-2 rounded-md bg-sunk p-2.5"
                  @submit.prevent="addTimelineEntry(shoot)"
                >
                  <div class="flex gap-2">
                    <Label :for="`tl-time-${shoot.id}`" class="sr-only">{{ $t('project.timelineTime') }}</Label>
                    <TimePicker :id="`tl-time-${shoot.id}`" v-model="timelineForm.time" class="w-[104px]" required />
                    <Label :for="`tl-title-${shoot.id}`" class="sr-only">{{ $t('project.timelineTitle') }}</Label>
                    <Input :id="`tl-title-${shoot.id}`" v-model="timelineForm.title" class="min-w-0 flex-1" type="text" :placeholder="$t('project.timelineTitle')" required />
                    <Button type="submit" variant="outline" size="icon" class="max-[640px]:min-h-11 max-[640px]:min-w-11" :disabled="timelineForm.processing" :aria-label="$t('common.save')">
                      <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
                    </Button>
                  </div>
                  <div v-if="timelineDetails" class="flex gap-2">
                    <Label :for="`tl-location-${shoot.id}`" class="sr-only">{{ $t('project.timelineLocation') }}</Label>
                    <Input :id="`tl-location-${shoot.id}`" v-model="timelineForm.location" class="min-w-0 flex-1" type="text" :placeholder="$t('project.timelineLocation')" />
                    <Label :for="`tl-note-${shoot.id}`" class="sr-only">{{ $t('project.timelineNote') }}</Label>
                    <Input :id="`tl-note-${shoot.id}`" v-model="timelineForm.note" class="min-w-0 flex-1" type="text" :placeholder="$t('project.timelineNote')" />
                  </div>
                  <div v-if="timelineDetails" class="flex items-center gap-1.5">
                    <Checkbox id="tl-transit" v-model="timelineForm.is_transit" />
                    <Label variant="inline" for="tl-transit">{{ $t('project.timelineTransit') }}</Label>
                  </div>
                  <button v-else type="button" class="w-fit cursor-pointer text-left text-small text-ink-2" @click="timelineDetails = true">
                    {{ $t('project.timelineMore') }}
                  </button>
                </form>

                <div class="mt-2.5 flex items-center gap-2">
                  <Button v-if="shoot.date && manageable" type="button" variant="outline" @click="addingTimelineFor === shoot.id ? (addingTimelineFor = null) : startTimelineEntry(shoot)">
                    {{ $t(addingTimelineFor === shoot.id ? 'project.timelineDone' : 'project.timelineEdit') }}
                  </Button>
                  <Button as="a" variant="outline" :href="shoot.pdfUrl" download>{{ $t('project.timelinePdf') }}</Button>
                  <Button as="a" variant="outline" :href="shoot.icsUrl" download>{{ $t('project.timelineIcs') }}</Button>
                </div>
              </div>
</template>
