<script setup lang="ts">
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import ProjectHeader from '@/admin/components/ProjectHeader.vue'
import CrewStack from '@/admin/components/CrewStack.vue'
import { Button } from '@/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/ui/card'

const props = defineProps<{
  header: App.Crm.Data.ProjectHeaderData
  shoots: App.Crm.Data.ShootData[]
  notes: App.Crm.Data.InternalNoteData[]
}>()

const composer = useForm({ body: '' })
const editing = ref<string | null>(null)
const draft = ref('')

function shortDate(date: string | null) {
  return date ? new Date(date).toLocaleDateString('de-DE') : null
}

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
  <main class="mx-auto max-w-4xl space-y-6 p-6">
    <ProjectHeader :header="header" active="overview" />

    <!-- The overview is self-sufficient; the tabs hold depth only. Shootings
         read here and are edited on their own tab. -->
    <Card>
      <CardHeader><CardTitle class="text-sm font-medium">{{ $t('project.shoots') }}</CardTitle></CardHeader>
      <CardContent class="space-y-2">
        <div
          v-for="shoot in shoots"
          :key="shoot.id"
          class="flex items-center gap-3 border-t py-2 text-sm first:border-t-0"
          :class="shoot.isCancelled && 'text-muted-foreground line-through'"
        >
          <span class="w-20 shrink-0 tabular-nums">
            {{ shortDate(shoot.date) ?? $t('board.openDate') }}
          </span>
          <span class="min-w-0 flex-1 truncate">
            {{ shoot.label ?? shoot.kind }}
            <span v-if="shoot.isPrimary" class="text-xs text-muted-foreground">
              · {{ $t('project.primary') }}
            </span>
          </span>
          <CrewStack :crew="shoot.crew" />
          <span class="shrink-0 rounded border px-1.5 py-0.5 text-xs">{{ shoot.statusLabel }}</span>
        </div>

        <p v-if="!shoots.length" class="text-sm text-muted-foreground">
          {{ $t('project.noShoots') }}
        </p>
      </CardContent>
    </Card>

    <!-- Its own model, never a flag on a client-visible message. Nothing here
         has a portal route, and nothing here may ever acquire one. -->
    <Card>
      <CardHeader class="flex-row items-center justify-between">
        <CardTitle class="text-sm font-medium">{{ $t('project.notes') }}</CardTitle>
        <span class="rounded border px-1.5 py-0.5 text-xs text-muted-foreground">
          {{ $t('project.notesInternalOnly') }}
        </span>
      </CardHeader>
      <CardContent class="space-y-4">
        <article v-for="note in notes" :key="note.id" class="space-y-1 border-t pt-3 first:border-t-0 first:pt-0">
          <div class="flex items-baseline gap-2 text-xs text-muted-foreground">
            <b class="font-medium text-foreground">{{ note.author }}</b>
            <span class="tabular-nums">{{ shortDate(note.writtenAt) }}</span>
            <button type="button" class="ml-auto underline" @click="edit(note)">
              {{ $t('project.noteEdit') }}
            </button>
            <button type="button" class="underline" @click="remove(note.id)">
              {{ $t('project.noteDelete') }}
            </button>
          </div>

          <textarea
            v-if="editing === note.id"
            v-model="draft"
            rows="3"
            class="w-full rounded-md border p-2 text-sm"
            @keydown.escape="editing = null"
          />
          <p v-else class="text-sm">{{ note.body }}</p>

          <Button v-if="editing === note.id" size="sm" @click="save(note.id)">
            {{ $t('common.save') }}
          </Button>
        </article>

        <form class="space-y-2" @submit.prevent="write">
          <textarea
            v-model="composer.body"
            rows="2"
            class="w-full rounded-md border p-2 text-sm"
            :placeholder="$t('project.notePlaceholder')"
          />
          <p v-if="composer.errors.body" class="text-xs text-destructive">
            {{ composer.errors.body }}
          </p>
          <Button type="submit" :disabled="composer.processing">{{ $t('common.save') }}</Button>
        </form>
      </CardContent>
    </Card>
  </main>
</template>
