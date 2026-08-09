<script setup lang="ts">
import { ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectHeader from '@/admin/components/ProjectHeader.vue'
import CrewStack from '@/admin/components/CrewStack.vue'
import { chipClass, dayAndMonth, weekday } from '@/admin/lib/shoots'

const props = defineProps<{
  header: App.Crm.Data.ProjectHeaderData
  shoots: App.Crm.Data.ShootData[]
  notes: App.Crm.Data.InternalNoteData[]
}>()

const composer = useForm({ body: '' })
const editing = ref<string | null>(null)
const draft = ref('')

function write() {
  composer.post(`/auftraege/${props.header.id}/notizen`, {
    preserveScroll: true,
    onSuccess: () => composer.reset(),
  })
}

function edit(note: App.Crm.Data.InternalNoteData) {
  editing.value = note.id
  draft.value = note.body
}

function save(id: string) {
  router.patch(`/notizen/${id}`, { body: draft.value }, {
    preserveScroll: true,
    onSuccess: () => (editing.value = null),
  })
}

function remove(id: string) {
  router.delete(`/notizen/${id}`, { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="anfragen">
    <ProjectHeader :header="header" active="overview" />

    <!-- The overview is self-sufficient: narrative left, facts right. Tabs
         hold depth only. -->
    <div class="split">
      <div class="stackcol">
        <div class="card static">
          <div class="cardhead">
            <span class="kick">{{ $t('project.shoots') }}</span>
            <span class="chip next">{{ $t('board.shootCount', header.shootCount) }}</span>
          </div>

          <div class="sess">
            <div
              v-for="shoot in shoots"
              :key="shoot.id"
              class="srow"
              :class="{ cancelled: shoot.isCancelled }"
            >
              <div class="stop">
                <div class="sdate">
                  {{ dayAndMonth(shoot.date) ?? '—' }}
                  <em>{{ weekday(shoot.date) ?? $t('board.openDate') }}</em>
                </div>
                <div class="sname">
                  {{ shoot.label ?? shoot.kind }}
                  <span v-if="shoot.isPrimary" class="prim">{{ $t('project.primary') }}</span>
                  <span>{{ [shoot.venue, shoot.callTime && `${shoot.callTime}–${shoot.endTime ?? ''}`].filter(Boolean).join(' · ') || '—' }}</span>
                </div>
                <CrewStack :crew="shoot.crew" />
                <span class="chip" :class="chipClass(shoot, header.position.value)">
                  {{ shoot.statusLabel }}
                </span>
              </div>
            </div>

            <p v-if="!shoots.length" class="hint">{{ $t('project.noShoots') }}</p>
          </div>

          <Link class="blkfoot" :href="`/auftraege/${header.id}/shootings`">
            {{ $t('project.toShoots') }}
          </Link>
        </div>

        <!-- Its own model, never a flag on a client-visible message. Nothing
             here has a portal route, and nothing here may acquire one. -->
        <div class="card static">
          <div class="cardhead">
            <span class="kick">{{ $t('project.notes') }}</span>
            <span class="chip next">{{ $t('project.notesInternalOnly') }}</span>
          </div>

          <div v-for="note in notes" :key="note.id" class="note">
            <div class="head">
              <b>{{ note.author }}</b>
              <span>{{ new Date(note.writtenAt).toLocaleDateString('de-DE') }}</span>
              <span class="acts">
                <button type="button" class="abtn" @click="edit(note)">
                  {{ $t('project.noteEdit') }}
                </button>
                <button type="button" class="abtn" @click="remove(note.id)">
                  {{ $t('project.noteDelete') }}
                </button>
              </span>
            </div>

            <textarea
              v-if="editing === note.id"
              v-model="draft"
              class="inp"
              rows="3"
              @keydown.escape="editing = null"
            />
            <p v-else>{{ note.body }}</p>

            <div v-if="editing === note.id" class="formfoot">
              <button class="btn" type="button" @click="save(note.id)">{{ $t('common.save') }}</button>
            </div>
          </div>

          <form class="composer" @submit.prevent="write">
            <textarea
              v-model="composer.body"
              class="inp"
              rows="2"
              :placeholder="$t('project.notePlaceholder')"
            />
            <p v-if="composer.errors.body" class="err">{{ composer.errors.body }}</p>
            <div class="formfoot" style="margin-top: 0">
              <button class="btn" type="submit" :disabled="composer.processing">
                {{ $t('common.save') }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- ---------------- right: facts ---------------- -->
      <div class="stackcol">
        <div class="card static">
          <div class="cardhead"><span class="kick">{{ $t('project.booking') }}</span></div>
          <div class="kv">
            <div class="kvrow">
              <span>{{ $t('project.type') }}</span><span>{{ header.type }}</span>
            </div>
            <div class="kvrow">
              <span>{{ $t('project.mainDateLabel') }}</span>
              <span :class="{ tbd: !header.primaryDate }">
                {{ header.primaryDate ? new Date(header.primaryDate).toLocaleDateString('de-DE') : $t('board.openDate') }}
              </span>
            </div>
            <div class="kvrow">
              <span>{{ $t('project.position') }}</span><span><b>{{ header.position.label }}</b></span>
            </div>
            <div class="kvrow">
              <span>{{ $t('project.bookedState') }}</span>
              <span :class="{ tbd: !header.isBooked }">
                {{ header.isBooked ? $t('project.booked') : $t('project.notBooked') }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
