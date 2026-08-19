<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectHeader from '@/admin/components/ProjectHeader.vue'
import { Button } from '@/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Label } from '@/ui/label'

const props = defineProps<{
  header: App.Crm.Data.ProjectHeaderData
  templates: { value: string; label: string }[]
  contract: App.Contracts.Data.ContractData | null
}>()

const form = useForm({ template: props.templates[0]?.value ?? '' })

function generate() {
  form.post(`/auftraege/${props.header.id}/vertraege`, { preserveScroll: true })
}

// The request that triggers a render returns immediately — this is the
// screen reacting to completion on a later request, rather than blocking on
// the queued job. Polls only while a render is actually in flight.
let timer: ReturnType<typeof setInterval> | undefined

function poll() {
  clearPoll()
  timer = setInterval(() => {
    router.reload({ only: ['contract'] })
  }, 2000)
}

function clearPoll() {
  if (timer) clearInterval(timer)
  timer = undefined
}

watch(
  () => props.contract?.status,
  (status) => (status === 'pending' ? poll() : clearPoll()),
  { immediate: true },
)

onBeforeUnmount(clearPoll)
</script>

<template>
  <AdminLayout active="anfragen">
    <ProjectHeader :header="header" active="contract" />

    <div class="card static" style="max-width: 640px; margin-top: var(--s5)">
      <div class="cardhead"><span class="kick">{{ $t('project.contract') }}</span></div>

      <div v-if="contract" class="tiles">
        <div class="fact">
          <div class="l">{{ $t('project.contractTemplate') }}</div>
          <div class="v">{{ contract.templateName }}</div>
          <div class="s">v{{ contract.templateVersion }}</div>
        </div>
        <div class="fact">
          <div class="l">{{ $t('project.contractStatus') }}</div>
          <div class="v">{{ contract.statusLabel }}</div>
        </div>
      </div>
      <p v-else-if="!header.canManageContracts || !templates.length" class="hint">{{ $t('project.contractNone') }}</p>

      <p v-if="contract?.status === 'pending'" class="hint">{{ $t('project.contractPending') }}</p>

      <Button as="a"
        v-if="contract?.downloadUrl" variant="ghost"
        :href="contract.downloadUrl"
        download
        style="margin-top: var(--s3)"
      >
        {{ $t('project.contractDownload') }}
      </Button>

      <form
        v-if="header.canManageContracts && templates.length"
        style="margin-top: var(--s4)"
        @submit.prevent="generate"
      >
        <div class="fld grp">
          <Label for="template">{{ $t('project.contractTemplate') }}</Label>
          <Select :model-value="form.template" @update:model-value="(value) => (form.template = String(value))">
            <SelectTrigger id="template">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="option in templates" :key="option.value" :value="option.value">
                {{ option.label }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
        <Button type="submit" :disabled="form.processing">
          {{ $t('project.contractGenerate') }}
        </Button>
      </form>
      <Button v-else-if="header.canManageContracts" :as="Link" href="/dokumentvorlagen/neu" style="margin-top: var(--s4)">
        {{ $t('documentTemplates.create') }}
      </Button>
    </div>
  </AdminLayout>
</template>
