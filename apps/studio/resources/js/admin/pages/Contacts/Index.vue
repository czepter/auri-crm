<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

defineProps<{ contacts: App.Crm.Data.ContactSuggestionData[] }>()

function initials(name: string): string {
  const words = name.trim().split(/\s+/).filter(Boolean)
  const picked = words.length > 1 ? [words[0], words[words.length - 1]] : words

  return picked.map((word) => word[0]).join('').toUpperCase()
}
</script>

<template>
  <AdminLayout active="kontakte">
    <div class="hrow" style="margin-bottom: var(--s5)">
      <h1>{{ $t('contacts.title') }}</h1>
    </div>

    <!-- No mockup for this screen. It follows the system: the labelled row
         grammar from the project screen's contact list, not a data table. -->
    <div v-if="contacts.length" class="panel">
      <Link v-for="contact in contacts" :key="contact.id" class="trow" :href="`/kontakte/${contact.id}`">
        <span class="pavatar">{{ initials(contact.name) }}</span>
        <span class="pinfo">
          <span class="pname">{{ contact.name }}</span>
          <span class="psub">
            {{ [contact.email, contact.phone].filter(Boolean).join(' · ') || $t('contacts.none') }}
          </span>
        </span>
        <span class="role">{{ $t('contacts.projectCount', contact.projectCount) }}</span>
      </Link>
    </div>

    <p v-else class="hint">{{ $t('contacts.empty') }}</p>
  </AdminLayout>
</template>
