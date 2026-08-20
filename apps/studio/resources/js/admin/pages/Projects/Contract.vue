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

    <div class="relative mt-6 block max-w-[640px] cursor-default rounded-md border border-border p-2.5 text-inherit no-underline">
      <div class="mb-4 flex items-center justify-between"><span class="mb-[5px] text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint">{{ $t('project.contract') }}</span></div>

      <div v-if="contract" class="grid grid-cols-[repeat(3,minmax(0,1fr))] gap-2.5">
        <div class="rounded-md bg-muted px-4 pt-3.5 pb-4">
          <div class="text-[9.5px] leading-none font-semibold tracking-[0.11em] uppercase text-faint">{{ $t('project.contractTemplate') }}</div>
          <div class="mt-[9px] text-[16px] leading-[1.2] font-medium tracking-[-0.02em] tabular-nums">{{ contract.templateName }}</div>
          <div class="mt-1 text-[12.5px] leading-[1.3] tabular-nums text-muted-foreground">v{{ contract.templateVersion }}</div>
        </div>
        <div class="rounded-md bg-muted px-4 pt-3.5 pb-4">
          <div class="text-[9.5px] leading-none font-semibold tracking-[0.11em] uppercase text-faint">{{ $t('project.contractStatus') }}</div>
          <div class="mt-[9px] text-[16px] leading-[1.2] font-medium tracking-[-0.02em] tabular-nums">{{ contract.statusLabel }}</div>
        </div>
      </div>
      <p v-else-if="!header.canManageContracts || !templates.length" class="text-[11.5px] leading-[1.45] font-normal text-faint">{{ $t('project.contractNone') }}</p>

      <p v-if="contract?.status === 'pending'" class="text-[11.5px] leading-[1.45] font-normal text-faint">{{ $t('project.contractPending') }}</p>

      <Button as="a"
        v-if="contract?.downloadUrl" variant="ghost"
        :href="contract.downloadUrl"
        download
        class="mt-2.5"
      >
        {{ $t('project.contractDownload') }}
      </Button>

      <form
        v-if="header.canManageContracts && templates.length"
        class="mt-4"
        @submit.prevent="generate"
      >
        <div class="mb-6 flex min-w-0 flex-col gap-1.5">
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
      <Button v-else-if="header.canManageContracts" :as="Link" href="/dokumentvorlagen/neu" class="mt-4">
        {{ $t('documentTemplates.create') }}
      </Button>
    </div>
  </AdminLayout>
</template>
