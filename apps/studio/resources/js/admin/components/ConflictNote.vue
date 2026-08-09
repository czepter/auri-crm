<script setup lang="ts">
// Warns, never blocks. The check answers "is somebody already on this day",
// which is not the question "may this be saved" — and a blocking form teaches
// staff to type a wrong date to get past it, corrupting the very column the
// check reads.
defineProps<{ conflicts: App.Crm.Data.DateConflictData[] }>()
</script>

<template>
  <div v-if="conflicts.length" class="mt-2 space-y-1">
    <p
      v-for="conflict in conflicts"
      :key="conflict.projectId"
      class="rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm"
    >
      {{
        $t('leads.conflict', {
          project: conflict.projectName,
          venue: conflict.venue ? `, ${conflict.venue}` : '',
        })
      }}
    </p>
  </div>
</template>
