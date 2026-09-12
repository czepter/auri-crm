<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Button } from '@/ui/button'
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

const ROW = [
  'flex w-full cursor-pointer items-baseline gap-[9px] border-0 bg-none px-0 py-2 text-left text-inherit no-underline',
  'shadow-[inset_0_1px_0_#EEEEF0] first:shadow-none',
  'not-first:border-t not-first:border-muted',
].join(' ')
</script>

<template>
  <div v-if="model" class="flex items-center gap-[11px] rounded-md border border-line-hover px-3 py-[9px]">
    <span
      class="flex size-[30px] flex-none items-center justify-center rounded-full bg-[#EDE7F6] text-[12px] leading-none font-semibold text-[#5B4B8A]"
    >
      {{ initials }}
    </span>
    <div>
      <div class="text-[13px] leading-[1.25] font-semibold">
        {{ model.name }}
        <span
          v-if="model.projectCount"
          class="ml-2 inline rounded-[var(--r-sm)] bg-muted px-[7px] py-0.5 text-[10px] leading-[1.5] font-medium text-muted-foreground"
        >
          {{ $t('leads.contactProjects', { count: model.projectCount + 1 }) }}
        </span>
      </div>
      <div class="mt-px text-[11.5px] leading-[1.3] font-normal text-muted-foreground">{{ [model.email, model.phone].filter(Boolean).join(' · ') }}</div>
    </div>
    <!-- leading-none again after the size: tailwind-merge treats a new
         text-[…] as clearing the variant's line-height, because Tailwind lets
         one class carry both. -->
    <Button
      variant="ghost"
      class="ml-auto text-[11.5px] leading-none text-muted-foreground hover:text-foreground"
      type="button"
      @click="model = null"
    >
      {{ $t('leads.contactChange') }}
    </Button>
  </div>

  <div v-else>
    <div class="overflow-hidden rounded-md border border-line-hover">
      <Input v-model="term" class="border-0" type="search" :placeholder="$t('common.search')" />

      <div v-if="searched" class="border-t border-border">
        <!-- A list row, not one of the system's three buttons: ghost is here
             for the reset and the shared element, nothing more. The row's own
             rhythm came from crm.css's inbox list, which reached these rows
             through .list and decided their padding, gap and baseline. -->
        <Button
          v-for="contact in results"
          :key="contact.id"
          variant="ghost"
          :class="ROW"
          type="button"
          @click="choose(contact)"
        >
          <span class="flex size-[26px] flex-none items-center justify-center rounded-full bg-[#E7F1F2] text-[11px] leading-none font-semibold text-[#1C6870]">{{ contact.name.slice(0, 1).toUpperCase() }}</span>
          <span>
            <span class="text-[12.5px] leading-[1.25] font-semibold">{{ contact.name }}</span>
            <span class="mt-px text-[11.5px] leading-[1.3] font-normal text-muted-foreground">{{ contact.email }}</span>
          </span>
        </Button>

        <Button variant="ghost" :class="[ROW, 'bg-muted']" type="button" @click="emit('createNew', term)">
          <span class="flex size-[26px] flex-none items-center justify-center rounded-full bg-[#E7F1F2] text-[11px] leading-none font-semibold text-[#1C6870] bg-foreground text-[14px] text-background">+</span>
          <span class="text-[12.5px] leading-[1.25] font-semibold">{{ $t('leads.contactCreate', { term }) }}</span>
        </Button>
      </div>
    </div>
  </div>
</template>
