<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { EllipsisVertical } from 'lucide-vue-next'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import type { PackageItemRow } from '@/admin/lib/packageItem'
import { lineTotal } from '@/admin/lib/packageItem'
import { debounced } from '@/admin/lib/autosave'
import PackageItemsEditor from '@/admin/components/quote/PackageItemsEditor.vue'
import ShowcaseImages from '@/admin/components/quote/ShowcaseImages.vue'
import PackagePortalPreview, { type PreviewItem } from '@/admin/components/quote/PackagePortalPreview.vue'
import { PreviewOverlay } from '@/ui/preview-overlay'
import PackagePriceSummary from '@/admin/components/quote/PackagePriceSummary.vue'
import { centsToEuros, eurosToCents } from '@/lib/money'
import { useLiveProps } from '@/admin/lib/liveReload'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Field } from '@/ui/field'
import { Label } from '@/ui/label'
import { ChoiceGroup } from '@/ui/choice-group'
import { Switch } from '@/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/ui/dropdown-menu'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/ui/alert-dialog'

type Request = {
  id: string
  project: string
  contact: string
  requestedOn: string | null
  date: string | null
  booked: boolean
}

const props = defineProps<{
  package: App.Catalog.Data.PackageData
  projectTypes: { key: string; name: string }[]
  requests: Request[]
  catalogItems: App.Catalog.Data.CatalogItemData[]
  vatRate: number | null
}>()
useLiveProps(['package', 'projectTypes', 'requests', 'catalogItems', 'vatRate'])

function day(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' })
}

const form = useForm({
  name: props.package.name,
  description: props.package.description ?? '',
  portal_offered: props.package.portalOffered,
  portal_price_mode: props.package.portalPriceMode,
  portal_requires_date: props.package.portalRequiresDate,
  project_type_key: props.package.projectTypeKey ?? '',
  items: props.package.items.map(
    (item): PackageItemRow => ({
      id: item.id,
      title: item.title,
      description: item.description ?? '',
      quantity: item.quantity,
      unitPrice: centsToEuros(item.unitPrice),
      vatRate: item.vatRate,
      selectable: item.selectable,
      selected: item.selected,
      hasImage: item.hasImage,
    }),
  ),
})

// Wie App\Catalog\Data\PortalServiceData::fromModel() rechnet — dieselbe
// Summe, nur aus dem Entwurf statt aus der gespeicherten Zeile, damit die
// Vorschau vor dem Speichern schon stimmt. Eine wählbare, noch nicht
// vorausgewählte Position zählt nicht mit — sie steht in der Vorschau als
// eigener Zusatzpreis, nicht im Gesamtbetrag.
const previewPrice = computed(() =>
  form.portal_price_mode === 'on_request'
    ? null
    : form.items.filter((item) => !item.selectable || item.selected).reduce((sum, item) => sum + lineTotal(item), 0),
)

const previewItems = computed<PreviewItem[]>(() =>
  form.items.map((item) => ({
    title: item.title,
    description: item.description,
    selectable: item.selectable,
    selected: item.selected,
    addOnPrice: item.selectable && !item.selected ? lineTotal(item) : null,
  })),
)

function toggle() {
  router.post(`/pakete/${props.package.id}/status`, {}, { preserveScroll: true })
}

// Kein preserveScroll: der Weg endet auf der Bearbeiten-Seite des Duplikats.
function duplicate() {
  router.post(`/pakete/${props.package.id}/duplizieren`)
}

const deleting = ref(false)

function destroy() {
  router.delete(`/pakete/${props.package.id}`)
}

// Speichert sofort — der Header-Button. Feldänderungen selbst laufen über
// `autosave` weiter unten, gebündelt statt bei jedem Tastenanschlag.
function save() {
  form
    .transform((data) => ({
      ...data,
      project_type_key: data.project_type_key || null,
      items: data.items.map((item) => ({
        title: item.title,
        description: item.description || null,
        quantity: item.quantity,
        unit_price: eurosToCents(item.unitPrice),
        vat_rate: item.vatRate,
        selectable: item.selectable,
        selected: item.selected,
        // Die id, nicht der Pfad: der Controller schlaegt das Bild an der
        // eigenen Zeile nach. Siehe PackageController::imagePathsFor().
        id: item.id,
      })),
    }))
    .patch(`/pakete/${props.package.id}`, { preserveScroll: true, preserveState: true, only: ['package'] })
}

// Wie QuoteWorkspace: gebündelt statt ein Request je Tastaturwechsel.
const autosave = debounced(save, 600)

// form.data() statt form selbst: ein deep watch auf form träfe auch
// processing/errors/recentlySuccessful und würde nach jedem Speichern durch
// deren eigene Änderung noch einmal speichern.
watch(() => form.data(), autosave, { deep: true })
</script>

<template>
  <AdminLayout active="pakete">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('packages.title')" kicker-href="/pakete" :title="package.name">
          <div class="ml-auto flex items-center gap-2">
            <Button as="a" variant="pill-muted" href="/pakete">{{ $t('common.cancel') }}</Button>

            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="ghost" type="button" class="rounded-[6px] px-2 hover:bg-sunk-2" :aria-label="$t('project.more')">
                  <EllipsisVertical />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem @select="duplicate">{{ $t('packages.duplicate') }}</DropdownMenuItem>
                <DropdownMenuItem @select="toggle">
                  {{ package.isActive ? $t('packages.deactivate') : $t('packages.activate') }}
                </DropdownMenuItem>
                <DropdownMenuItem class="text-destructive" @select="deleting = true">
                  {{ $t('packages.deleteMenu') }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </PageHead>

        <AlertDialog v-model:open="deleting">
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>{{ $t('packages.deleteTitle', { name: package.name }) }}</AlertDialogTitle>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogAction tone="destructive" @click="destroy">{{ $t('common.delete') }}</AlertDialogAction>
              <AlertDialogCancel>{{ $t('common.cancel') }}</AlertDialogCancel>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>

        <form class="flex items-start gap-8 px-6 pt-7 pb-8" @submit.prevent="save">
          <div class="max-w-[640px] min-w-0 flex-1">
          <Field id="name" class="mb-6" :label="$t('packages.name')" :error="form.errors.name">
            <Input id="name" v-model="form.name" type="text" required />
          </Field>
          <Field id="description" class="mb-6" :label="$t('packages.description')">
            <Input id="description" v-model="form.description" type="text" />
          </Field>

          <div class="mt-8 first-of-type:mt-6">
            <div class="mb-2.5 text-small leading-none font-semibold text-faint">{{ $t('packages.items') }}</div>
            <PackageItemsEditor v-model="form.items" :package-id="package.id" :catalog-items="catalogItems" />
          </div>

          <div class="mt-8">
            <div class="mb-2.5 text-small leading-none font-semibold text-faint">{{ $t('packages.showcase') }}</div>
            <ShowcaseImages :base="`/pakete/${package.id}/schaufenster`" :count="package.showcaseCount" />
            <span class="text-small leading-[1.45] font-normal text-faint">{{ $t('packages.showcaseHint') }}</span>
          </div>

          <!-- Ein Paket ist eine Angebotsvorlage. Diese Felder machen daraus
               eine Leistung, die ein Bestandskunde ansehen und anfragen kann,
               und sie stehen deshalb hier und nicht bei den Positionen: in das
               Angebot, das daraus wird, gehören sie nicht.

               Sichtbar bleiben sie auch, wenn der Schalter aus ist. Sonst
               ließe sich eine Leistung nicht einem einzelnen Kunden
               vorschlagen, ohne sie gleich allen hinzustellen. -->
          <div class="mt-8">
            <div class="mb-2.5 text-small leading-none font-semibold text-faint">{{ $t('packages.portalSection') }}</div>

            <div class="flex items-center gap-2.5">
              <Switch id="portal-offered" v-model="form.portal_offered" />
              <Label for="portal-offered">{{ $t('packages.portalOffered') }}</Label>
            </div>

            <Field id="project-type" class="mt-6" :label="$t('packages.projectType')" :error="form.errors.project_type_key">
              <Select
                :model-value="form.project_type_key || undefined"
                @update:model-value="(v) => (form.project_type_key = String(v))"
              >
                <SelectTrigger id="project-type"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="type in projectTypes" :key="type.key" :value="type.key">{{ type.name }}</SelectItem>
                </SelectContent>
              </Select>
            </Field>

            <div class="mt-6">
              <Label class="mb-2 block">{{ $t('packages.portalPriceMode') }}</Label>
              <ChoiceGroup
                v-model="form.portal_price_mode"
                :options="[
                  { value: 'exact', label: $t('packages.priceExact') },
                  { value: 'from', label: $t('packages.priceFrom') },
                  { value: 'on_request', label: $t('packages.priceOnRequest') },
                ]"
              />
            </div>

            <div class="mt-6">
              <Label class="mb-2 block">{{ $t('packages.portalRequiresDate') }}</Label>
              <ChoiceGroup
                :model-value="form.portal_requires_date ? 'date' : 'none'"
                :options="[
                  { value: 'date', label: $t('packages.portalWithDate') },
                  { value: 'none', label: $t('packages.portalWithoutDate') },
                ]"
                @update:model-value="(v) => (form.portal_requires_date = v === 'date')"
              />
            </div>
          </div>
          </div>

          <div class="sticky top-7 min-w-[380px] flex-1">
            <PackagePriceSummary :items="form.items" />

            <PreviewOverlay :min="1280" :label="$t('common.preview')" wrapper-class="mt-6">
              <div class="overflow-hidden rounded-[var(--r)] bg-paper shadow-card">
                <div class="border-b border-border px-[26px] py-4 text-small leading-none font-semibold text-faint">
                  {{ $t('packages.preview') }}
                </div>
                <PackagePortalPreview
                  :name="form.name"
                  :description="form.description || null"
                  :price="previewPrice"
                  :price-mode="form.portal_price_mode"
                  :requires-date="form.portal_requires_date"
                  :items="previewItems"
                  :vat-rate="vatRate"
                />
              </div>
            </PreviewOverlay>
          </div>
        </form>

        <!-- Steht am Paket, nicht in der Übersicht: es beantwortet eine Frage
             über dieses Paket, keine über alle. -->
        <section v-if="requests.length" class="max-w-[640px] px-6 pb-8">
          <div class="mb-2.5 flex items-baseline gap-2.5">
            <span class="text-small leading-none font-semibold text-faint">{{ $t('packages.requestsSection') }}</span>
            <span class="text-small text-ink-2">{{ package.requestedCount }} · {{ $t('packages.bookedCount', { count: package.bookedCount }) }}</span>
          </div>
          <div
            v-for="request in requests"
            :key="request.id"
            class="flex items-baseline gap-4 py-2.5 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none"
          >
            <span class="w-[64px] flex-none text-small tabular-nums text-ink-2">{{ request.requestedOn ? day(request.requestedOn) : '—' }}</span>
            <span class="min-w-0 flex-1 text-small">
              <a :href="`/projekte/${request.project}`">{{ request.contact }}</a>
              <span v-if="request.date" class="block text-small text-ink-2">{{ $t('packages.requestDate', { date: day(request.date) }) }}</span>
            </span>
            <span class="text-small" :class="request.booked ? 'text-ok' : 'text-ink-2'">
              {{ request.booked ? $t('packages.requestBooked') : $t('packages.requestOpen') }}
            </span>
          </div>
        </section>
      </div>
    </Page>
  </AdminLayout>
</template>
