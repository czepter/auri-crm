<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { formatEuros } from '@/lib/money'

type Rate = { id: string; label: string; amount: number; dueOn: string }

// A contact picked here is the invoice without a project; a project carries
// its own contact.
const project = defineModel<App.Crm.Data.CaseSuggestionData | null>('project', { required: true })
const contact = defineModel<App.Crm.Data.ContactSuggestionData | null>('contact', { required: true })
const props = defineProps<{
  rates: Record<string, Rate[]>
  billable: App.Crm.Data.CaseSuggestionData[]
}>()
const emit = defineEmits<{ createNew: [term: string] }>()

const { t } = useI18n()

const term = ref('')
const projects = ref<App.Crm.Data.CaseSuggestionData[]>([])
const contacts = ref<App.Crm.Data.ContactSuggestionData[]>([])
const recent = ref<App.Crm.Data.CaseSuggestionData[]>([])
const searched = ref(false)

const typing = computed(() => term.value.trim().length >= 2)

const others = computed(() => {
  const billable = new Set(props.billable.map((entry) => entry.id))

  return recent.value.filter((entry) => !billable.has(entry.id))
})

async function search(url: string) {
  return (await fetch(url, { headers: { Accept: 'application/json' } })).json()
}

onMounted(async () => {
  if (!project.value) {
    recent.value = (await search('/projekte/suche?q=')).projects
  }
})

let timer: ReturnType<typeof setTimeout> | undefined

watch(term, (value) => {
  clearTimeout(timer)
  searched.value = false

  if (value.trim().length < 2) {
    projects.value = []
    contacts.value = []
    return
  }

  timer = setTimeout(async () => {
    const query = encodeURIComponent(value)
    const [byProject, byContact] = await Promise.all([
      search(`/projekte/suche?q=${query}`),
      search(`/kontakte/suche?q=${query}`),
    ])

    projects.value = byProject.projects
    contacts.value = byContact.contacts
    searched.value = true
  }, 250)
})

function chooseProject(entry: App.Crm.Data.CaseSuggestionData) {
  project.value = entry
  contact.value = null
}

function chooseContact(entry: App.Crm.Data.ContactSuggestionData) {
  contact.value = entry
  project.value = null
}

function clear() {
  project.value = null
  contact.value = null
  term.value = ''
}

function hint(entry: App.Crm.Data.CaseSuggestionData) {
  const rates = props.rates[entry.id] ?? []

  if (!rates.length) {
    return null
  }

  return rates.length === 1
    ? `${rates[0].label} · ${formatEuros(rates[0].amount)}`
    : `${t('invoicesCreate.ratesOpen', rates.length)} · ${formatEuros(rates.reduce((sum, rate) => sum + rate.amount, 0))}`
}

const GROUP = 'mt-4 mb-1 px-0.5 text-small leading-none font-semibold text-muted-foreground'

const ROW = [
  'flex w-full cursor-pointer items-baseline gap-[9px] border-0 bg-none px-0 py-2 text-left text-inherit no-underline',
  'shadow-[inset_0_1px_0_var(--rule)] first:shadow-none',
  'not-first:border-t not-first:border-muted',
].join(' ')
</script>

<template>
  <div v-if="project || contact" class="flex items-center gap-[11px] rounded-md border border-line-hover px-3 py-[9px]">
    <div class="min-w-0">
      <div class="truncate text-small leading-[1.25] font-semibold">{{ project?.name ?? contact?.name }}</div>
      <div v-if="project?.contactName ?? contact?.email" class="mt-px text-small leading-[1.3] font-normal text-muted-foreground">
        {{ project?.contactName ?? contact?.email }}
      </div>
    </div>
    <span v-if="project" class="flex-none text-small" :class="project.isBooked ? 'text-ink-2' : 'text-ring'">
      {{ project.isBooked ? $t('create.project') : $t('create.lead') }}
    </span>
    <Button class="ml-auto" type="button" variant="secondary" @click="clear">{{ $t('common.edit') }}</Button>
  </div>

  <div v-else>
    <Input :aria-label="$t('invoicesCreate.forPlaceholder')" v-model="term" type="search" :placeholder="$t('invoicesCreate.forPlaceholder')" />

    <template v-if="!typing">
      <p v-if="billable.length" :class="GROUP">{{ $t('invoicesCreate.groupBillable') }}</p>
      <Button v-for="entry in billable" :key="entry.id" variant="ghost" :class="ROW" type="button" @click="chooseProject(entry)">
        <span class="min-w-0 flex-1 truncate text-small leading-[1.25] font-semibold">{{ entry.name }}</span>
        <span class="flex-none text-small leading-[1.3] font-semibold text-ring">{{ hint(entry) }}</span>
      </Button>

      <p v-if="others.length && billable.length" :class="GROUP">{{ $t('invoicesCreate.groupOther') }}</p>
      <Button v-for="entry in others" :key="entry.id" variant="ghost" :class="ROW" type="button" @click="chooseProject(entry)">
        <span class="min-w-0 flex-1 truncate text-small leading-[1.25] font-semibold">{{ entry.name }}</span>
        <span v-if="entry.contactName" class="flex-none text-small leading-[1.3] text-muted-foreground">{{ entry.contactName }}</span>
        <span class="w-[62px] flex-none text-right text-small" :class="entry.isBooked ? 'text-ink-2' : 'text-ring'">
          {{ entry.isBooked ? $t('create.project') : $t('create.lead') }}
        </span>
      </Button>
    </template>

    <template v-else-if="searched">
      <p v-if="projects.length" :class="GROUP">{{ $t('invoicesCreate.groupProjects') }}</p>
      <Button v-for="entry in projects" :key="entry.id" variant="ghost" :class="ROW" type="button" @click="chooseProject(entry)">
        <span class="min-w-0 flex-1 truncate text-small leading-[1.25] font-semibold">{{ entry.name }}</span>
        <span v-if="hint(entry)" class="flex-none text-small leading-[1.3] font-semibold text-ring">{{ hint(entry) }}</span>
        <span v-else-if="entry.contactName" class="flex-none text-small leading-[1.3] text-muted-foreground">{{ entry.contactName }}</span>
        <span class="w-[62px] flex-none text-right text-small" :class="entry.isBooked ? 'text-ink-2' : 'text-ring'">
          {{ entry.isBooked ? $t('create.project') : $t('create.lead') }}
        </span>
      </Button>

      <p :class="GROUP">{{ $t('invoicesCreate.groupContacts') }}</p>
      <Button v-for="entry in contacts" :key="entry.id" variant="ghost" :class="ROW" type="button" @click="chooseContact(entry)">
        <span class="min-w-0 flex-1 truncate text-small leading-[1.25] font-semibold">{{ entry.name }}</span>
        <span v-if="entry.email" class="flex-none text-small leading-[1.3] text-muted-foreground">{{ entry.email }}</span>
      </Button>

      <Button variant="ghost" :class="ROW" type="button" @click="emit('createNew', term.trim())">
        <span class="min-w-0 flex-1 truncate text-small leading-[1.25] font-medium text-ring">
          {{ $t('leads.contactCreate', { term: term.trim() }) }}
        </span>
      </Button>
    </template>
  </div>
</template>
