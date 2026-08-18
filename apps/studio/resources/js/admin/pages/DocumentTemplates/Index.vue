<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

type Edition = { id: string; version: number; isCurrent: boolean; inUse: number }

defineProps<{
  templates: App.Documents.Data.DocumentTemplateData[]
  families: Record<string, Edition[]>
  type: string | null
  types: { value: string; label: string }[]
}>()
</script>

<template>
  <AdminLayout active="dokumentvorlagen">
    <div data-theme="crm">
      <div class="hrow">
        <h1>{{ $t('documentTemplates.title') }}</h1>
        <Link class="btn" href="/dokumentvorlagen/neu" style="margin-left: auto">
          {{ $t('documentTemplates.create') }}
        </Link>
      </div>

      <div class="filters">
        <Link class="pop" href="/dokumentvorlagen">{{ $t('documentTemplates.allTypes') }}</Link>
        <Link
          v-for="option in types"
          :key="option.value"
          class="pop"
          :href="`/dokumentvorlagen?type=${option.value}`"
        >
          <u v-if="type === option.value">•</u>{{ option.label }}
        </Link>
      </div>

      <table v-if="templates.length" style="margin-top: var(--s5)">
        <thead>
          <tr>
            <th>{{ $t('documentTemplates.colName') }}</th>
            <th>{{ $t('documentTemplates.colType') }}</th>
            <th class="r">{{ $t('documentTemplates.colVersion') }}</th>
            <th class="r">{{ $t('documentTemplates.colInUse') }}</th>
            <th class="r" />
          </tr>
        </thead>
        <tbody>
          <template v-for="template in templates" :key="template.id">
            <tr>
              <td class="nm"><Link :href="`/dokumentvorlagen/${template.id}`">{{ template.name }}</Link></td>
              <td class="mu">
                {{ template.typeLabel }}
                <template v-if="template.shootKindLabel"> · {{ template.shootKindLabel }}</template>
              </td>
              <td class="r">{{ template.version }}</td>
              <td class="r">{{ families[template.id]?.find((e) => e.isCurrent)?.inUse ?? 0 }}</td>
              <td class="r">
                <!-- Not "Bearbeiten": overwriting a template that is out
                     would mean a signed contract silently looking different
                     afterwards. -->
                <Link class="link" :href="`/dokumentvorlagen/${template.id}`">
                  {{ $t('documentTemplates.newVersion') }}
                </Link>
              </td>
            </tr>

            <tr v-for="edition in (families[template.id] ?? []).filter((e) => !e.isCurrent)" :key="edition.id">
              <td class="mu" colspan="2">{{ $t('documentTemplates.version', { version: edition.version }) }}</td>
              <td class="r mu">{{ edition.version }}</td>
              <td class="r mu">{{ edition.inUse }}</td>
              <td />
            </tr>
          </template>
        </tbody>
      </table>

      <p v-else class="hint" style="padding: var(--s5) var(--s7)">{{ $t('documentTemplates.empty') }}</p>
    </div>
  </AdminLayout>
</template>
