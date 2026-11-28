<script setup lang="ts">
import { watch } from 'vue'
import FormSection from '@/ui/page/FormSection.vue'
import { debounced } from '@/admin/lib/autosave'
import { useForm } from '@inertiajs/vue3'
import { Textarea } from '@/ui/textarea'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'
import { Field } from '@/ui/field'
import { RadioGroup, RadioGroupItem } from '@/ui/radio-group'
import Chip from '@/ui/page/Chip.vue'

const props = defineProps<{ settings: App.Settings.Data.PortalSettingsData }>()
const emit = defineEmits<{ saved: [] }>()

// Die drei Zustände, in denen ein Kunde das Portal öffnet. Der Fremde mit
// einer Anfrage und der Kunde, dessen Bilder fertig sind, sind nicht
// derselbe Leser — vorher teilten sie sich einen Text.
const STATES = ['inquiry', 'active', 'done'] as const

const form = useForm({
  portal_maintenance_enabled: props.settings.maintenanceEnabled,
  portal_maintenance_message: props.settings.maintenanceMessage ?? '',
  portal_accept_quotes_enabled: props.settings.acceptQuotesEnabled,
  portal_payment_status_enabled: props.settings.paymentStatusEnabled,
  portal_offers_enabled: props.settings.offersEnabled,
  portal_messaging_mode: props.settings.messagingMode,
  portal_welcome_inquiry: props.settings.welcomeInquiry ?? '',
  portal_welcome_active: props.settings.welcomeActive ?? '',
  portal_welcome_done: props.settings.welcomeDone ?? '',
})

const CAPABILITIES = [
  { field: 'portal_accept_quotes_enabled', label: 'portalSettings.acceptQuotesEnabledLabel' },
  { field: 'portal_payment_status_enabled', label: 'portalSettings.paymentStatusEnabledLabel' },
  // Eigener Schalter und nicht an „Angebote annehmen" gehängt: ein Kunde
  // mitten in einer Reklamation soll nichts angeboten bekommen, darf aber
  // weiter annehmen, was er schon hat.
  { field: 'portal_offers_enabled', label: 'portalSettings.offersEnabledLabel' },
] as const

function submit() {
  form
    .transform((data) => ({
      ...data,
      portal_maintenance_message: data.portal_maintenance_message || null,
      portal_welcome_inquiry: data.portal_welcome_inquiry || null,
      portal_welcome_active: data.portal_welcome_active || null,
      portal_welcome_done: data.portal_welcome_done || null,
    }))
    .patch('/einstellungen/portal', { preserveScroll: true, onSuccess: () => emit('saved') })
}

// Die Begrüßungstexte speichern beim Tippen mit Denkpause; die übrigen
// Schalter bleiben beim Speichern-Knopf.
const autosave = debounced(() => form.isDirty && !form.processing && submit(), 800)

watch(() => STATES.map((state) => form[`portal_welcome_${state}`]), autosave)

defineExpose({ form, submit })
</script>

<template>
  <form id="portal-settings-form" @submit.prevent="submit">
    <FormSection :title="$t('portalSettings.welcomeSection')">
      <div class="mb-2 overflow-hidden surface-card">
        <div v-for="state in STATES" :key="state" class="px-4 py-3 not-first:border-t not-first:border-border">
          <div class="mb-2 flex flex-wrap items-center gap-2.5">
            <Chip>{{ $t(`portalSettings.welcomeState.${state}`) }}</Chip>
            <Label variant="inline" :for="`ta-welcome-${state}`">{{ $t(`portalSettings.welcomeWhen.${state}`) }}</Label>
          </div>
          <Textarea :id="`ta-welcome-${state}`" v-model="form[`portal_welcome_${state}`]" rows="2" />
        </div>
      </div>
      <span class="text-small leading-[1.45] text-faint">{{ $t('portalSettings.perContactHint') }}</span>
    </FormSection>

    <!-- T-08: a temporary closure, worded and switched separately from
         A-04's hide-project/revoke-contact forms — never a third
         catch-all access control. -->
    <FormSection :title="$t('portalSettings.maintenanceSection')">
      <div>
        <div class="flex items-center gap-2.5">
          <Checkbox id="cb-portal-maintenance-enabled" v-model="form.portal_maintenance_enabled" />
          <Label variant="inline" for="cb-portal-maintenance-enabled">{{ $t('portalSettings.maintenanceEnabledLabel') }}</Label>
        </div>
      </div>
      <div v-if="form.portal_maintenance_enabled" class="mt-4">
        <Field :error="form.errors.portal_maintenance_message" v-slot="{ id }">
          <Label :for="id">{{ $t('portalSettings.maintenanceMessageLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
          <Textarea :id v-model="form.portal_maintenance_message" rows="3" />
        </Field>
      </div>
    </FormSection>

    <!-- T-08: every switch below defaults to off — a studio opts the
         portal into each capability, whether or not the feature behind
         it already ships elsewhere. -->
    <FormSection :title="$t('portalSettings.capabilitiesSection')">
      <div class="mb-2 overflow-hidden surface-card">
        <div
          v-for="capability in CAPABILITIES"
          :key="capability.field"
          class="flex items-center gap-2.5 px-4 py-3 not-first:border-t not-first:border-border"
        >
          <Label variant="inline" :for="`cb-${capability.field}`">{{ $t(capability.label) }}</Label>
          <Checkbox :id="`cb-${capability.field}`" v-model="form[capability.field]" class="ml-auto" />
        </div>
      </div>
      <span class="text-small leading-[1.45] text-faint">{{ $t('portalSettings.perContactHint') }}</span>
    </FormSection>

    <!-- P-04 (#223) made a thread without a booking possible, which is a
         third state rather than a second position of one switch. -->
    <FormSection :title="$t('portalSettings.messagingLabel')">
      <div>
        <RadioGroup v-model="form.portal_messaging_mode">
          <div v-for="mode in ['off', 'booking', 'any']" :key="mode" class="flex items-center gap-2.5">
            <RadioGroupItem :id="`rb-messaging-${mode}`" :value="mode" />
            <Label variant="inline" :for="`rb-messaging-${mode}`">
              {{ $t(`portalSettings.messagingMode.${mode}`) }}
            </Label>
          </div>
        </RadioGroup>
      </div>
    </FormSection>
  </form>
</template>
