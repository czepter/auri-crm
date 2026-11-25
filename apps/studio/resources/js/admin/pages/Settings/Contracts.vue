<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import FormSection from '@/ui/page/FormSection.vue'
import FormShell from '@/ui/page/FormShell.vue'
import SettingsNav from '@/admin/components/settings/SettingsNav.vue'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { ChoiceGroup } from '@/ui/choice-group'
import { Label } from '@/ui/label'
import { saveState } from '@/admin/lib/autosave'
import { useLiveProps } from '@/admin/lib/liveReload'
import { useI18n } from 'vue-i18n'

const props = defineProps<{ settings: App.Settings.Data.ContractSettingsData }>()
useLiveProps(['settings'])

const { t } = useI18n()

const form = useForm({
  contract_mode: props.settings.mode,
  portal_sign_contracts_enabled: props.settings.signOnline,
})

const modes = (['always', 'on_demand', 'off'] as const).map((value) => ({ value, label: t(`contractSettings.mode.${value}`) }))

function submit() {
  form.patch('/einstellungen/vertraege', { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <FormShell :kicker="$t('settings.kicker')" :title="$t('contractSettings.title')" :state="saveState([form])">
      <template #head>
        <Button v-if="form.isDirty" variant="default" type="submit" form="contract-settings-form" :disabled="form.processing">
          {{ $t('common.save') }}
        </Button>
      </template>

      <template #nav><SettingsNav active="contracts" /></template>

      <form id="contract-settings-form" class="min-w-0 max-w-[620px] flex-1" @submit.prevent="submit">
        <FormSection :title="$t('contractSettings.title')">
          <ChoiceGroup v-model="form.contract_mode" :options="modes" />
          <div v-if="form.contract_mode !== 'off'" class="mt-4 flex items-center gap-2.5">
            <Checkbox id="cb-contract-sign-online" v-model="form.portal_sign_contracts_enabled" />
            <Label variant="inline" for="cb-contract-sign-online">{{ $t('contractSettings.signOnline') }}</Label>
          </div>
        </FormSection>
      </form>
    </FormShell>
  </AdminLayout>
</template>
