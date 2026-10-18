<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Label } from '@/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Textarea } from '@/ui/textarea'

/*
 * P-09 (#228): three-valued, always. '' means Standard — the key is removed
 * rather than stored as a copy of today's default, so a studio changing its
 * own default is not silently left behind on this contact.
 *
 * The record is written in place and the change announced: the sheet saves
 * per switch, the create screen carries the values along to its own submit.
 */
// The ten keys rather than an index signature: the sheet hands in its whole
// useForm, processing and methods included, which Record<string, string>
// refuses — a type asking only for what these fields read and write does not.
type PortalKey =
  | 'portal_visible' | 'accept_quotes' | 'sign_contracts' | 'payment_status'
  | 'messaging_mode' | 'gallery_downloads' | 'gallery_lists' | 'gallery_comments'
  | 'offers' | 'welcome_inquiry' | 'welcome_active' | 'welcome_done'

const props = withDefaults(
  defineProps<{
    portal: Record<PortalKey, string>
    defaults: Record<string, string | boolean | null>
    group?: Group
  }>(),
  { group: 'all' },
)

const emit = defineEmits<{ change: [] }>()

const { t } = useI18n()

// Der Reiter am Kontakt stellt die Gruppen als eigene Karten nebeneinander,
// das Anlegen-Formular hat nur eine Spalte und nimmt alle.
const GROUPS = {
  all: ['portal_visible', 'accept_quotes', 'sign_contracts', 'payment_status',
    'gallery_downloads', 'gallery_lists', 'gallery_comments', 'offers'],
  permissions: ['accept_quotes', 'sign_contracts', 'payment_status', 'portal_visible'],
  gallery: ['gallery_downloads', 'gallery_lists', 'gallery_comments'],
  offers: ['offers'],
  welcome: [],
} as const

// Dieselben drei Zustände wie unter Einstellungen → Portal; wer für einen
// Kunden schreibt, überschreibt jeden einzeln.
const WELCOME_STATES = ['inquiry', 'active', 'done'] as const

type Group = keyof typeof GROUPS

type Switch = (typeof GROUPS)['all'][number]

const switches = computed<readonly Switch[]>(() => GROUPS[props.group])

function isOn(key: Switch) {
  return props.portal[key] === '' ? props.defaults[key] === true : props.portal[key] === '1'
}

function toggle(key: Switch) {
  props.portal[key] = isOn(key) ? '0' : '1'
  emit('change')
}

function reset(key: Switch) {
  props.portal[key] = ''
  emit('change')
}

// reka's SelectItem refuses an empty value, and '' is what "Standard" is
// stored as — so the inherit choice carries a name of its own on the way in
// and out.
const messagingMode = computed({
  get: () => props.portal.messaging_mode || 'standard',
  set: (value) => {
    props.portal.messaging_mode = value === 'standard' ? '' : String(value)
    emit('change')
  },
})

// The studio's own value, in the words the studio's own screen uses —
// "Standard (Nur zu einer Buchung)", not "Standard (booking)".
function defaultLabel(key: string) {
  const value = props.defaults[key]

  if (typeof value === 'boolean') {
    return value ? t('contacts.portalOn') : t('contacts.portalOff')
  }

  return key === 'messaging_mode' ? t(`portalSettings.messagingMode.${value}`) : String(value ?? '—')
}
</script>

<template>
  <!-- No state text beside a switch: "wie Vorgabe" and "abweichend" narrate
       what the switch already shows. What is left is a reset, and only where
       an override actually holds a value. -->
  <div
    v-for="key in switches"
    :key="key"
    class="flex items-center gap-3 py-[9px] shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none"
  >
    <div class="min-w-0 flex-1 text-small">
      {{ $t(`contacts.portalKey.${key}`) }}
      <a
        v-if="portal[key] !== ''"
        class="text-small tracking-[-0.01em] whitespace-nowrap text-ink-2 no-underline hover:underline"
        href="#"
        @click.prevent="reset(key)"
      >
        {{ $t('contacts.portalReset') }}
      </a>
    </div>
    <span
      class="relative h-[22px] w-9 flex-none cursor-pointer rounded-full after:absolute after:top-[3px] after:left-[3px] after:size-4 after:rounded-full after:bg-paper after:transition-transform after:duration-[160ms] after:ease-out after:content-['']"
      :class="isOn(key) ? 'bg-action after:[transform:translateX(14px)]' : 'bg-sunk-2'"
      @click="toggle(key)"
    />
  </div>

  <!-- Four options, the longest of them a sentence: beside its caption the
       select would run into it, so it stands under. -->
  <!-- The caption is the inline one, not the field caption: under the
       rail's section headings a second heading-like caption reads as a third
       section rather than as the name of the control. -->
  <div v-if="group === 'all' || group === 'permissions'" class="py-[9px] shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none">
    <Label variant="inline" for="portal-messaging">
      {{ $t('contacts.portalKey.messaging_mode') }}
    </Label>
    <Select v-model="messagingMode">
      <SelectTrigger id="portal-messaging" size="sm" class="mt-[3px] w-full">
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value="standard">
          {{ $t('contacts.portalInherit', { value: defaultLabel('messaging_mode') }) }}
        </SelectItem>
        <SelectItem value="off">{{ $t('portalSettings.messagingMode.off') }}</SelectItem>
        <SelectItem value="booking">{{ $t('portalSettings.messagingMode.booking') }}</SelectItem>
        <SelectItem value="any">{{ $t('portalSettings.messagingMode.any') }}</SelectItem>
      </SelectContent>
    </Select>
  </div>

  <div
    v-for="state in (group === 'all' || group === 'welcome' ? WELCOME_STATES : [])"
    :key="state"
    class="py-[9px] shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none"
  >
    <Label variant="inline" :for="`portal-welcome-${state}`">
      {{ $t(`portalSettings.welcomeState.${state}`) }}
    </Label>
    <Textarea
      :id="`portal-welcome-${state}`"
      v-model="portal[`welcome_${state}`]"
      class="mt-[3px]"
      rows="2"
      @blur="emit('change')"
    />
  </div>
</template>
