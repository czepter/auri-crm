<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import { AlertDialog, AlertDialogBody, AlertDialogCancel, AlertDialogContent, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/ui/alert-dialog'
import { Button } from '@/ui/button'
import { ChoiceGroup } from '@/ui/choice-group'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Switch } from '@/ui/switch'

type Policy = App.Privacy.Data.RetentionPolicyData
type Part = keyof Policy

const props = defineProps<{
  months: number | null
  policy: Policy
  candidates: App.Privacy.Data.RetentionCandidateData[]
  funnel: { total: number; booked: number }
}>()

const PARTS: Part[] = ['name', 'text', 'messages', 'offers', 'galleries', 'order']
const TEMPLATES: { id: string; policy: Policy }[] = [
  { id: 'Anonymize', policy: { name: true, text: true, messages: true, offers: true, galleries: true, order: false } },
  { id: 'Texts', policy: { name: false, text: true, messages: true, offers: false, galleries: false, order: false } },
  { id: 'Name', policy: { name: true, text: false, messages: false, offers: false, galleries: false, order: false } },
  { id: 'All', policy: { name: true, text: true, messages: true, offers: true, galleries: true, order: true } },
]

const enabled = ref(props.months !== null)
const months = ref(String(props.months ?? 12))
const chosen = reactive<Policy>({ ...props.policy })
const confirming = ref(false)

const form = useForm({ current_password: '' })

// Der Auftrag trägt die anderen Teile: mit ihm sind sie alle weg.
const effective = computed<Policy>(() =>
  chosen.order ? { name: true, text: true, messages: true, offers: true, galleries: true, order: true } : { ...chosen },
)

const monthOptions = computed(() =>
  [...new Set([6, 12, 18, 24, Number(months.value)])].sort((a, b) => a - b).map((n) => ({ value: String(n), label: String(n) })),
)

const template = computed(() => TEMPLATES.find((t) => PARTS.every((part) => t.policy[part] === effective.value[part]))?.id ?? null)

function pickTemplate(id: string | null) {
  Object.assign(chosen, TEMPLATES.find((t) => t.id === id)?.policy)
}

const ended = computed(() => props.candidates.filter((c) => c.idleMonths >= Number(months.value)))
const due = computed(() => (enabled.value ? ended.value.filter((c) => !c.invoiced) : []))
const exempt = computed(() => (enabled.value ? ended.value.length - due.value.length : 0))

const sum = (key: 'leads' | 'messages' | 'offers' | 'galleries') => due.value.reduce((total, c) => total + c[key], 0)
const counts = computed<Record<Part, number>>(() => ({
  name: due.value.length,
  text: due.value.filter((c) => c.text !== null).length,
  messages: sum('messages'),
  offers: sum('offers'),
  galleries: sum('galleries'),
  order: due.value.length,
}))

const removedLeads = computed(() => (effective.value.order ? sum('leads') : 0))
const rate = (total: number) => (total === 0 ? 0 : Math.round((props.funnel.booked / total) * 100))
const rateBefore = computed(() => rate(props.funnel.total))
const rateAfter = computed(() => rate(props.funnel.total - removedLeads.value))

const lostCount = computed(() => due.value.filter((c) => c.outcome === 'lost').length)

const tiles = computed(() => [
  { key: 'Due', value: String(due.value.length), sub: 'due' },
  { key: 'Exempt', value: String(exempt.value), sub: 'exempt' },
  { key: 'Reports', value: removedLeads.value ? `${props.funnel.total} → ${props.funnel.total - removedLeads.value}` : String(props.funnel.total), hot: removedLeads.value > 0 },
  { key: 'Rate', value: removedLeads.value ? `${rateBefore.value} % → ${rateAfter.value} %` : `${rateBefore.value} %`, hot: removedLeads.value > 0 },
])

const verb = computed(() => (effective.value.order ? 'Delete' : effective.value.name ? 'Anonymize' : 'Clean'))
const warning = computed(() => (effective.value.order ? 'Order' : effective.value.name ? 'Name' : 'Text'))
const requests = computed(() => sum('leads'))
const removedParts = computed(() => PARTS.filter((part) => effective.value[part]))

const save = useForm({})

function patch() {
  save
    .transform(() => ({ retention_months: enabled.value ? Number(months.value) : null, policy: { ...chosen } }))
    .patch('/einstellungen/aufbewahrung', { preserveScroll: true })
}

function run() {
  form
    .transform((data) => ({ ...data, retention_months: Number(months.value), policy: { ...chosen } }))
    .post('/einstellungen/aufbewahrung/durchfuehren', {
      preserveScroll: true,
      onSuccess: () => {
        confirming.value = false
        form.reset('current_password')
      },
    })
}

function submit() {
  if (enabled.value && due.value.length > 0) {
    confirming.value = true

    return
  }

  patch()
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('settings.kicker')" :title="$t('settings.navRetention')" />

        <div class="px-6 pt-7 pb-8">
          <div class="flex items-start gap-8 max-[640px]:flex-col max-[640px]:gap-4">
            <SettingsNav active="retention" />

            <form class="max-w-[1100px] min-w-0 flex-1" @submit.prevent="submit">
              <div class="flex flex-wrap items-center gap-3">
                <Switch id="retention-on" v-model="enabled" />
                <Label for="retention-on">{{ $t('settings.retentionOn') }}</Label>
                <ChoiceGroup v-model="months" variant="segmented" :options="monthOptions" :class="enabled ? '' : 'pointer-events-none opacity-50'" />
                <span class="text-small text-ink-2">{{ $t('settings.retentionMonths') }}</span>
              </div>

              <div :class="enabled ? '' : 'pointer-events-none opacity-45'" :inert="!enabled">
                <div class="mt-6 grid grid-cols-4 gap-3 max-[900px]:grid-cols-2">
                  <div v-for="tile in tiles" :key="tile.key" class="rounded-[var(--r)] bg-paper shadow-card px-5 py-4">
                    <div class="text-small font-semibold text-ink-2">{{ $t(`settings.retention${tile.key}`) }}</div>
                    <div class="mt-1 text-heading leading-[1.1] font-semibold tabular-nums" :class="tile.hot && 'text-destructive'">{{ tile.value }}</div>
                    <div class="mt-1 text-small text-ink-2">
                      <template v-if="tile.sub === 'due'">{{ $t('settings.retentionDueSub', { lost: lostCount, declined: due.length - lostCount }) }}</template>
                      <template v-else-if="tile.sub === 'exempt'">{{ $t('settings.retentionExemptSub') }}</template>
                      <template v-else-if="!tile.hot">{{ $t('settings.retentionUnchanged') }}</template>
                      <template v-else-if="tile.key === 'Reports'">−{{ removedLeads }}</template>
                      <template v-else>+{{ rateAfter - rateBefore }} %</template>
                    </div>
                  </div>
                </div>

                <div class="mt-7 grid grid-cols-[400px_minmax(0,1fr)] items-start gap-10 max-[1100px]:grid-cols-1">
                  <div>
                    <ChoiceGroup
                      :label="$t('settings.retentionTemplate')"
                      :model-value="template"
                      :options="TEMPLATES.map((t) => ({ value: t.id, label: $t(`settings.retentionTpl${t.id}`) }))"
                      @update:model-value="pickTemplate"
                    />

                    <div class="mt-6 flex justify-between text-small font-semibold text-ink-2">
                      <span>{{ $t('settings.retentionParts') }}</span>
                      <span>{{ $t('settings.retentionAffected') }}</span>
                    </div>
                    <div
                      v-for="part in PARTS"
                      :key="part"
                      class="flex items-center gap-4 py-3 shadow-[inset_0_-1px_0_var(--rule)]"
                      :class="chosen.order && part !== 'order' && 'opacity-55'"
                      :inert="chosen.order && part !== 'order'"
                    >
                      <div class="min-w-0 flex-1">
                        <div class="text-ui font-semibold">{{ $t(`settings.retentionPart${part[0].toUpperCase()}${part.slice(1)}`) }}</div>
                        <div class="text-small text-ink-2">{{ $t(`settings.retentionPart${part[0].toUpperCase()}${part.slice(1)}Sub`) }}</div>
                      </div>
                      <span class="w-8 text-right text-ui tabular-nums">{{ counts[part] }}</span>
                      <ChoiceGroup
                        variant="segmented"
                        :model-value="effective[part] ? 'off' : 'keep'"
                        :options="[
                          { value: 'keep', label: $t('settings.retentionKeep') },
                          { value: 'off', label: part === 'name' ? $t('settings.retentionAnonymize') : $t('settings.retentionDelete') },
                        ]"
                        @update:model-value="(value) => (chosen[part] = value === 'off')"
                      />
                    </div>
                  </div>

                  <div class="min-w-0">
                    <div class="flex justify-between text-small font-semibold text-ink-2">
                      <span>{{ $t('settings.retentionDue') }}</span>
                      <span>{{ due.length }}</span>
                    </div>
                    <div class="mt-2 grid grid-cols-[150px_minmax(0,1fr)_100px_76px] gap-3 py-2 text-small font-semibold text-ink-2 shadow-[inset_0_-1px_0_var(--rule)]">
                      <span>{{ $t('settings.retentionColContact') }}</span>
                      <span>{{ $t('settings.retentionColRequest') }}</span>
                      <span>{{ $t('settings.retentionColOutcome') }}</span>
                      <span>{{ $t('settings.retentionColOrder') }}</span>
                    </div>
                    <div class="max-h-[520px] overflow-y-auto">
                      <div
                        v-for="candidate in due"
                        :key="candidate.id"
                        class="grid grid-cols-[150px_minmax(0,1fr)_100px_76px] items-center gap-3 py-2 shadow-[inset_0_-1px_0_var(--rule)]"
                      >
                        <div class="min-w-0">
                          <div class="truncate text-ui font-semibold" :class="effective.name && 'font-normal text-ink-2 italic'">
                            {{ effective.name ? '[gelöscht]' : candidate.name }}
                          </div>
                          <div class="truncate text-small text-ink-2">{{ candidate.type }}</div>
                        </div>
                        <div class="truncate text-small" :class="effective.text && 'text-ink-2'">{{ effective.text ? '—' : candidate.text }}</div>
                        <div class="text-small">
                          {{ candidate.outcomeLabel }}
                          <div class="text-small text-ink-2">{{ $t('settings.retentionIdle', { n: candidate.idleMonths }) }}</div>
                        </div>
                        <span
                          class="w-fit rounded-[var(--r-sm)] px-2.5 py-0.5 text-small font-semibold"
                          :class="effective.order ? 'bg-late-bg text-destructive' : 'bg-sunk text-ink-2'"
                        >
                          {{ effective.order ? $t('settings.retentionRemoved') : $t('settings.retentionStays') }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="mt-6">
                <Button type="submit" :disabled="save.processing">
                  {{ enabled && due.length > 0 ? $t('settings.retentionRun') : $t('settings.numberingSave') }}
                </Button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <AlertDialog v-model:open="confirming">
        <AlertDialogContent>
          <form class="contents" @submit.prevent="run">
            <AlertDialogHeader>
              <AlertDialogTitle>{{ $t(`settings.retentionTitle${verb}`, { count: requests }) }}</AlertDialogTitle>
            </AlertDialogHeader>
            <AlertDialogBody>
              <div>
                <div v-for="part in removedParts" :key="part" class="flex justify-between gap-4 py-2 text-ui shadow-[inset_0_-1px_0_var(--rule)]">
                  <span class="font-medium">{{ $t(`settings.retentionPart${part[0].toUpperCase()}${part.slice(1)}`) }}</span>
                  <span class="tabular-nums text-ink-2">
                    {{ $t(part === 'name' ? 'settings.retentionDoneAnonymized' : 'settings.retentionDoneDeleted', { count: counts[part] }) }}
                  </span>
                </div>
                <div v-if="removedLeads" class="flex justify-between gap-4 py-2 text-ui font-semibold text-destructive shadow-[inset_0_-1px_0_var(--rule)]">
                  <span>{{ $t('settings.retentionRate') }}</span>
                  <span class="tabular-nums">{{ rateBefore }} % → {{ rateAfter }} %</span>
                </div>
              </div>

              <p class="m-0 rounded-md bg-late-bg px-3.5 py-3 text-small leading-[1.45] text-destructive">
                <strong>{{ $t('settings.retentionFinal') }}</strong> {{ $t(`settings.retentionWarn${warning}`) }}
              </p>

              <Field :label="$t('settings.retentionPassword')" id="retention-password" :error="form.errors.current_password">
                <Input id="retention-password" v-model="form.current_password" type="password" autocomplete="current-password" />
              </Field>
            </AlertDialogBody>
            <AlertDialogFooter>
              <Button type="submit" tone="destructive" class="ml-0" :disabled="form.processing || form.current_password === ''">
                {{ $t('settings.retentionRun') }}
              </Button>
              <AlertDialogCancel>{{ $t('common.cancel') }}</AlertDialogCancel>
            </AlertDialogFooter>
          </form>
        </AlertDialogContent>
      </AlertDialog>
    </Page>
  </AdminLayout>
</template>
