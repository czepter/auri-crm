<script setup lang="ts">
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { Link } from '@inertiajs/vue3'
import { useLiveProps } from '@/admin/lib/liveReload'

type Eintrag = { slug: string; titel: string; beschreibung: string }

defineProps<{
  artikel: { slug: string; titel: string; art: string; html: string }
  aufgaben: Eintrag[]
  referenz: Eintrag[]
}>()
useLiveProps([])
</script>

<template>
  <AdminLayout active="hilfe">
    <Page data-tokens>
      <template #left>
        <nav class="flex w-[196px] flex-none flex-col gap-0.5 p-4">
          <Link
            v-for="eintrag in [...aufgaben, ...referenz]"
            :key="eintrag.slug"
            class="rounded-[7px] px-2.5 py-[9px] text-ui leading-none no-underline hover:bg-muted"
            :class="eintrag.slug === artikel.slug ? 'font-semibold text-foreground' : 'text-muted-foreground'"
            :href="`/hilfe/${eintrag.slug}`"
          >{{ eintrag.titel }}</Link>
        </nav>
      </template>

      <div class="min-w-0 flex-1">
        <PageHead :title="artikel.titel" />
        <div class="help-prose max-w-[68ch] px-6 pt-7 pb-8" v-html="artikel.html" />
      </div>
    </Page>
  </AdminLayout>
</template>
