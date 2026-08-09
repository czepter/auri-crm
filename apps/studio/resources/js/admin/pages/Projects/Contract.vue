<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ProjectHeader from '@/admin/components/ProjectHeader.vue'

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

      <div v-if="contract" class="kv">
        <div class="kvrow">
          <span>{{ $t('project.contractTemplate') }}</span>
          <span>{{ contract.templateName }} · v{{ contract.templateVersion }}</span>
        </div>
        <div class="kvrow">
          <span>{{ $t('project.contractStatus') }}</span>
          <span>{{ contract.statusLabel }}</span>
        </div>
      </div>
      <p v-else class="hint">{{ $t('project.contractNone') }}</p>

      <p v-if="contract?.status === 'pending'" class="hint">{{ $t('project.contractPending') }}</p>

      <a
        v-if="contract?.downloadUrl"
        class="txtbtn"
        :href="contract.downloadUrl"
        download
        style="margin-top: var(--s3)"
      >
        {{ $t('project.contractDownload') }}
      </a>

      <form
        v-if="header.canManageContracts && templates.length"
        style="margin-top: var(--s4)"
        @submit.prevent="generate"
      >
        <div class="fld grp">
          <label for="template">{{ $t('project.contractTemplate') }}</label>
          <select id="template" v-model="form.template" class="inp">
            <option v-for="option in templates" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>
        <button class="btn" type="submit" :disabled="form.processing">
          {{ $t('project.contractGenerate') }}
        </button>
      </form>
      <p v-else-if="header.canManageContracts" class="hint">{{ $t('project.contractNoTemplates') }}</p>
    </div>
  </AdminLayout>
</template>
