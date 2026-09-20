<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import { Button } from '@/ui/button'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Switch } from '@/ui/switch'

const props = defineProps<{ months: number | null }>()

const enabled = ref(props.months !== null)
const form = useForm({ retention_months: props.months ?? 12 })

function save() {
  form
    .transform((data) => ({ retention_months: enabled.value ? data.retention_months : null }))
    .patch('/einstellungen/aufbewahrung', { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('settings.kicker')" :title="$t('settings.navRetention')" />

        <div class="px-8 pt-7 pb-8">
          <div class="flex items-start gap-8 max-[640px]:flex-col max-[640px]:gap-4">
            <SettingsNav active="retention" />

            <form class="max-w-[620px] min-w-0 flex-1" @submit.prevent="save">
              <div class="flex items-center gap-3">
                <Switch id="retention-on" v-model="enabled" />
                <Label for="retention-on">{{ $t('settings.retentionOn') }}</Label>
              </div>

              <Field v-if="enabled" class="mt-4 w-56" :label="$t('settings.retentionMonths')" id="retention-months" :error="form.errors.retention_months">
                <Input id="retention-months" v-model.number="form.retention_months" class="tabular-nums" type="number" min="1" max="120" />
              </Field>

              <div class="mt-6">
                <Button type="submit" :disabled="form.processing">{{ $t('settings.numberingSave') }}</Button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
