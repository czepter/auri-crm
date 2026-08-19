<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'

type Starter = { key: string; name: string; description: string; shootKind: string | null }

const props = defineProps<{
  starters: Record<string, Starter[]>
  defaults: string[]
  typeLabels: Record<string, string>
}>()

const emit = defineEmits<{ saved: [] }>()

// T-18: a sensible default set pre-ticked, and everything else offered. The
// wizard's own "weiter" button drives submit() through the shared pane
// contract — see Onboarding.vue.
const chosen = ref<string[]>([...props.defaults])
const form = useForm({ keys: [] as string[] })

function toggle(key: string) {
  chosen.value = chosen.value.includes(key)
    ? chosen.value.filter((k) => k !== key)
    : [...chosen.value, key]
}

function submit() {
  form.keys = chosen.value
  // Idempotent server-side, so a second pass through this step is harmless —
  // see SeedStarterTemplates.
  form.post('/dokumentvorlagen/starter', { preserveState: true, onSuccess: () => emit('saved') })
}

defineExpose({ form, submit })
</script>

<template>
  <div>
    <div v-for="(entries, type) in starters" :key="type" class="mt-8 first-of-type:mt-6">
      <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ typeLabels[type] ?? type }}</div>
      <label v-for="starter in entries" :key="starter.key" class="flex items-center gap-2.5">
        <input type="checkbox" :checked="chosen.includes(starter.key)" @change="toggle(starter.key)">
        <span>
          <b>{{ starter.name }}</b>
          <em class="text-[11.5px] leading-[1.45] text-faint"> — {{ starter.description }}</em>
        </span>
      </label>
    </div>
  </div>
</template>
