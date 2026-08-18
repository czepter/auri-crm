<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

// Warns, never blocks. The check answers "is somebody already on this day",
// which is not the question "may this be saved" — and a blocking form teaches
// staff to type a wrong date to get past it, corrupting the very column the
// check reads.
defineProps<{ conflicts: App.Crm.Data.DateConflictData[] }>()
</script>

<template>
  <component
    :is="conflict.projectId ? Link : 'div'"
    v-for="conflict in conflicts"
    :key="conflict.projectId ?? conflict.personId ?? conflict.label ?? undefined"
    class="flag"
    :href="conflict.projectId ? `/auftraege/${conflict.projectId}` : undefined"
  >
    {{
      conflict.source === 'closure'
        ? $t('leads.conflictClosure', { label: conflict.label })
        : conflict.source === 'absence'
          ? $t('leads.conflictAbsence', { label: conflict.label })
          : $t(conflict.booked ? 'leads.conflictBooked' : 'leads.conflict', {
              project: conflict.projectName,
              venue: conflict.venue ? `, ${conflict.venue}` : '',
            })
    }}
  </component>
</template>
