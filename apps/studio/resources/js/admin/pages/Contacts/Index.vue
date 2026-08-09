<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

defineProps<{ contacts: App.Crm.Data.ContactSuggestionData[] }>()
</script>

<template>
  <main class="mx-auto max-w-4xl space-y-6 p-6">
    <h1 class="text-2xl font-semibold tracking-tight">{{ $t('contacts.title') }}</h1>

    <table v-if="contacts.length" class="w-full text-sm">
      <thead class="text-left text-xs text-muted-foreground">
        <tr>
          <th class="py-2 font-medium">{{ $t('contacts.name') }}</th>
          <th class="py-2 font-medium">{{ $t('contacts.email') }}</th>
          <th class="py-2 font-medium">{{ $t('contacts.phone') }}</th>
          <th class="py-2 text-right font-medium">{{ $t('contacts.projects') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="contact in contacts" :key="contact.id" class="border-t">
          <td class="py-2">
            <Link
              :href="`/kontakte/${contact.id}`"
              class="font-medium underline-offset-2 hover:underline"
            >
              {{ contact.name }}
            </Link>
          </td>
          <td class="py-2 text-muted-foreground">{{ contact.email ?? $t('contacts.none') }}</td>
          <td class="py-2 text-muted-foreground">{{ contact.phone ?? $t('contacts.none') }}</td>
          <td class="py-2 text-right tabular-nums">{{ contact.projectCount }}</td>
        </tr>
      </tbody>
    </table>

    <p v-else class="text-sm text-muted-foreground">{{ $t('contacts.empty') }}</p>
  </main>
</template>
