<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'

const model = defineModel<App.Crm.Data.CaseSuggestionData | null>({ required: true })

const term = ref('')
const results = ref<App.Crm.Data.CaseSuggestionData[]>([])
const suggestions = ref<App.Crm.Data.CaseSuggestionData[]>([])

async function search(value: string) {
  const response = await fetch(`/projekte/suche?q=${encodeURIComponent(value)}`, {
    headers: { Accept: 'application/json' },
  })
  return (await response.json()).projects
}

onMounted(async () => {
  if (!model.value) {
    suggestions.value = await search('')
  }
})

let timer: ReturnType<typeof setTimeout> | undefined

watch(term, (value) => {
  clearTimeout(timer)

  if (value.trim().length < 2) {
    results.value = []
    return
  }

  timer = setTimeout(async () => {
    results.value = await search(value)
  }, 250)
})

const rows = computed(() => (term.value.trim().length < 2 ? suggestions.value : results.value))

function choose(project: App.Crm.Data.CaseSuggestionData) {
  model.value = project
  results.value = []
}

function clear() {
  model.value = null
  term.value = ''
}

const ROW = [
  'flex w-full cursor-pointer items-baseline gap-[9px] border-0 bg-none px-0 py-2 text-left text-inherit no-underline',
  'shadow-[inset_0_1px_0_#EEEEF0] first:shadow-none',
  'not-first:border-t not-first:border-muted',
].join(' ')
</script>

<template>
  <div v-if="model" class="flex items-center gap-[11px] rounded-md border border-line-hover px-3 py-[9px]">
    <div class="min-w-0">
      <div class="truncate text-small leading-[1.25] font-semibold">{{ model.name }}</div>
      <div v-if="model.contactName" class="mt-px text-small leading-[1.3] font-normal text-muted-foreground">
        {{ model.contactName }}
      </div>
    </div>
    <span class="flex-none text-small tracking-[0.05em] uppercase" :class="model.isBooked ? 'text-ink-2' : 'text-action'">
      {{ model.isBooked ? $t('create.project') : $t('create.lead') }}
    </span>
    <Button class="ml-auto" type="button" variant="pill-muted" @click="clear">{{ $t('common.edit') }}</Button>
  </div>

  <div v-else>
    <Input v-model="term" type="search" :placeholder="$t('create.pickPlaceholder')" />

    <div v-if="rows.length" class="mt-1.5">
      <Button
        v-for="project in rows"
        :key="project.id"
        variant="ghost"
        :class="ROW"
        type="button"
        @click="choose(project)"
      >
        <span class="min-w-0 flex-1 truncate text-small leading-[1.25] font-semibold">{{ project.name }}</span>
        <span v-if="project.contactName" class="flex-none text-small leading-[1.3] text-muted-foreground">
          {{ project.contactName }}
        </span>
        <span class="w-[62px] flex-none text-right text-small tracking-[0.05em] uppercase" :class="project.isBooked ? 'text-ink-2' : 'text-action'">
          {{ project.isBooked ? $t('create.project') : $t('create.lead') }}
        </span>
      </Button>
    </div>
  </div>
</template>
