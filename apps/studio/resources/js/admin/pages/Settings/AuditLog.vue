<script setup lang="ts">
import { reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import { Button } from '@/ui/button'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { NONE, fromOption, toOption } from '@/lib/select'

type Option = { value: string; label: string }

const props = defineProps<{
  entries: { id: string; at: string; actor: string | null; label: string; target: { name: string; href: string } | null }[]
  nextCursor: string | null
  filters: { ereignis: string; person: string; von: string; bis: string }
  events: Option[]
  people: Option[]
}>()

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
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('settings.kicker')" :title="$t('settings.navAudit')" />

        <div class="px-8 pt-7 pb-8">
          <div class="flex items-start gap-8 max-[640px]:flex-col max-[640px]:gap-4">
            <SettingsNav active="audit" />

            <div class="max-w-[820px] min-w-0 flex-1">
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
                class="flex items-center gap-4 py-2.5 shadow-[inset_0_-1px_0_var(--rule)]"
              >
                <span class="w-36 flex-none text-[12.5px] tabular-nums text-ink-2">{{ at(entry.at) }}</span>
                <span class="w-36 flex-none truncate text-[13px]">{{ entry.actor ?? '—' }}</span>
                <span class="min-w-0 flex-1 truncate text-[13.5px] font-semibold">{{ entry.label }}</span>
                <Link v-if="entry.target" class="max-w-48 flex-none truncate text-[12.5px] text-inherit" :href="entry.target.href">{{ entry.target.name }}</Link>
              </div>

              <div v-if="nextCursor" class="mt-4">
                <Button type="button" variant="outline" @click="apply(nextCursor)">{{ $t('audit.older') }}</Button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
