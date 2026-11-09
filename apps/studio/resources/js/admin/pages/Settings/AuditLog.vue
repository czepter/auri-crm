<script setup lang="ts">
import { computed, reactive } from 'vue'
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

type Props = {
  entries: { id: string; at: string; event: string; actor: string | null; label: string; detail: string[]; target: { name: string; href: string } | null }[]
  nextCursor: string | null
  filters: { ereignis: string; person: string; von: string; bis: string; anmeldungen: boolean }
  events: Option[]
  people: Option[]
}

const props = defineProps<Props>()
useLiveProps(['entries', 'nextCursor', 'events', 'people'])

const query = reactive({ ...props.filters })

function apply(cursor?: string) {
  const params = Object.fromEntries(
    Object.entries({ ...query, anmeldungen: query.anmeldungen ? 1 : 0, cursor }).filter(([, value]) => value),
  )

  router.get('/einstellungen/protokoll', params, { preserveScroll: true })
}

type Row = Props['entries'][number] & { count: number }

// Ein Kunde, der das Portal öffnet, erzeugt je Besuch zwei Zeilen; hintereinander sind sie ein Eintrag.
const PORTAL = ['portal.link_issued', 'portal.link_consumed']

const rows = computed(() =>
  props.entries.reduce<Row[]>((all, entry) => {
    const last = all.at(-1)

    if (last && PORTAL.includes(entry.event) && PORTAL.includes(last.event)) {
      last.count += 1
      if (last.target?.href !== entry.target?.href) last.target = null

      return all
    }

    all.push({ ...entry, count: 1 })

    return all
  }, []),
)

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

      <div class="min-w-0 max-w-[1280px]">
        <div class="sticky top-0 z-10 mb-2 grid grid-cols-4 gap-3 bg-background py-3 max-[640px]:grid-cols-2">
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
            <Input id="log-von" v-model="query.von" lang="de" type="date" :title="$t('common.dateFormat')" :aria-label="$t('audit.from') + ' (' + $t('common.dateFormat') + ')'" @change="apply()" />
          </Field>
          <Field :label="$t('audit.to')" id="log-bis">
            <Input id="log-bis" v-model="query.bis" lang="de" type="date" :title="$t('common.dateFormat')" :aria-label="$t('audit.to') + ' (' + $t('common.dateFormat') + ')'" @change="apply()" />
          </Field>
        </div>

        <button
          type="button"
          class="mb-2 cursor-pointer rounded-full border-0 px-3 py-1 text-small leading-[1.3]"
          :class="query.anmeldungen ? 'bg-foreground text-background' : 'bg-sunk-2 text-foreground'"
          :aria-pressed="query.anmeldungen"
          @click="query.anmeldungen = !query.anmeldungen; apply()"
        >{{ $t('audit.logins') }}</button>

        <div
          v-for="entry in rows"
          :key="entry.id"
          class="flex items-start gap-4 py-2.5 shadow-[inset_0_-1px_0_var(--rule)]"
        >
          <span class="w-36 flex-none text-small tabular-nums text-ink-2">{{ at(entry.at) }}</span>
          <span class="w-36 flex-none truncate text-small">{{ entry.actor ?? '—' }}</span>
          <div class="min-w-0 flex-1">
            <div class="truncate text-ui font-semibold">{{ entry.count > 1 ? $t('audit.portalViews', { n: entry.count }) : entry.label }}</div>
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
