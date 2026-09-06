<script setup lang="ts">
import { computed } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { Button } from '@/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'

const props = defineProps<{
  user: { name: string; email: string; phone: string | null; role: string }
  pendingEmail: string | null
  mfaConfirmed: boolean
  recoveryCodesLeft: number
  digestFrequency: string | null
  events: Record<string, Mode>
  briefable: Record<string, boolean>
}>()

type Mode = 'immediate' | 'briefing' | 'digest' | 'off'

// The order the pane lists them in — the same keys StudioEvents::ALL sends,
// grouped the way somebody reads them: the booking first, the gallery last.
const EVENT_KEYS = [
  'inquiryReceived',
  'clientMessage',
  'quoteAccepted',
  'contractSigned',
  'questionnaireSubmitted',
  'installmentPaid',
  'assetCommented',
  'assetFavorited',
] as const

// Die drei Scans stehen zuerst und in einem eigenen Abschnitt: nur sie
// kennen den vierten Zustand, und ein Feld mit vier Optionen neben einem mit
// dreien liest sich sonst wie ein Fehler.
const BACKLOG_KEYS = ['tasksDue', 'leadStalled', 'installmentOverdue'] as const

const MODES = ['immediate', 'digest', 'off'] as const

function modesFor(key: string): Mode[] {
  return props.briefable[key] ? ['immediate', 'briefing', 'digest', 'off'] : [...MODES]
}

// A Select item carries a string; "kein Rhythmus" is null on the wire. Same
// sentinel pairing Settings/TaskTemplates.vue uses for its own empty option.
const NO_DIGEST = 'none'

// Same shell as /einstellungen: the pane rides in the query so a link can
// name one.
const PANES = ['konto', 'sicherheit', 'benachrichtigungen'] as const

const NAV = [
  { key: 'konto', href: '/profil', label: 'profile.navAccount' },
  { key: 'sicherheit', href: '/profil?pane=sicherheit', label: 'profile.navSecurity' },
  { key: 'benachrichtigungen', href: '/profil?pane=benachrichtigungen', label: 'profile.navNotifications' },
  // The own-absences screen already exists and is open to every role; this is
  // the way to it from here, not a second copy of it.
  { key: 'abwesenheiten', href: '/abwesenheiten', label: 'profile.navAbsences' },
]

const page = usePage()

const pane = computed(() => {
  const asked = new URL(page.url, 'http://profile').searchParams.get('pane')

  return PANES.find((known) => known === asked) ?? 'konto'
})

const accountForm = useForm({
  name: props.user.name,
  email: props.user.email,
  phone: props.user.phone ?? '',
  current_password: '',
})

// The e-mail is the login, so moving it costs the current password. Asked for
// only once the field actually differs — renaming yourself does not.
const emailMoved = computed(
  () => accountForm.email.trim().toLowerCase() !== props.user.email.toLowerCase(),
)

const passwordForm = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const eventsForm = useForm({
  digest_frequency: props.digestFrequency,
  events: { ...props.events },
})

const activeForm = computed(() => {
  if (pane.value === 'sicherheit') return passwordForm
  if (pane.value === 'benachrichtigungen') return eventsForm
  return accountForm
})

const formId = computed(() => `profile-${pane.value}-form`)

const paneTitle = computed(() => {
  if (pane.value === 'sicherheit') return 'profile.securityTitle'
  if (pane.value === 'benachrichtigungen') return 'profile.notificationsTitle'
  return 'profile.accountTitle'
})

function submitAccount() {
  accountForm.patch('/profil', {
    preserveScroll: true,
    onSuccess: () => accountForm.reset('current_password'),
  })
}

function submitPassword() {
  passwordForm.put('/profil/passwort', {
    preserveScroll: true,
    onSuccess: () => passwordForm.reset(),
  })
}

function submitEvents() {
  eventsForm.patch('/profil/benachrichtigungen', { preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="profil">
    <div data-tokens>
      <PageHead :kicker="$t('profile.kicker')" :title="$t(paneTitle)">
        <span v-if="activeForm.isDirty" class="flex-none rounded-[var(--r-sm)] bg-due-bg px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase text-due">{{ $t('settings.unsaved') }}</span>
        <Button
          class="ml-auto"
          variant="pill"
          type="submit"
          :form="formId"
          :disabled="!activeForm.isDirty || activeForm.processing"
        >
          {{ $t('common.save') }}
        </Button>
      </PageHead>

      <div class="px-8 pt-7 pb-8">
        <div class="flex items-start gap-8">
          <nav class="flex w-[196px] flex-none flex-col gap-0.5">
            <Link
              v-for="item in NAV"
              :key="item.key"
              class="flex items-center gap-2.5 rounded-[7px] px-2.5 py-[9px] text-[14px] leading-none no-underline transition-[background-color] duration-[.12s] ease-out hover:bg-muted [&_svg]:size-[17px]"
              :class="item.key === pane ? 'font-semibold text-foreground [&_svg]:opacity-100' : 'font-normal text-muted-foreground [&_svg]:opacity-75'"
              :href="item.href"
            >{{ $t(item.label) }}</Link>
          </nav>

          <div class="max-w-[620px] min-w-0 flex-1">
            <form v-if="pane === 'konto'" id="profile-konto-form" @submit.prevent="submitAccount">
              <div class="mb-6">
                <Field :label="$t('profile.name')" id="pr-name" wide :error="accountForm.errors.name">
                  <Input id="pr-name" v-model="accountForm.name" type="text" />
                </Field>
              </div>
              <div class="mb-6">
                <Field
                  :label="$t('profile.email')"
                  id="pr-mail"
                  wide
                  :hint="pendingEmail ? $t('profile.pendingEmail', { email: pendingEmail }) : undefined"
                  :error="accountForm.errors.email"
                >
                  <Input id="pr-mail" v-model="accountForm.email" type="email" />
                </Field>
              </div>
              <div class="mb-6">
                <Field :label="$t('profile.phone')" id="pr-telefon" class="max-w-[300px]" :error="accountForm.errors.phone">
                  <Input id="pr-telefon" v-model="accountForm.phone" type="tel" />
                </Field>
              </div>
              <div v-if="emailMoved" class="mb-6">
                <Field
                  :label="$t('profile.currentPassword')"
                  id="pr-passwort"
                  class="max-w-[300px]"
                  :error="accountForm.errors.current_password"
                >
                  <Input id="pr-passwort" v-model="accountForm.current_password" type="password" autocomplete="current-password" />
                </Field>
              </div>
              <div class="mb-6">
                <Field :label="$t('profile.role')" id="pr-rolle" class="max-w-[300px]">
                  <Input id="pr-rolle" :model-value="user.role" type="text" disabled />
                </Field>
              </div>
            </form>

            <form v-else-if="pane === 'sicherheit'" id="profile-sicherheit-form" @submit.prevent="submitPassword">
              <div class="mt-8 first:mt-0">
                <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('profile.passwordSection') }}</div>
                <div class="mb-6">
                  <Field
                    :label="$t('profile.currentPassword')"
                    id="pw-alt"
                    class="max-w-[300px]"
                    :error="passwordForm.errors.current_password"
                  >
                    <Input id="pw-alt" v-model="passwordForm.current_password" type="password" autocomplete="current-password" />
                  </Field>
                </div>
                <div class="mb-6">
                  <div class="grid grid-cols-2 gap-4">
                    <Field :label="$t('profile.newPassword')" id="pw-neu" :error="passwordForm.errors.password">
                      <Input id="pw-neu" v-model="passwordForm.password" type="password" autocomplete="new-password" />
                    </Field>
                    <Field :label="$t('profile.repeatPassword')" id="pw-neu2">
                      <Input id="pw-neu2" v-model="passwordForm.password_confirmation" type="password" autocomplete="new-password" />
                    </Field>
                  </div>
                </div>
              </div>

              <div class="mt-8">
                <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('profile.mfaSection') }}</div>
                <div class="flex items-center gap-3 text-[13px] leading-none">
                  <span>{{ mfaConfirmed ? $t('profile.mfaOn') : $t('profile.mfaOff') }}</span>
                  <Button v-if="!mfaConfirmed" as-child variant="ghost" type="button">
                    <Link href="/auth/mfa/enroll">{{ $t('profile.mfaEnroll') }}</Link>
                  </Button>
                </div>
                <div v-if="mfaConfirmed" class="mt-4 flex items-center gap-3 text-[13px] leading-none">
                  <span>{{ $t('profile.recoveryCodesLeft', { count: recoveryCodesLeft }) }}</span>
                  <!-- Its own POST, not part of the password form: it hands back
                       eight codes on the screen that shows them once. -->
                  <Button variant="ghost" type="button" @click="router.post('/profil/wiederherstellungscodes')">
                    {{ $t('profile.regenerate') }}
                  </Button>
                </div>
              </div>
            </form>

            <form v-else id="profile-benachrichtigungen-form" @submit.prevent="submitEvents">
              <div class="mt-8 first:mt-0">
                <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('profile.backlogSection') }}</div>
                <div class="mb-6">
                  <div class="grid grid-cols-2 gap-4">
                    <Field
                      v-for="key in BACKLOG_KEYS"
                      :key="key"
                      :label="$t(`profile.event.${key}`)"
                      :id="`ev-${key}`"
                    >
                      <Select
                        :model-value="eventsForm.events[key]"
                        @update:model-value="(value) => (eventsForm.events[key] = String(value) as Mode)"
                      >
                        <SelectTrigger :id="`ev-${key}`">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem v-for="mode in modesFor(key)" :key="mode" :value="mode">{{ $t(`profile.mode.${mode}`) }}</SelectItem>
                        </SelectContent>
                      </Select>
                    </Field>
                  </div>
                </div>
              </div>

              <div class="mt-8 first:mt-0">
                <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('profile.eventsSection') }}</div>
                <div class="mb-6">
                  <div class="grid grid-cols-2 gap-4">
                    <Field
                      v-for="key in EVENT_KEYS"
                      :key="key"
                      :label="$t(`profile.event.${key}`)"
                      :id="`ev-${key}`"
                    >
                      <Select
                        :model-value="eventsForm.events[key]"
                        @update:model-value="(value) => (eventsForm.events[key] = String(value) as Mode)"
                      >
                        <SelectTrigger :id="`ev-${key}`">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem v-for="mode in modesFor(key)" :key="mode" :value="mode">{{ $t(`profile.mode.${mode}`) }}</SelectItem>
                        </SelectContent>
                      </Select>
                    </Field>
                  </div>
                </div>
              </div>

              <div class="mt-8">
                <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('profile.digestSection') }}</div>
                <Field
                  :label="$t('profile.digestFrequency')"
                  id="digest-rhythmus"
                  class="max-w-[300px]"
                  :hint="$t('profile.digestHint')"
                >
                  <Select
                    :model-value="eventsForm.digest_frequency ?? NO_DIGEST"
                    @update:model-value="(value) => (eventsForm.digest_frequency = value === NO_DIGEST ? null : String(value))"
                  >
                    <SelectTrigger id="digest-rhythmus">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem :value="NO_DIGEST">{{ $t('profile.digestOff') }}</SelectItem>
                      <SelectItem value="daily">{{ $t('profile.digestDaily') }}</SelectItem>
                      <SelectItem value="weekly">{{ $t('profile.digestWeekly') }}</SelectItem>
                      <SelectItem value="monthly">{{ $t('profile.digestMonthly') }}</SelectItem>
                    </SelectContent>
                  </Select>
                </Field>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
