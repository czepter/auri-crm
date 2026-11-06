<script setup lang="ts">
import { computed, ref } from 'vue'
import { refDebounced } from '@vueuse/core'
import { ArrowLeft } from 'lucide-vue-next'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import FormShell from '@/ui/page/FormShell.vue'
import { Button } from '@/ui/button'
import { ChoiceGroup } from '@/ui/choice-group'
import { useI18n } from 'vue-i18n'

const props = defineProps<{ label: string; back: string; src: string; phone?: boolean }>()

const { t } = useI18n()

const state = ref('inquiry')
const register = ref('formal')

const stateOptions = ['inquiry', 'active', 'done'].map((value) => ({ value, label: t(`portalSettings.welcomeState.${value}`) }))
const registerOptions = ['formal', 'informal'].map((value) => ({ value, label: t(value === 'formal' ? 'settings.clientRegisterFormal' : 'settings.clientRegisterInformal') }))

const framed = computed(() => {
  const url = new URL(props.src, 'http://x')
  if (props.phone) {
    url.searchParams.set('zustand', state.value)
    url.searchParams.set('anrede', register.value)
  }

  return url.pathname + url.search
})
const frameSrc = refDebounced(framed, 300)
</script>

<template>
  <AdminLayout active="einstellungen">
    <FormShell v-if="phone" :title="$t(label)" :kicker="$t('settings.kicker')" :kicker-href="back">
      <template #head>
        <Button as="a" class="ml-auto" variant="outline" :href="back">
          <ArrowLeft />
          {{ $t('common.back') }}
        </Button>
      </template>

      <div class="flex flex-col gap-6">
        <ChoiceGroup v-model="register" :label="$t('settings.clientRegister')" variant="segmented" :options="registerOptions" />
        <ChoiceGroup v-model="state" :label="$t('portalSettings.welcomeSection')" variant="segmented" :options="stateOptions" />
      </div>

      <template #preview>
        <aside class="sticky top-6 w-[430px] flex-none">
          <div class="rounded-lg bg-muted p-5">
            <iframe :src="frameSrc" class="mx-auto block h-[calc(100dvh-200px)] w-[390px] rounded-md border-0 bg-background" title="" />
          </div>
        </aside>
      </template>
    </FormShell>

    <FormShell v-else :title="$t(label)" :kicker="$t('settings.kicker')" :kicker-href="back" wide>
      <template #head>
        <Button as="a" class="ml-auto" variant="outline" :href="back">
          <ArrowLeft />
          {{ $t('common.back') }}
        </Button>
      </template>

      <iframe :src class="block h-[calc(100dvh-220px)] w-full rounded-[var(--r)] border-0 bg-paper shadow-card" title="" />
    </FormShell>
  </AdminLayout>
</template>
