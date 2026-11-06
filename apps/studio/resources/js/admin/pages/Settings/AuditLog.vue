<script setup lang="ts">
import { reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import FormShell from '@/ui/page/FormShell.vue'
import SettingsNav from '@/admin/components/settings/SettingsNav.vue'
import { Button } from '@/ui/button'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { NONE, fromOption, toOption } from '@/lib/select'
import { useLiveProps } from '@/admin/lib/liveReload'

type Option = { value: string; label: string }

const props = defineProps<{
  entries: { id: string; at: string; actor: string | null; label: string; detail: string[]; target: { name: string; href: string } | null }[]
  nextCursor: string | null
  filters: { ereignis: string; person: string; von: string; bis: string }
  events: Option[]
  people: Option[]
}>()
useLiveProps(['entries', 'nextCursor', 'events', 'people'])

const query = reactive({ ...props.filters })

function apply(cursor?: string) {
  const params = Object.fromEntries(
    Object.entries({ ...query, cursor }).filter(([, value]) => value),
  )

  router.get('/einstellungen/protokoll', params, { preserveScroll: true })
}

function at(iso: string) {
  return new Date(iso).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <FormShell
      :kicker="$t('settings.kicker')" :title="$t('settings.navAudit')" wide
    >
      <template #nav><SettingsNav active="audit" /></template>

      <div class="min-w-0">
        <div class="mb-5 grid grid-cols-4 gap-3 max-[640px]:grid-cols-2">
          <Field :label="$t('audit.event')" id="log-ereignis">
            <Select :model-value="toOption(query.ereignis)" @update:model-value="(value) => { query.ereignis = fromOption(value); apply() }">
              <SelectTrigger id="log-ereignis"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem :value="NONE">{{ $t('audit.all') }}</SelectItem>
                <SelectItem v-for="event in events" :key="event.value" :value="event.value">{{ event.label }}</SelectItem>
              </SelectContent>
            </Select>
          </Field>
          <Field :label="$t('audit.person')" id="log-person">
            <Select :model-value="toOption(query.person)" @update:model-value="(value) => { query.person = fromOption(value); apply() }">
              <SelectTrigger id="log-person"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem :value="NONE">{{ $t('audit.all') }}</SelectItem>
                <SelectItem v-for="person in people" :key="person.value" :value="person.value">{{ person.label }}</SelectItem>
              </SelectContent>
            </Select>
          </Field>
          <Field :label="$t('audit.from')" id="log-von">
            <Input id="log-von" v-model="query.von" type="date" @change="apply()" />
          </Field>
          <Field :label="$t('audit.to')" id="log-bis">
            <Input id="log-bis" v-model="query.bis" type="date" @change="apply()" />
          </Field>
        </div>

        <div
          v-for="entry in entries"
          :key="entry.id"
          class="flex items-start gap-4 py-2.5 shadow-[inset_0_-1px_0_var(--rule)]"
        >
          <span class="w-36 flex-none text-small tabular-nums text-ink-2">{{ at(entry.at) }}</span>
          <span class="w-36 flex-none truncate text-small">{{ entry.actor ?? '—' }}</span>
          <div class="min-w-0 flex-1">
            <div class="truncate text-ui font-semibold">{{ entry.label }}</div>
            <div v-for="line in entry.detail" :key="line" class="mt-0.5 text-small break-words text-ink-2">{{ line }}</div>
          </div>
          <Link v-if="entry.target" class="max-w-48 flex-none truncate text-small text-inherit" :href="entry.target.href">{{ entry.target.name }}</Link>
        </div>

        <div v-if="nextCursor" class="mt-4">
          <Button type="button" variant="outline" @click="apply(nextCursor)">{{ $t('audit.older') }}</Button>
        </div>
      </div>
    </FormShell>
  </AdminLayout>
</template>
