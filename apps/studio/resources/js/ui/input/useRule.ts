import type { Ref } from 'vue'
import { watchEffect } from 'vue'
import { useI18n } from 'vue-i18n'
import { type Rule, ruleValid } from '@/lib/rules'

/** Meldet die Regel als Browser-Gültigkeit, damit Field sie wie jede andere zeigt. */
export function useRule(el: Ref<HTMLInputElement | null>, rule: () => Rule | undefined, value: () => unknown) {
  const { t } = useI18n()

  watchEffect(() => {
    const active = rule()

    el.value?.setCustomValidity(active && !ruleValid(active, value()) ? t(`common.invalid.${active}`) : '')
  })
}
