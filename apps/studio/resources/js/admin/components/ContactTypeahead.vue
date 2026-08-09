<script setup lang="ts">
import { computed, ref, watch } from 'vue'

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

const initials = computed(() =>
  (model.value?.name ?? '')
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0])
    .join('')
    .toUpperCase(),
)

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
  <div v-if="model" class="picked">
    <span class="av">{{ initials }}</span>
    <div>
      <div class="nm">
        {{ model.name }}
        <span v-if="model.projectCount" class="rp">
          {{ $t('leads.contactProjects', { count: model.projectCount + 1 }) }}
        </span>
      </div>
      <div class="em">{{ [model.email, model.phone].filter(Boolean).join(' · ') }}</div>
    </div>
    <button type="button" class="chg" @click="model = null">
      {{ $t('leads.contactChange') }}
    </button>
  </div>

  <div v-else>
    <div class="ta">
      <input v-model="term" class="inp" type="search" :placeholder="$t('common.search')" style="border: none" />

      <div v-if="searched" class="list">
        <button
          v-for="contact in results"
          :key="contact.id"
          type="button"
          class="row"
          @click="choose(contact)"
        >
          <span class="av">{{ contact.name.slice(0, 1).toUpperCase() }}</span>
          <span>
            <span class="nm">{{ contact.name }}</span>
            <span class="em">{{ contact.email }}</span>
          </span>
        </button>

        <button type="button" class="row new" @click="emit('createNew', term)">
          <span class="av plus">+</span>
          <span class="nm">{{ $t('leads.contactCreate', { term }) }}</span>
        </button>
      </div>
    </div>

    <p class="hint" style="margin-top: 6px">{{ $t('leads.contactSearchHint') }}</p>
  </div>
</template>
