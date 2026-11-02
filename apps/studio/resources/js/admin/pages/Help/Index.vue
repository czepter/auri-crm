<script setup lang="ts">
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { Link } from '@inertiajs/vue3'
import { useLiveProps } from '@/admin/lib/liveReload'

type Eintrag = { slug: string; titel: string; beschreibung: string }

defineProps<{ aufgaben: Eintrag[]; referenz: Eintrag[] }>()
useLiveProps([])
</script>

<template>
  <AdminLayout active="hilfe">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('help.title')" />

        <div class="px-6 pt-7 pb-8">
          <section v-for="gruppe in [{ key: 'aufgaben', rows: aufgaben }, { key: 'referenz', rows: referenz }]" :key="gruppe.key" class="mb-8">
            <h2 class="mb-2 text-small text-ink-2">
              {{ $t(`help.group.${gruppe.key}`) }}
            </h2>

            <Link
              v-for="eintrag in gruppe.rows"
              :key="eintrag.slug"
              class="block border-b border-[var(--rule)] py-2.5 text-foreground no-underline last:border-0 hover:bg-sunk"
              :href="`/hilfe/${eintrag.slug}`"
            >
              <span class="text-ui font-medium">{{ eintrag.titel }}</span>
              <span class="ml-2 text-small text-ink-2">{{ eintrag.beschreibung }}</span>
            </Link>
          </section>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
