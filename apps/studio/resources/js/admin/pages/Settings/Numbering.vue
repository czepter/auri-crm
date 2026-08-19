<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
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
    <div class="hrow">
      <div>
        <div class="kick">{{ $t('settings.kicker') }}</div>
        <h1>{{ $t('settings.numberingTitle') }}</h1>
      </div>
    </div>

    <div style="display: flex; gap: var(--s6); margin-top: var(--s5); align-items: flex-start">
      <SettingsNav active="numbering" />

      <div style="flex: 1; min-width: 0; max-width: 620px">
        <div v-for="range in ranges" :key="range.id" class="dsec">
          <div class="dlab">{{ range.documentTypeLabel }}</div>

          <form :id="`number-range-form-${range.id}`" @submit.prevent="save(range.id)">
            <div class="fgrid grp">
              <div class="fld">
                <Label :for="`prefix-${range.id}`">{{ $t('settings.numberingPrefix') }}</Label>
                <Input
                  :id="`prefix-${range.id}`"
                  v-model="forms[range.id].prefix"
                  type="text"
                  maxlength="8" />
                <p v-if="forms[range.id].errors.prefix" class="err">{{ forms[range.id].errors.prefix }}</p>
              </div>

              <div class="fld">
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
                <p v-if="forms[range.id].errors.reset_rule" class="err">{{ forms[range.id].errors.reset_rule }}</p>
              </div>

              <div class="fld wide">
                <p class="hint">{{ $t('settings.numberingChangeWarning') }}</p>
              </div>

              <div class="fld">
                <Label :for="`starting-counter-${range.id}`">{{ $t('settings.numberingStartingCounter') }}</Label>
                <Input
                  :id="`starting-counter-${range.id}`"
                  v-model.number="forms[range.id].starting_counter" class="tabular-nums"
                  type="number"
                  min="1"
                  :disabled="!range.canSetStartingCounter" />
                <p v-if="!range.canSetStartingCounter" class="hint">{{ $t('settings.numberingLockedHint') }}</p>
                <p v-if="forms[range.id].errors.starting_counter" class="err">
                  {{ forms[range.id].errors.starting_counter }}
                </p>
              </div>

              <div class="fld">
                <Label>{{ $t('settings.numberingPreview') }}</Label>
                <Input
                  :model-value="range.nextNumberPreview"
                  class="pointer-events-none bg-muted tabular-nums"
                  readonly
                />
              </div>
            </div>

            <div class="formfoot">
              <Button
                type="submit"
                :disabled="!forms[range.id].isDirty || forms[range.id].processing"
                :style="!forms[range.id].isDirty ? 'opacity:.4' : undefined"
              >
                <svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
                {{ $t('settings.numberingSave') }}
              </Button>
              <span v-if="forms[range.id].isDirty" class="chip now">{{ $t('settings.unsaved') }}</span>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
