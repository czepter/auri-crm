<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import PackageItemsEditor, { type PackageItemRow } from '@/admin/components/PackageItemsEditor.vue'
import { centsToEuros, eurosToCents } from '@/admin/lib/money'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Field } from '@/ui/field'
import { Label } from '@/ui/label'
import { ChoiceGroup } from '@/ui/choice-group'
import { Switch } from '@/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'

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
}>()

function day(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' })
}

const form = useForm({
  name: props.package.name,
  description: props.package.description ?? '',
  portal_offered: props.package.portalOffered,
  portal_text: props.package.portalText ?? '',
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
      selectable: item.selectable,
      selected: item.selected,
      outcome: item.outcome ?? '',
      hasImage: item.hasImage,
    }),
  ),
})

function toggle() {
  router.post(`/pakete/${props.package.id}/status`, {}, { preserveScroll: true })
}

// Kein preserveScroll: der Weg endet auf der Bearbeiten-Seite des Duplikats.
function duplicate() {
  router.post(`/pakete/${props.package.id}/duplizieren`)
}

function submit() {
  form
    .transform((data) => ({
      ...data,
      portal_text: data.portal_text || null,
      project_type_key: data.project_type_key || null,
      items: data.items.map((item) => ({
        title: item.title,
        description: item.description || null,
        quantity: item.quantity,
        unit_price: eurosToCents(item.unitPrice),
        selectable: item.selectable,
        selected: item.selected,
        outcome: item.outcome || null,
        // Die id, nicht der Pfad: der Controller schlaegt das Bild an der
        // eigenen Zeile nach. Siehe PackageController::imagePathsFor().
        id: item.id,
      })),
    }))
    .patch(`/pakete/${props.package.id}`)
}
</script>

<template>
  <AdminLayout active="pakete">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('packages.title')" :title="package.name">
          <Button variant="pill-muted" class="ml-auto" type="button" @click="toggle">
            {{ package.isActive ? $t('packages.deactivate') : $t('packages.activate') }}
          </Button>
          <Button variant="pill-muted" type="button" @click="duplicate">{{ $t('packages.duplicate') }}</Button>
        </PageHead>

        <form class="max-w-[640px] px-8 pt-7 pb-8" @submit.prevent="submit">
          <Field id="name" class="mb-6" :label="$t('packages.name')" :error="form.errors.name">
            <Input id="name" v-model="form.name" type="text" required />
          </Field>
          <Field id="description" class="mb-6" :label="$t('packages.description')">
            <Input id="description" v-model="form.description" type="text" />
          </Field>

          <div class="mt-8 first-of-type:mt-6">
            <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('packages.items') }}</div>
            <PackageItemsEditor v-model="form.items" :package-id="package.id" />
          </div>

          <!-- Ein Paket ist eine Angebotsvorlage. Diese Felder machen daraus
               eine Leistung, die ein Bestandskunde ansehen und anfragen kann,
               und sie stehen deshalb hier und nicht bei den Positionen: in das
               Angebot, das daraus wird, gehören sie nicht.

               Sichtbar bleiben sie auch, wenn der Schalter aus ist. Sonst
               ließe sich eine Leistung nicht einem einzelnen Kunden
               vorschlagen, ohne sie gleich allen hinzustellen. -->
          <div class="mt-8">
            <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('packages.portalSection') }}</div>

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

            <Field id="portal-text" class="mt-6" :label="$t('packages.portalText')" :error="form.errors.portal_text">
              <Input id="portal-text" v-model="form.portal_text" type="text" />
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

          <div class="mt-6 flex items-center gap-4">
            <Button type="submit" class="ml-0" :disabled="form.processing">{{ $t('common.save') }}</Button>
            <Button as="a" href="/pakete" variant="ghost">{{ $t('common.cancel') }}</Button>
          </div>
        </form>

        <!-- Steht am Paket, nicht in der Übersicht: es beantwortet eine Frage
             über dieses Paket, keine über alle. -->
        <section v-if="requests.length" class="max-w-[640px] px-8 pb-8">
          <div class="mb-2.5 flex items-baseline gap-2.5">
            <span class="text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('packages.requestsSection') }}</span>
            <span class="text-[11.5px] text-ink-2">{{ package.requestedCount }} · {{ $t('packages.bookedCount', { count: package.bookedCount }) }}</span>
          </div>
          <div
            v-for="request in requests"
            :key="request.id"
            class="flex items-baseline gap-4 py-2.5 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none"
          >
            <span class="w-[64px] flex-none text-[12px] tabular-nums text-ink-2">{{ request.requestedOn ? day(request.requestedOn) : '—' }}</span>
            <span class="min-w-0 flex-1 text-[13px]">
              <a :href="`/projekte/${request.project}`">{{ request.contact }}</a>
              <span v-if="request.date" class="block text-[12px] text-ink-2">{{ $t('packages.requestDate', { date: day(request.date) }) }}</span>
            </span>
            <span class="text-[12.5px]" :class="request.booked ? 'text-action' : 'text-ink-2'">
              {{ request.booked ? $t('packages.requestBooked') : $t('packages.requestOpen') }}
            </span>
          </div>
        </section>
      </div>
    </Page>
  </AdminLayout>
</template>
