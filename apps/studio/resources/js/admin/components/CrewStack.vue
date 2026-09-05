<script setup lang="ts">
defineProps<{ crew: App.Crm.Data.CrewMemberData[] }>()
</script>

<template>
  <!-- Initials, not images: users carries no avatar column and S-02 does not
       add one, so this is the avatar rather than a fallback for a failed load.
       The 2px ring is the system's only border, and it is what keeps
       overlapping faces legible. -->
  <span v-if="crew.length" class="stack">
    <span
      v-for="member in crew"
      :key="member.id"
      class="av"
      :title="`${member.name} · ${member.roleLabel}`"
    >
      {{ member.initials }}
    </span>
  </span>
</template>
