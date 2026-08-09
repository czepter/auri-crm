<script setup lang="ts">
defineProps<{ crew: App.Crm.Data.CrewMemberData[] }>()
</script>

<template>
  <!-- Initials, not images: users carries no avatar column and S-02 does not
       add one, so this is the avatar rather than a fallback for a failed load. -->
  <span v-if="crew.length" class="flex -space-x-1.5">
    <span
      v-for="member in crew"
      :key="member.id"
      class="grid size-6 place-items-center rounded-full border bg-background text-[10px] font-medium"
      :title="`${member.name} · ${member.roleLabel}`"
    >
      {{ member.initials }}
    </span>
  </span>
</template>
