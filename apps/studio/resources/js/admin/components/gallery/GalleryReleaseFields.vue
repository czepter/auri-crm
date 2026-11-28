<script setup lang="ts">
import { ref, watch } from 'vue'
import { Checkbox } from '@/ui/checkbox'
import { ChoiceGroup } from '@/ui/choice-group'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Switch } from '@/ui/switch'
import { ACTIONS, AUDIENCES, offered, withPermission, type Action, type Audience, type Permissions } from '@/admin/lib/galleryPermissions'

export type ReleaseValues = {
  view_mode: 'none' | 'text' | 'logo' | 'locked'
  watermark_text: string | null
  download_rule: 'open' | 'after_payment' | 'off'
  download_sizes: ('original' | 'web')[]
  permissions: Permissions
  intro_enabled: boolean
}

export type ReleasePart = 'view' | 'download' | 'permissions' | 'intro'

export type ReleasePatch = Partial<ReleaseValues> & { download_invoice_id?: string | null }

const props = withDefaults(defineProps<{
  modelValue: ReleaseValues
  placeholder?: string | null
  /** Nur an einer Galerie mit Projekt; eine Vorlage kennt keine Rechnung. */
  invoices?: { id: string; number: string; status: string }[]
  invoiceId?: string | null
  followsPlan?: boolean
  /** Eine Vorlage hat keinen eigenen Text: das Wasserzeichen nimmt den Studionamen. */
  watermarkField?: boolean
  parts?: ReleasePart[]
}>(), { watermarkField: true, parts: () => ['view', 'download', 'permissions', 'intro'] })

const emit = defineEmits<{ change: [patch: ReleasePatch] }>()

const VIEW_MODES = ['none', 'text', 'logo', 'locked'] as const
const RULES = ['open', 'after_payment', 'off'] as const
const SIZES = ['original', 'web'] as const
const ALL_INVOICES = 'all'

const watermark = ref(props.modelValue.watermark_text ?? '')
watch(() => props.modelValue.watermark_text, (text) => (watermark.value = text ?? ''))

function saveWatermark() {
  emit('change', { watermark_text: watermark.value.trim() === '' ? null : watermark.value })
}

function setSize(size: (typeof SIZES)[number], on: boolean | 'indeterminate') {
  const next = SIZES.filter((option) => (option === size ? on === true : props.modelValue.download_sizes.includes(option)))
  if (next.length === 0) return

  emit('change', { download_sizes: [...next] })
}

function setPermission(audience: Audience, action: Action, on: boolean | 'indeterminate') {
  emit('change', { permissions: withPermission(props.modelValue.permissions, audience, action, on === true) })
}
</script>

<template>
  <div v-if="parts.includes('view')" class="mb-6 flex max-w-[440px] flex-col gap-2.5">
    <ChoiceGroup
      :model-value="modelValue.view_mode"
      :label="$t('galleryRecord.view')"
      :options="VIEW_MODES.map((mode) => ({ value: mode, label: $t(`galleryRecord.viewMode.${mode}`) }))"
      @update:model-value="(mode) => emit('change', { view_mode: mode as ReleaseValues['view_mode'] })"
    />
    <Input
      v-if="modelValue.view_mode === 'text' && watermarkField"
      v-model="watermark"
      type="text"
      maxlength="80"
      :placeholder="placeholder ?? ''"
      :aria-label="$t('galleryRecord.watermarkText')"
      @blur="saveWatermark"
    />
  </div>

  <div v-if="parts.includes('download')" class="mb-6 flex max-w-[440px] flex-col gap-3">
    <ChoiceGroup
      :model-value="modelValue.download_rule"
      :label="$t('galleryRecord.downloadsHeading')"
      :options="RULES.map((rule) => ({ value: rule, label: $t(`galleries.download.${rule}`) }))"
      @update:model-value="(rule) => emit('change', { download_rule: rule as ReleaseValues['download_rule'] })"
    />
    <div v-if="invoices && modelValue.download_rule === 'after_payment'" class="flex flex-col gap-1.5">
      <Label for="download-invoice">{{ $t('galleryRecord.invoice') }}</Label>
      <Select
        :model-value="invoiceId ?? ALL_INVOICES"
        @update:model-value="(value) => emit('change', { download_invoice_id: value === ALL_INVOICES ? null : String(value) })"
      >
        <SelectTrigger id="download-invoice"><SelectValue /></SelectTrigger>
        <SelectContent>
          <SelectItem :value="ALL_INVOICES">{{ $t(followsPlan ? 'galleryRecord.invoicePlan' : 'galleryRecord.invoiceAll') }}</SelectItem>
          <SelectItem v-for="invoice in invoices" :key="invoice.id" :value="invoice.id">
            {{ invoice.number }} · {{ $t(`galleryRecord.invoiceStatus.${invoice.status}`) }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>
    <div class="flex items-center gap-5">
      <div v-for="size in SIZES" :key="size" class="flex items-center gap-2.5">
        <Checkbox
          :id="`cb-size-${size}`"
          :model-value="modelValue.download_sizes.includes(size)"
          @update:model-value="(on) => setSize(size, on)"
        />
        <Label variant="inline" :for="`cb-size-${size}`">{{ $t(`galleryRecord.sizeOption.${size}`) }}</Label>
      </div>
    </div>
  </div>

  <table v-if="parts.includes('permissions')" class="mb-6 w-full max-w-[440px] border-collapse text-small">
    <thead>
      <tr class="text-left text-ink-2">
        <th class="py-2 font-normal" />
        <th v-for="audience in AUDIENCES" :key="audience" class="w-[88px] py-2 font-normal">{{ $t(`galleryRecord.permission.${audience}`) }}</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="action in ACTIONS" :key="action" class="border-t border-rule">
        <td class="py-2.5 font-medium">{{ $t(`galleryRecord.permission.${action}`) }}</td>
        <td v-for="audience in AUDIENCES" :key="audience" class="py-2.5">
          <Checkbox
            v-if="offered(audience, action)"
            :id="`cb-perm-${audience}-${action}`"
            :model-value="modelValue.permissions[audience][action]"
            :aria-label="`${$t(`galleryRecord.permission.${action}`)}, ${$t(`galleryRecord.permission.${audience}`)}`"
            @update:model-value="(on) => setPermission(audience, action, on)"
          />
          <span v-else class="text-faint">—</span>
        </td>
      </tr>
    </tbody>
  </table>

  <div v-if="parts.includes('intro')" class="flex items-center gap-2.5">
    <Switch id="sw-intro" :model-value="modelValue.intro_enabled" @update:model-value="(on) => emit('change', { intro_enabled: on })" />
    <Label variant="inline" for="sw-intro">{{ $t('galleryRecord.intro') }}</Label>
  </div>
</template>
