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
    class="mt-1.5 flex items-center rounded-[7px] bg-late-bg bg-icon-alert bg-[length:13px_13px] bg-[position:9px_center] bg-no-repeat py-[7px] pr-[9px] pl-7 text-[12.5px] leading-[1.3] font-medium text-destructive"
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
