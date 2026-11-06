<script setup lang="ts">
import { ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import FormShell from '@/ui/page/FormShell.vue'
import CreateSummary from '@/admin/components/CreateSummary.vue'
import SettingsNav from '@/admin/components/settings/SettingsNav.vue'
import { Button } from '@/ui/button'
import { Field } from '@/ui/field'
import { PinInput, PinInputGroup, PinInputSlot } from '@/ui/pin-input'
import { useLiveProps } from '@/admin/lib/liveReload'

defineProps<{
  codeSent: boolean
  graceDays: number
  counts: { projects: number; contacts: number; galleries: number; files: number }
}>()
useLiveProps(['codeSent', 'studioDeletion'])

const page = usePage()

const LENGTH = 6

const form = useForm({ code: '' })
const digits = ref<string[]>([])

function requestCode() {
  router.post('/einstellungen/studio-loeschen/code', {}, { preserveScroll: true })
}

function confirm() {
  form
    .transform(() => ({ code: digits.value.join('') }))
    .post('/einstellungen/studio-loeschen', {
      preserveScroll: true,
      onSuccess: () => {
        digits.value = []
        form.reset('code')
      },
    })
}

function cancel() {
  router.delete('/einstellungen/studio-loeschen', { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <FormShell
      :kicker="$t('settings.kicker')" :title="$t('settings.navDeleteStudio')"
    >
      <template #nav><SettingsNav active="deletion" /></template>

      <div class="min-w-0 rounded-[var(--r)] border border-destructive/40 p-5">
        <div v-if="page.props.studioDeletion" class="flex items-center gap-4 py-3 shadow-[inset_0_-1px_0_var(--rule)]">
          <span class="flex-1 text-ui font-semibold">{{ $t('settings.deletionOn', { date: page.props.studioDeletion }) }}</span>
          <Button variant="outline" @click="cancel">{{ $t('common.cancel') }}</Button>
        </div>

        <template v-else>
          <div class="flex items-center gap-4 py-3 shadow-[inset_0_-1px_0_var(--rule)]">
            <span class="flex-1 text-ui">{{ $t('settings.deletionGrace', { days: graceDays }) }}</span>
            <Button variant="outline" tone="destructive" @click="requestCode">
              {{ codeSent ? $t('settings.deletionNewCode') : $t('settings.deletionRequest') }}
            </Button>
          </div>

          <form v-if="codeSent" class="mt-6" @submit.prevent="confirm">
            <Field :label="$t('settings.deletionCode')" id="deletion-code" :error="form.errors.code">
              <div class="flex items-center gap-2.5">
                <PinInput v-model="digits" @complete="confirm">
                  <PinInputGroup>
                    <PinInputSlot v-for="index in LENGTH" :key="index" :id="index === 1 ? 'deletion-code' : undefined" :index="index - 1" />
                  </PinInputGroup>
                </PinInput>
                <Button type="submit" :disabled="form.processing">{{ $t('settings.deletionConfirm') }}</Button>
              </div>
            </Field>
          </form>
        </template>

        <div class="mt-6">
          <Button as="a" variant="outline" href="/einstellungen/export">{{ $t('settings.navExport') }}</Button>
        </div>
      </div>

      <template #aside>
        <CreateSummary
          :title="$t('settings.deletionRemoved')"
          :rows="[
            { label: $t('projects.title'), value: counts.projects.toLocaleString('de-DE') },
            { label: $t('contacts.title'), value: counts.contacts.toLocaleString('de-DE') },
            { label: $t('galleries.title'), value: counts.galleries.toLocaleString('de-DE') },
            { label: $t('settings.deletionFiles'), value: counts.files.toLocaleString('de-DE') },
          ]"
        />
      </template>
    </FormShell>
  </AdminLayout>
</template>
