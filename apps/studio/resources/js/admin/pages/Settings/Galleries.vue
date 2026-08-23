<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import { Button } from '@/ui/button'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'
import { Field } from '@/ui/field'

const props = defineProps<{ settings: App.Settings.Data.GallerySettingsData }>()

const form = useForm({
  gallery_default_expiry_days: props.settings.expiryDays ?? '',
  gallery_default_requires_settled_balance: props.settings.requiresSettledBalance,
  gallery_default_downloads_enabled: props.settings.downloadsEnabled,
  gallery_default_lists_enabled: props.settings.listsEnabled,
  gallery_default_comments_enabled: props.settings.commentsEnabled,
})

const GATES = [
  { field: 'gallery_default_requires_settled_balance', label: 'gallerySettings.balanceLabel' },
  { field: 'gallery_default_downloads_enabled', label: 'gallerySettings.downloadsLabel' },
  { field: 'gallery_default_lists_enabled', label: 'gallerySettings.listsLabel' },
  { field: 'gallery_default_comments_enabled', label: 'gallerySettings.commentsLabel' },
] as const

function submit() {
  form
    .transform((data) => ({
      ...data,
      gallery_default_expiry_days: data.gallery_default_expiry_days === '' ? null : data.gallery_default_expiry_days,
    }))
    .patch('/einstellungen/galerien', { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <div data-tokens>
      <PageHead :kicker="$t('settings.kicker')" :title="$t('gallerySettings.title')">
        <span v-if="form.isDirty" class="flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase bg-due-bg text-due">{{ $t('settings.unsaved') }}</span>
        <Button
          class="ml-auto"
          variant="pill"
          type="submit"
          form="gallery-settings-form"
          :disabled="!form.isDirty || form.processing"
        >
          {{ $t('common.save') }}
        </Button>
      </PageHead>

      <div class="px-8 pt-7 pb-8">
        <div class="flex items-start gap-8">
          <SettingsNav active="galleries" />

          <form id="gallery-settings-form" class="max-w-[460px] min-w-0 flex-1" @submit.prevent="submit">
            <div class="mb-6">
              <Field :error="form.errors.gallery_default_expiry_days" v-slot="{ id }">
                <Label :for="id">{{ $t('gallerySettings.expiryLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
                <InputGroup>
                  <InputGroupInput :id v-model="form.gallery_default_expiry_days" class="tabular-nums" type="number" min="1" />
                  <InputGroupAddon align="inline-end">{{ $t('common.unitDays') }}</InputGroupAddon>
                </InputGroup>
              </Field>
            </div>
            <div v-for="gate in GATES" :key="gate.field" class="mb-6 flex items-center gap-2.5">
              <Checkbox :id="`cb-gallery-${gate.field}`" v-model="form[gate.field]" />
              <Label variant="inline" :for="`cb-gallery-${gate.field}`">{{ $t(gate.label) }}</Label>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
