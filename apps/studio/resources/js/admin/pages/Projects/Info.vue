<script setup lang="ts">
import { computed } from 'vue'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import CaseRail from '@/admin/components/crm/CaseRail.vue'
import ProjectShell from '@/admin/components/crm/ProjectShell.vue'
import ProjectStep from '@/admin/components/crm/ProjectStep.vue'
import { nextStep, paymentCall } from '@/admin/lib/projectNextStep'
import { useLiveProps } from '@/admin/lib/liveReload'

const props = defineProps<{
  header: App.Crm.Screens.CaseHeaderData
  shoots: App.Crm.Data.ShootData[]
}>()
useLiveProps(['header', 'shoots'])

const hasStep = computed(
  () => (props.header.canManageShoots && nextStep(props.shoots)) || paymentCall(props.header.paymentPlan, props.header.people),
)
</script>

<template>
  <AdminLayout :active="header.isBooked ? 'projekte' : 'anfragen'">
    <ProjectShell :header="header" active="info">
      <template v-if="hasStep" #stage>
        <ProjectStep :header="header" :shoots="shoots" />
      </template>

      <div class="min-[1081px]:hidden">
        <CaseRail :header="header" />
      </div>
    </ProjectShell>
  </AdminLayout>
</template>
