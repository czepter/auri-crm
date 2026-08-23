<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { NativeSelect } from '@/ui/native-select'

const props = defineProps<{ presets: App.Gallery.Data.GalleryPresetData[] }>()

// Werte im Skript, Beschriftung im Template — dieselbe Aufteilung, die
// Record.vue für COVERS, COLOURS und GRIDS anwendet.
const MODES = ['inherit', 'days', 'never']

// Drei Zustände brauchen drei Optionen: eine Checkbox kann "erben" nicht
// darstellen, und genau das ist der Punkt einer Vorlage, die nur einen Teil
// der Werte setzt.
const TRISTATE = [
  { value: '', key: 'inherit' },
  { value: '1', key: 'on' },
  { value: '0', key: 'off' },
]

function toTri(value: boolean | null) {
  return value === null ? '' : value ? '1' : '0'
}

function fromTri(value: string) {
  return value === '' ? null : value === '1'
}

const forms = Object.fromEntries(
  props.presets.map((preset) => [
    preset.id,
    useForm({
      name: preset.name,
      expiry_mode: preset.expiryMode,
      expiry_days: preset.expiryDays ?? '',
      requires_settled_balance: toTri(preset.requiresSettledBalance),
      downloads_enabled: toTri(preset.downloadsEnabled),
      lists_enabled: toTri(preset.listsEnabled),
      comments_enabled: toTri(preset.commentsEnabled),
      design: preset.design,
    }),
  ]),
)

function payload(data: Record<string, unknown>) {
  return {
    ...data,
    requires_settled_balance: fromTri(data.requires_settled_balance as string),
    downloads_enabled: fromTri(data.downloads_enabled as string),
    lists_enabled: fromTri(data.lists_enabled as string),
    comments_enabled: fromTri(data.comments_enabled as string),
  }
}

function save(id: string) {
  forms[id].transform(payload).patch(`/einstellungen/galerie-vorlagen/${id}`, { preserveScroll: true })
}

function remove(id: string) {
  router.delete(`/einstellungen/galerie-vorlagen/${id}`, { preserveScroll: true })
}

const newForm = useForm({
  name: '',
  expiry_mode: 'inherit',
  expiry_days: '' as string | number,
  requires_settled_balance: '',
  downloads_enabled: '',
  lists_enabled: '',
  comments_enabled: '',
  design: null as Record<string, string> | null,
})

function create() {
  newForm.transform(payload).post('/einstellungen/galerie-vorlagen', {
    preserveScroll: true,
    onSuccess: () => newForm.reset(),
  })
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <div data-tokens>
      <PageHead :kicker="$t('settings.kicker')" :title="$t('galleryPresets.title')" />

      <div class="px-8 pt-7 pb-8">
        <div class="flex items-start gap-8">
          <SettingsNav active="galleryPresets" />

          <div class="min-w-0 max-w-[620px] flex-1">
            <div v-for="preset in presets" :key="preset.id" class="mt-8 first-of-type:mt-0">
              <div class="grid grid-cols-2 gap-x-6 gap-y-4">
                <div class="flex min-w-0 flex-col gap-1.5">
                  <Label :for="`name-${preset.id}`">{{ $t('galleryPresets.nameField') }}</Label>
                  <Input :id="`name-${preset.id}`" v-model="forms[preset.id].name" type="text" />
                </div>

                <div class="flex min-w-0 flex-col gap-1.5">
                  <Label :for="`mode-${preset.id}`">{{ $t('galleryPresets.expiryField') }}</Label>
                  <NativeSelect :id="`mode-${preset.id}`" v-model="forms[preset.id].expiry_mode">
                    <option v-for="mode in MODES" :key="mode" :value="mode">{{ $t(`galleryPresets.mode_${mode}`) }}</option>
                  </NativeSelect>
                </div>

                <div v-if="forms[preset.id].expiry_mode === 'days'" class="flex min-w-0 flex-col gap-1.5">
                  <Label :for="`days-${preset.id}`">{{ $t('gallerySettings.expiryLabel') }}</Label>
                  <Input
                    :id="`days-${preset.id}`"
                    v-model="forms[preset.id].expiry_days"
                    class="tabular-nums"
                    type="number"
                    min="1"
                  />
                </div>

                <div class="flex min-w-0 flex-col gap-1.5">
                  <Label :for="`balance-${preset.id}`">{{ $t('gallerySettings.balanceLabel') }}</Label>
                  <NativeSelect :id="`balance-${preset.id}`" v-model="forms[preset.id].requires_settled_balance">
                    <option v-for="state in TRISTATE" :key="state.value" :value="state.value">{{ $t(`galleryPresets.${state.key}`) }}</option>
                  </NativeSelect>
                </div>

                <div class="flex min-w-0 flex-col gap-1.5">
                  <Label :for="`downloads-${preset.id}`">{{ $t('gallerySettings.downloadsLabel') }}</Label>
                  <NativeSelect :id="`downloads-${preset.id}`" v-model="forms[preset.id].downloads_enabled">
                    <option v-for="state in TRISTATE" :key="state.value" :value="state.value">{{ $t(`galleryPresets.${state.key}`) }}</option>
                  </NativeSelect>
                </div>

                <div class="flex min-w-0 flex-col gap-1.5">
                  <Label :for="`lists-${preset.id}`">{{ $t('gallerySettings.listsLabel') }}</Label>
                  <NativeSelect :id="`lists-${preset.id}`" v-model="forms[preset.id].lists_enabled">
                    <option v-for="state in TRISTATE" :key="state.value" :value="state.value">{{ $t(`galleryPresets.${state.key}`) }}</option>
                  </NativeSelect>
                </div>

                <div class="flex min-w-0 flex-col gap-1.5">
                  <Label :for="`comments-${preset.id}`">{{ $t('gallerySettings.commentsLabel') }}</Label>
                  <NativeSelect :id="`comments-${preset.id}`" v-model="forms[preset.id].comments_enabled">
                    <option v-for="state in TRISTATE" :key="state.value" :value="state.value">{{ $t(`galleryPresets.${state.key}`) }}</option>
                  </NativeSelect>
                </div>
              </div>

              <div class="mt-4 flex items-center gap-4">
                <Button type="button" @click="save(preset.id)">{{ $t('common.save') }}</Button>
                <Button type="button" variant="pill-muted" @click="remove(preset.id)">{{ $t('galleryPresets.delete') }}</Button>
              </div>
            </div>

            <form class="mt-10 border-t border-border pt-8" @submit.prevent="create">
              <div class="grid grid-cols-2 gap-x-6 gap-y-4">
                <div class="flex min-w-0 flex-col gap-1.5">
                  <Label for="new-preset-name">{{ $t('galleryPresets.nameField') }}</Label>
                  <Input id="new-preset-name" v-model="newForm.name" type="text" />
                </div>

                <div class="flex min-w-0 flex-col gap-1.5">
                  <Label for="new-preset-mode">{{ $t('galleryPresets.expiryField') }}</Label>
                  <NativeSelect id="new-preset-mode" v-model="newForm.expiry_mode">
                    <option v-for="mode in MODES" :key="mode" :value="mode">{{ $t(`galleryPresets.mode_${mode}`) }}</option>
                  </NativeSelect>
                </div>

                <div v-if="newForm.expiry_mode === 'days'" class="flex min-w-0 flex-col gap-1.5">
                  <Label for="new-preset-days">{{ $t('gallerySettings.expiryLabel') }}</Label>
                  <Input id="new-preset-days" v-model="newForm.expiry_days" class="tabular-nums" type="number" min="1" />
                </div>
              </div>

              <Button class="mt-4" type="submit" :disabled="newForm.name === ''">
                {{ $t('galleryPresets.add') }}
              </Button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
