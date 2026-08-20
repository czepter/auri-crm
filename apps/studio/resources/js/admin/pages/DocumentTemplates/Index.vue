<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { Table, TableCell, TableHead, TableRow } from '@/ui/table'
import { Button } from '@/ui/button'

type Edition = { id: string; version: number; isCurrent: boolean; inUse: number }

defineProps<{
  templates: App.Documents.Data.DocumentTemplateData[]
  families: Record<string, Edition[]>
  type: string | null
  types: { value: string; label: string }[]
}>()

/* crm.css's own table, written out. The sheet styled td and th directly, and
   an element rule beats every utility — so the rules are gone and the values
   live here. */

/* crm.css .hrow und die Ueberschrift, die darin steht. */

/* crm.css .btn — die gefuellte Aktion. */

/* crm.css .filters / .pop / .link. */
</script>

<template>
  <AdminLayout active="dokumentvorlagen">
    <div data-tokens="crm">
      <PageHead :title="$t('documentTemplates.title')">
        <Button :as="Link" variant="pill" class="ml-auto" href="/dokumentvorlagen/neu">
          {{ $t('documentTemplates.create') }}
        </Button>
      </PageHead>

      <div class="flex items-center gap-2 px-8 pt-4">
        <Link class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border-0 bg-sunk-2 px-2.5 py-[5px] text-[12.5px] leading-[1.3] font-normal text-foreground [&_u]:text-ink-2 [&_u]:no-underline after:mt-[-4px] after:text-[11px] after:text-ink-2 after:content-['⌄']" href="/dokumentvorlagen">{{ $t('documentTemplates.allTypes') }}</Link>
        <Link
          v-for="option in types"
          :key="option.value"
          class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border-0 bg-sunk-2 px-2.5 py-[5px] text-[12.5px] leading-[1.3] font-normal text-foreground [&_u]:text-ink-2 [&_u]:no-underline after:mt-[-4px] after:text-[11px] after:text-ink-2 after:content-['⌄']"
          :href="`/dokumentvorlagen?type=${option.value}`"
        >
          <u v-if="type === option.value">•</u>{{ option.label }}
        </Link>
      </div>

      <Table v-if="templates.length">
        <thead>
          <tr>
            <TableHead>{{ $t('documentTemplates.colName') }}</TableHead>
            <TableHead>{{ $t('documentTemplates.colType') }}</TableHead>
            <TableHead align="right">{{ $t('documentTemplates.colVersion') }}</TableHead>
            <TableHead align="right">{{ $t('documentTemplates.colInUse') }}</TableHead>
            <TableHead align="right" />
          </tr>
        </thead>
        <tbody>
          <template v-for="template in templates" :key="template.id">
            <TableRow>
              <TableCell tone="name"><Link :href="`/dokumentvorlagen/${template.id}`">{{ template.name }}</Link></TableCell>
              <TableCell tone="muted">
                {{ template.typeLabel }}
                <template v-if="template.shootKindLabel"> · {{ template.shootKindLabel }}</template>
              </TableCell>
              <TableCell align="right">{{ template.version }}</TableCell>
              <TableCell align="right">{{ families[template.id]?.find((e) => e.isCurrent)?.inUse ?? 0 }}</TableCell>
              <TableCell align="right">
                <!-- Not "Bearbeiten": overwriting a template that is out
                     would mean a signed contract silently looking different
                     afterwards. -->
                <Link class="text-[12.5px] leading-[1.47] tracking-[-0.01em] whitespace-nowrap text-action no-underline hover:underline" :href="`/dokumentvorlagen/${template.id}`">
                  {{ $t('documentTemplates.newVersion') }}
                </Link>
              </TableCell>
            </TableRow>

            <TableRow v-for="edition in (families[template.id] ?? []).filter((e) => !e.isCurrent)" :key="edition.id">
              <TableCell tone="muted" colspan="2">{{ $t('documentTemplates.version', { version: edition.version }) }}</TableCell>
              <TableCell tone="muted" align="right">{{ edition.version }}</TableCell>
              <TableCell tone="muted" align="right">{{ edition.inUse }}</TableCell>
              <TableCell />
            </TableRow>
          </template>
        </tbody>
      </Table>

    </div>
  </AdminLayout>
</template>
