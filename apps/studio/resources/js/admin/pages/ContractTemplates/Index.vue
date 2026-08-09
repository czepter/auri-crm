<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

defineProps<{ templates: App.Contracts.Data.ContractTemplateData[] }>()
</script>

<template>
  <AdminLayout active="vertragsvorlagen">
    <div class="hrow" style="margin-bottom: var(--s5)">
      <h1>{{ $t('contractTemplates.title') }}</h1>
      <Link class="btn" href="/vertragsvorlagen/neu">
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
        {{ $t('contractTemplates.create') }}
      </Link>
    </div>

    <!-- No mockup for this screen — follows the system, same as Packages/Index. -->
    <div v-if="templates.length" class="panel">
      <div v-for="template in templates" :key="template.id" class="trow">
        <Link class="pinfo" :href="`/vertragsvorlagen/${template.id}`">
          <span class="pname">{{ template.name }}</span>
          <span class="psub">{{ $t('contractTemplates.version', { version: template.version }) }}</span>
        </Link>
      </div>
    </div>

    <p v-else class="hint">{{ $t('contractTemplates.empty') }}</p>
  </AdminLayout>
</template>
