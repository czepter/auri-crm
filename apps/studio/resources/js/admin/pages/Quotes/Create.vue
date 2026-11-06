<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import type { QuoteContact, VatSettings } from '@/admin/components/quote/quotePayload'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import FormShell from '@/ui/page/FormShell.vue'
import ProjectTypeahead from '@/admin/components/crm/ProjectTypeahead.vue'
import QuoteWorkspace from '@/admin/components/quote/QuoteWorkspace.vue'
import BusinessDataGate from '@/admin/pages/Settings/panes/BusinessDataGate.vue'
import { Button } from '@/ui/button'
import { Field } from '@/ui/field'
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{
  project: App.Crm.Data.CaseSuggestionData | null
  quotes: App.Quotes.Data.QuoteData[]
  packages: App.Catalog.Data.PackageData[]
  catalogItems: App.Catalog.Data.CatalogItemData[]
  contacts: QuoteContact[]
  canManageCatalog: boolean
  vatSettings: VatSettings
  studioSettings: App.Settings.Data.StudioSettingsData
  missingBusinessData: string[]
}>()
useLiveProps(['quotes', 'packages', 'catalogItems', 'contacts'])

// Offen, solange Impressumsdaten fehlen; „Abbrechen" führt zurück, ohne zu
// speichern, damit niemand im Formular festsitzt.
const gateOpen = ref(props.missingBusinessData.length > 0)

const project = ref(props.project)

// Die Wahl steht in der Adresse: das Anlegen am Projekt kehrt mit back()
// hierher zurück und findet den Entwurf nur so wieder.
watch(project, (chosen) => {
  router.get('/angebote/neu', chosen ? { projekt: chosen.id } : {}, {
    preserveState: true,
    replace: true,
    onSuccess: () => {
      if (chosen && !drafts.value.length) {
        create()
      }
    },
  })
})

const drafts = computed(() => props.quotes.filter((quote) => quote.status === 'draft'))

// Der Entwurf entsteht am gewählten Projekt; ab da ist es derselbe Editor
// wie im Angebotsreiter, nicht ein zweiter.
function create() {
  if (project.value) {
    router.post(`/auftraege/${project.value.id}/angebote`)
  }
}

// Kommt das Projekt schon aus der Adresse (Anfrage, Auftrag), steht der
// Entwurf ohne zweiten Klick da.
onMounted(() => {
  if (!drafts.value.length) {
    create()
  }
})
</script>

<template>
  <AdminLayout active="angebote">
    <FormShell
      :kicker="$t('create.kickerQuotes')" :title="$t('quotes.create')" :wide="!!(project && drafts.length)"
    >
      <div>
        <Field v-slot="{ id }" class="max-w-[720px]" :label="$t('create.pick')">
          <ProjectTypeahead :id="id" v-model="project" />
        </Field>

        <Button v-if="project && !drafts.length" class="mt-4 ml-0" type="button" @click="create">
          {{ $t('quotes.create') }}
        </Button>

        <div v-if="project && drafts.length" class="mt-5">
          <QuoteWorkspace
            :project-id="project.id"
            :can-manage-quotes="true"
            :can-manage-payment-plan="false"
            :quotes="drafts"
            :packages="packages"
            :catalog-items="catalogItems"
            :contacts="contacts"
            :can-manage-catalog="canManageCatalog"
            :payment-plan="null"
            :vat-settings="vatSettings"
          />
        </div>
      </div>
    </FormShell>

    <BusinessDataGate
      :open="gateOpen"
      :settings="studioSettings"
      @done="gateOpen = false"
      @cancel="router.visit('/angebote')"
    />
  </AdminLayout>
</template>
