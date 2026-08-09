<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

const props = defineProps<{
  templates: App.Documents.Data.DocumentTemplateData[]
  type: string | null
  types: { value: string; label: string }[]
}>()
</script>

<template>
  <AdminLayout active="dokumentvorlagen">
    <div class="hrow" style="margin-bottom: var(--s5)">
      <h1>{{ $t('documentTemplates.title') }}</h1>
      <Link class="btn" href="/dokumentvorlagen/neu">
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
        {{ $t('documentTemplates.create') }}
      </Link>
    </div>

    <!-- No mockup for this screen — follows the system, same as Packages/Index. -->
    <div class="hrow" style="margin-bottom: var(--s4); gap: var(--s4)">
      <Link
        class="txtbtn"
        :style="{ fontWeight: !props.type ? 700 : 400 }"
        href="/dokumentvorlagen"
      >
        {{ $t('documentTemplates.allTypes') }}
      </Link>
      <Link
        v-for="option in props.types"
        :key="option.value"
        class="txtbtn"
        :style="{ fontWeight: props.type === option.value ? 700 : 400 }"
        :href="`/dokumentvorlagen?type=${option.value}`"
      >
        {{ option.label }}
      </Link>
    </div>

    <div v-if="templates.length" class="panel">
      <div v-for="template in templates" :key="template.id" class="trow">
        <Link class="pinfo" :href="`/dokumentvorlagen/${template.id}`">
          <span class="pname">{{ template.name }}</span>
          <span class="psub">
            {{ template.typeLabel }}
            <template v-if="template.shootKindLabel"> · {{ template.shootKindLabel }}</template>
            · {{ $t('documentTemplates.version', { version: template.version }) }}
          </span>
        </Link>
      </div>
    </div>

    <p v-else class="hint">{{ $t('documentTemplates.empty') }}</p>
  </AdminLayout>
</template>
