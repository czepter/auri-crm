<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Label } from '@/ui/label'

const props = defineProps<{ ranges: App.Settings.Data.NumberRangeData[] }>()

// One form per range, keyed by its id — three independent drafts rather than
// one save button that would need to reason about which range changed.
const forms = Object.fromEntries(
  props.ranges.map((range) => [
    range.id,
    useForm({
      prefix: range.prefix,
      reset_rule: range.resetRule,
      starting_counter: range.startingCounter,
    }),
  ]),
)

function save(rangeId: string) {
  forms[rangeId].patch(`/einstellungen/nummernkreise/${rangeId}`, { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('settings.kicker')" :title="$t('settings.numberingTitle')" />

        <div class="px-6 pt-7 pb-8">
          <div class="flex items-start gap-8 max-[640px]:flex-col max-[640px]:gap-4">
            <SettingsNav active="numbering" />

            <div class="max-w-[620px] min-w-0 flex-1">
              <div v-for="range in ranges" :key="range.id" class="mt-8 first-of-type:mt-6">
                <div class="mb-2.5 text-small leading-none font-semibold text-faint">{{ range.documentTypeLabel }}</div>

                <form :id="`number-range-form-${range.id}`" @submit.prevent="save(range.id)">
                  <div class="mb-6 grid grid-cols-[repeat(2,minmax(0,1fr))] gap-x-6 gap-y-4">
                    <div class="flex min-w-0 flex-col gap-1.5">
                      <Label :for="`prefix-${range.id}`">{{ $t('settings.numberingPrefix') }}</Label>
                      <Input
                        :id="`prefix-${range.id}`"
                        v-model="forms[range.id].prefix"
                        type="text"
                        maxlength="8" />
                      <p v-if="forms[range.id].errors.prefix" class="text-small leading-[1.45] text-destructive">{{ forms[range.id].errors.prefix }}</p>
                    </div>

                    <div class="flex min-w-0 flex-col gap-1.5">
                      <Label :for="`reset-rule-${range.id}`">{{ $t('settings.numberingResetRule') }}</Label>
                      <Select
                        :model-value="forms[range.id].reset_rule"
                        @update:model-value="(value) => (forms[range.id].reset_rule = String(value))"
                      >
                        <SelectTrigger :id="`reset-rule-${range.id}`">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="yearly">{{ $t('settings.numberingResetRuleYearly') }}</SelectItem>
                          <SelectItem value="continuous">{{ $t('settings.numberingResetRuleContinuous') }}</SelectItem>
                        </SelectContent>
                      </Select>
                      <p v-if="forms[range.id].errors.reset_rule" class="text-small leading-[1.45] text-destructive">{{ forms[range.id].errors.reset_rule }}</p>
                    </div>

                    <div class="col-span-full flex min-w-0 flex-col gap-1.5">
                      <p class="text-small leading-[1.45] font-normal text-faint">{{ $t('settings.numberingChangeWarning') }}</p>
                    </div>

                    <div class="flex min-w-0 flex-col gap-1.5">
                      <Label :for="`starting-counter-${range.id}`">{{ $t('settings.numberingStartingCounter') }}</Label>
                      <Input
                        :id="`starting-counter-${range.id}`"
                        v-model.number="forms[range.id].starting_counter" class="tabular-nums"
                        type="number"
                        min="1"
                        :disabled="!range.canSetStartingCounter" />
                      <p v-if="!range.canSetStartingCounter" class="text-small leading-[1.45] font-normal text-faint">{{ $t('settings.numberingLockedHint') }}</p>
                      <p v-if="forms[range.id].errors.starting_counter" class="text-small leading-[1.45] text-destructive">
                        {{ forms[range.id].errors.starting_counter }}
                      </p>
                    </div>

                    <div class="flex min-w-0 flex-col gap-1.5">
                      <Label>{{ $t('settings.numberingPreview') }}</Label>
                      <Input
                        :model-value="range.nextNumberPreview"
                        class="pointer-events-none bg-muted tabular-nums"
                        readonly
                      />
                    </div>
                  </div>

                  <div
                    v-if="forms[range.id].isDirty"
                    class="mt-6 flex items-center gap-4 [&_[data-slot=button][data-variant=default]]:ml-0"
                  >
                    <Button type="submit" :disabled="forms[range.id].processing">
                      <svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
                      {{ $t('settings.numberingSave') }}
                    </Button>
                    <span class="chip now">{{ $t('settings.unsaved') }}</span>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
