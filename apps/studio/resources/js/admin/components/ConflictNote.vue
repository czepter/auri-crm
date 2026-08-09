<script setup lang="ts">
// Warns, never blocks. The check answers "is somebody already on this day",
// which is not the question "may this be saved" — and a blocking form teaches
// staff to type a wrong date to get past it, corrupting the very column the
// check reads.
defineProps<{ conflicts: App.Crm.Data.DateConflictData[] }>()
</script>

<template>
  <div
    v-for="conflict in conflicts"
    :key="conflict.projectId ?? conflict.label ?? undefined"
    class="flag"
  >
    {{
      conflict.source === 'closure'
        ? $t('leads.conflictClosure', { label: conflict.label })
        : $t('leads.conflict', {
            project: conflict.projectName,
            venue: conflict.venue ? `, ${conflict.venue}` : '',
          })
    }}
  </div>
</template>
