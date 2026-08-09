<script setup lang="ts">
import { ref, watch } from 'vue'
import { Input } from '@/ui/input'

// The ordering enforced here IS the anti-duplicate mechanism, not a
// convenience: portal access in P2 hangs off Contact, so a repeat client must
// resolve to the same row. The name fields only unfold once a search has run,
// which makes "create new" unreachable until the existing matches have been
// seen. A plain text field cannot do this — it just lets you type a fresh name.
const model = defineModel<App.Crm.Data.ContactSuggestionData | null>({ required: true })
const emit = defineEmits<{ createNew: [term: string] }>()

const term = ref('')
const results = ref<App.Crm.Data.ContactSuggestionData[]>([])
const searched = ref(false)

let timer: ReturnType<typeof setTimeout> | undefined

watch(term, (value) => {
  clearTimeout(timer)
  searched.value = false

  if (value.trim().length < 2) {
    results.value = []
    return
  }

  timer = setTimeout(async () => {
    const response = await fetch(`/kontakte/suche?q=${encodeURIComponent(value)}`, {
      headers: { Accept: 'application/json' },
    })
    results.value = (await response.json()).contacts
    searched.value = true
  }, 250)
})

function choose(contact: App.Crm.Data.ContactSuggestionData) {
  model.value = contact
  results.value = []
}
</script>

<template>
  <div v-if="model" class="flex items-center gap-3 rounded-md border p-3">
    <div class="flex-1">
      <div class="text-sm font-medium">
        {{ model.name }}
        <span v-if="model.projectCount" class="ml-1 text-xs text-muted-foreground">
          {{ $t('leads.contactProjects', { count: model.projectCount + 1 }) }}
        </span>
      </div>
      <div class="text-xs text-muted-foreground">
        {{ [model.email, model.phone].filter(Boolean).join(' · ') }}
      </div>
    </div>
    <button type="button" class="text-sm underline" @click="model = null">
      {{ $t('leads.contactChange') }}
    </button>
  </div>

  <div v-else>
    <Input v-model="term" type="search" :placeholder="$t('common.search')" />

    <ul v-if="searched" class="mt-1 divide-y rounded-md border">
      <li v-for="contact in results" :key="contact.id">
        <button
          type="button"
          class="w-full px-3 py-2 text-left text-sm hover:bg-muted"
          @click="choose(contact)"
        >
          <span class="font-medium">{{ contact.name }}</span>
          <span class="ml-2 text-xs text-muted-foreground">{{ contact.email }}</span>
        </button>
      </li>
      <li>
        <button
          type="button"
          class="w-full px-3 py-2 text-left text-sm hover:bg-muted"
          @click="emit('createNew', term)"
        >
          {{ $t('leads.contactCreate', { term }) }}
        </button>
      </li>
    </ul>

    <p class="mt-1 text-xs text-muted-foreground">{{ $t('leads.contactSearchHint') }}</p>
  </div>
</template>
