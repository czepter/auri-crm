<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ContactQueue from '@/admin/components/ContactQueue.vue'
import Page from '@/ui/page/Page.vue'
import Rail from '@/ui/page/Rail.vue'
import RailSection from '@/ui/page/RailSection.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Textarea } from '@/ui/textarea'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/ui/alert-dialog'
import { formatEurosRounded } from '@/admin/lib/money'

const { t } = useI18n()

const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''

const props = defineProps<{
  contacts: App.Crm.Data.ContactRowData[]
  contact: App.Crm.Data.ContactSuggestionData
  billingAddress: { street: string | null; postcode: string | null; city: string | null }
  correspondence: App.Engagement.Data.MessageData[]
  projects: { id: string; name: string; role: string; date: string | null; value: number | null; booked: boolean }[]
  galleries: {
    id: string
    title: string | null
    expiresAt: string | null
    assetCount: number
    openedOn: string | null
  }[]
  connected: { id: string; name: string; role: string | null }[]
  portalAccessRevokedAt: string | null
  canRevokePortalAccess: boolean
  dsgvoErasedAt: string | null
  canManageDsgvo: boolean
  dsgvoRequests: { id: string; kind: string; requestedAt: string }[]
  portalDefaults: Record<string, string | boolean | null>
  portalOverrides: Record<string, string | boolean>
  canPreviewPortal: boolean
}>()

const form = useForm({
  name: props.contact.name,
  email: props.contact.email ?? '',
  phone: props.contact.phone ?? '',
  street: props.billingAddress.street ?? '',
  postcode: props.billingAddress.postcode ?? '',
  city: props.billingAddress.city ?? '',
})

// The confirm is worded to the one thing this button changes: every project
// this contact can reach in the portal, not one of them — the opposite scope
// from a project's own "hide from portal" control. It lives in the template
// now as an AlertDialog, which is the same guard the native confirm() was,
// minus a browser chrome nobody could style or translate.
function revokePortalAccess() {
  router.post(`/kontakte/${props.contact.id}/portal-zugang/widerruf`)
}

// Irreversible, and named as such — the same worded-to-the-scope guard
// revokePortalAccess uses above, and the only one that takes the accent
// button, because it is the only one that cannot be undone.
// P-09 (#228): three-valued, always. '' means Standard — the key is removed
// rather than stored as a copy of today's default, so a studio changing its
// own default is not silently left behind on this contact.
const SWITCHES = ['portal_visible', 'accept_quotes', 'sign_contracts', 'payment_status',
  'gallery_downloads', 'gallery_lists', 'gallery_comments'] as const

const portal = useForm({
  // On means every booking captured for this contact is born visible; the
  // switch on a single project still overrides it afterwards.
  portal_visible: asChoice(props.portalOverrides.portal_visible),
  accept_quotes: asChoice(props.portalOverrides.accept_quotes),
  sign_contracts: asChoice(props.portalOverrides.sign_contracts),
  payment_status: asChoice(props.portalOverrides.payment_status),
  messaging_mode: (props.portalOverrides.messaging_mode as string) ?? '',
  gallery_downloads: asChoice(props.portalOverrides.gallery_downloads),
  gallery_lists: asChoice(props.portalOverrides.gallery_lists),
  gallery_comments: asChoice(props.portalOverrides.gallery_comments),
  welcome: (props.portalOverrides.welcome as string) ?? '',
})

function asChoice(value: string | boolean | undefined) {
  return value === undefined ? '' : value ? '1' : '0'
}

// reka's SelectItem refuses an empty value, and '' is what "Standard" is
// stored as — so the inherit choice carries a name of its own on the way in
// and out.
const messagingMode = computed({
  get: () => portal.messaging_mode || 'standard',
  set: (value) => {
    portal.messaging_mode = value === 'standard' ? '' : String(value)
    savePortal()
  },
})

function savePortal() {
  portal
    .transform((data) => Object.fromEntries(
      Object.entries(data).map(([key, value]) => [
        key,
        value === '' ? null : SWITCHES.includes(key as never) ? value === '1' : value,
      ]),
    ))
    .patch(`/kontakte/${props.contact.id}/portal`, { preserveScroll: true })
}

// The studio's own value, in the words the studio's own screen uses —
// "Standard (Nur zu einer Buchung)", not "Standard (booking)".
function defaultLabel(key: string) {
  const value = props.portalDefaults[key]

  if (typeof value === 'boolean') {
    return value ? t('contacts.portalOn') : t('contacts.portalOff')
  }

  return key === 'messaging_mode' ? t(`portalSettings.messagingMode.${value}`) : String(value ?? '—')
}


function day(iso: string | null) {
  return iso ? new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' }) : '—'
}

// C-18 (#254): no "Bearbeiten" — every row is operable where it stands. One
// row at a time, because two open fields on a sheet read as a form and a
// form is the thing this screen is not.
const editing = ref<string | null>(null)

function saveField() {
  editing.value = null
  form.patch(`/kontakte/${props.contact.id}`, { preserveScroll: true })
}

// Focus leaving the row is what closes it, not blur on a single field — the
// address holds three, and tabbing from Strasse to PLZ must not unmount the
// field being tabbed into.
function saveOnLeave(event: FocusEvent) {
  const row = event.currentTarget as HTMLElement

  if (!row.contains(event.relatedTarget as Node | null)) {
    saveField()
  }
}

// The switch shows what applies; whether that came from the studio's default
// or from this contact is what "Zurücksetzen" answers, and only where an
// override actually holds a value.
type Switch = (typeof SWITCHES)[number]

function isOn(key: Switch) {
  return portal[key] === '' ? props.portalDefaults[key] === true : portal[key] === '1'
}

function toggle(key: Switch) {
  portal[key] = isOn(key) ? '0' : '1'
  savePortal()
}

function reset(key: Switch) {
  portal[key] = ''
  savePortal()
}

function eraseDsgvoData() {
  router.post(`/kontakte/${props.contact.id}/dsgvo/loeschen`)
}
</script>

<template>
  <AdminLayout active="kontakte">
    <!-- C-18 (#254): built like a contact card, because a contact is opened
         and read, not edited. The sheet holds what the person is; the trace
         holds what the studio has switched on for them. -->
    <Page>
      <template #left>
        <ContactQueue :contacts="contacts" :active-id="contact.id" />
      </template>

      <main data-tokens class="min-w-0 flex-1 overflow-y-auto px-8 pt-7 pb-8">
        <div class="max-w-[760px]">
          <!-- The head carries the name and nothing else. No avatar: initials
               in a circle repeat the name beside them, and a picture has
               neither a column nor a store. No subtitle either — role and
               standing are further down, where they can be acted on. -->
          <div class="flex cursor-text items-center gap-5" @click="editing = 'name'" @focusout="saveOnLeave">
            <Input
              v-if="editing === 'name'"
              v-model="form.name"
              variant="filled"
              class="text-[28px] leading-[1.08] font-semibold tracking-[-0.032em]"
              type="text"
              autofocus
              @keyup.enter="saveField"
            />
            <h1 v-else class="m-0 text-[28px] leading-[1.08] font-semibold tracking-[-0.032em]">{{ contact.name }}</h1>
          </div>

          <div class="mt-5 mb-7 flex gap-2">
            <a
              v-if="contact.phone"
              class="inline-flex items-center gap-[7px] rounded-full bg-action px-[15px] py-2 text-[13px] text-action-foreground no-underline"
              :href="`tel:${contact.phone}`"
            >
              {{ $t('contacts.call') }}
            </a>
            <a
              v-if="contact.email"
              class="inline-flex items-center gap-[7px] rounded-full bg-sunk px-[15px] py-2 text-[13px] text-foreground no-underline hover:bg-sunk-2"
              :href="`mailto:${contact.email}`"
            >
              {{ $t('contacts.email') }}
            </a>
          </div>

          <section class="mb-7">
            <h2 class="mt-0 mb-3 text-[10.5px] leading-none font-semibold tracking-[0.07em] uppercase text-ink-2">
              {{ $t('contacts.reach') }}
            </h2>

            <div
              class="flex cursor-text items-center gap-4 py-2.5 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none"
              @click="editing = 'phone'"
              @focusout="saveOnLeave"
            >
              <div>
                <div v-if="editing === 'phone'">
                  <Input v-model="form.phone" variant="filled" size="sm" type="tel" autofocus @keyup.enter="saveField" />
                </div>
                <div v-else class="text-[15px] leading-none font-semibold tracking-[-0.012em] tabular-nums">{{ contact.phone ?? '—' }}</div>
                <div class="mt-px text-[12px] text-ink-2">{{ $t('contacts.phone') }}</div>
              </div>
            </div>

            <div
              class="flex cursor-text items-center gap-4 py-2.5 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none"
              @click="editing = 'email'"
              @focusout="saveOnLeave"
            >
              <div>
                <div v-if="editing === 'email'">
                  <Input v-model="form.email" variant="filled" size="sm" type="email" autofocus @keyup.enter="saveField" />
                </div>
                <div v-else class="text-[15px] leading-none font-semibold tracking-[-0.012em] tabular-nums">{{ contact.email ?? '—' }}</div>
                <div class="mt-px text-[12px] text-ink-2">{{ $t('contacts.email') }}</div>
              </div>
            </div>

            <!-- M-06: the address every invoice carries and this screen never
                 showed. -->
            <div
              class="flex cursor-text items-center gap-4 py-2.5 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none"
              @click="editing = 'address'"
              @focusout="saveOnLeave"
            >
              <div class="w-full">
                <div v-if="editing === 'address'" class="flex gap-1.5">
                  <Input v-model="form.street" variant="filled" size="sm" type="text" autocomplete="street-address" autofocus @keyup.enter="saveField" />
                  <Input v-model="form.postcode" variant="filled" size="sm" class="w-[8ch]" type="text" autocomplete="postal-code" @keyup.enter="saveField" />
                  <Input v-model="form.city" variant="filled" size="sm" type="text" autocomplete="address-level2" @keyup.enter="saveField" />
                </div>
                <div v-else class="text-[15px] leading-none font-semibold tracking-[-0.012em] tabular-nums">
                  {{ [billingAddress.street, [billingAddress.postcode, billingAddress.city].filter(Boolean).join(' ')].filter(Boolean).join(', ') || '—' }}
                </div>
                <div class="mt-px text-[12px] text-ink-2">{{ $t('contacts.address') }}</div>
              </div>
            </div>
          </section>

          <!-- Two sections, never one list: a project is an order with money
               and dates, a gallery a delivery with an expiry. -->
          <section v-if="projects.length" class="mb-7">
            <h2 class="mt-0 mb-3 text-[10.5px] leading-none font-semibold tracking-[0.07em] uppercase text-ink-2">
              {{ $t('contacts.projects') }}
            </h2>
            <div
              v-for="project in projects"
              :key="project.id"
              class="flex items-center gap-4 py-2.5 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none"
            >
              <div>
                <div class="text-[15px] leading-none font-semibold tracking-[-0.012em] tabular-nums">
                  <Link class="no-underline hover:underline" :href="`/auftraege/${project.id}`">{{ project.name }}</Link>
                </div>
                <div class="mt-px text-[12px] text-ink-2">
                  {{ $t(`contacts.role.${project.role}`) }}
                  <template v-if="project.date"> · {{ day(project.date) }}</template>
                </div>
              </div>
              <span v-if="project.value" class="ml-auto text-[12.5px] text-ink-2">
                {{ formatEurosRounded(project.value) }}
              </span>
            </div>
          </section>

          <section v-if="galleries.length" class="mb-7">
            <h2 class="mt-0 mb-3 text-[10.5px] leading-none font-semibold tracking-[0.07em] uppercase text-ink-2">
              {{ $t('contacts.galleries') }}
            </h2>
            <div
              v-for="gallery in galleries"
              :key="gallery.id"
              class="flex items-center gap-4 py-2.5 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none"
            >
              <div>
                <div class="text-[15px] leading-none font-semibold tracking-[-0.012em] tabular-nums">
                  <Link class="no-underline hover:underline" :href="`/galerien/${gallery.id}`">{{ gallery.title ?? '—' }}</Link>
                </div>
                <div class="mt-px text-[12px] text-ink-2">
                  {{ $t('contacts.assetCount', gallery.assetCount) }}
                  ·
                  {{ gallery.openedOn ? $t('contacts.openedOn', { date: day(gallery.openedOn) }) : $t('contacts.notOpened') }}
                </div>
              </div>
              <span v-if="gallery.expiresAt" class="ml-auto text-[12.5px] text-ink-2">
                {{ $t('contacts.expiresOn', { date: day(gallery.expiresAt) }) }}
              </span>
            </div>
          </section>

          <!-- E-11: what the studio has actually said to this person, across
               every project. A thread only ever knows its own project, so this
               is the one screen that can answer the question at all. -->
          <section v-if="correspondence.length" class="mb-7">
            <h2 class="mt-0 mb-3 text-[10.5px] leading-none font-semibold tracking-[0.07em] uppercase text-ink-2">
              {{ $t('contacts.correspondence') }}
            </h2>
            <div
              v-for="message in correspondence"
              :key="message.id"
              class="py-3 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none"
            >
              <div class="flex items-baseline gap-2.25">
                <span class="text-[13px] font-semibold tracking-[-0.012em]">{{ message.author }}</span>
                <span class="ml-auto text-[11.5px] tabular-nums text-ink-2">
                  {{ day(message.sentAt) }}
                  <template v-if="message.projectName">
                    ·
                    <Link class="no-underline hover:underline" :href="`/auftraege/${message.projectId}/nachrichten`">{{ message.projectName }}</Link>
                  </template>
                  <template v-if="message.deliveryState === 'failed'"> · {{ $t('contacts.deliveryFailed') }}</template>
                </span>
              </div>
              <div class="mt-[5px] max-w-[68ch] text-[13.5px] leading-[1.55]">{{ message.body }}</div>
            </div>
          </section>
        </div>
      </main>

      <template #right>
        <Rail data-tokens class="w-88">
          <RailSection :title="$t('contacts.portalSection')">
            <!-- No state text beside a switch: "wie Vorgabe" and "abweichend"
                 narrate what the switch already shows. What is left is a reset,
                 and only where an override actually holds a value. -->
            <div
              v-for="key in SWITCHES"
              :key="key"
              class="flex items-center gap-3 py-[9px] shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none"
            >
              <div class="min-w-0 flex-1 text-[13px]">
                {{ $t(`contacts.portalKey.${key}`) }}
                <a
                  v-if="portal[key] !== ''"
                  class="text-[12px] tracking-[-0.01em] whitespace-nowrap text-ink-2 no-underline hover:underline"
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

            <!-- Four options, the longest of them a sentence: beside its
                 caption the select would run into it, so it stands under. -->
            <!-- The caption is the inline one, not the uppercase field
                 caption: the rail already spends uppercase on its section
                 headings, and a second one under them reads as a third
                 section rather than as the name of the control. -->
            <div class="py-[9px] shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none">
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

            <div class="py-[9px] shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none">
              <Label variant="inline" for="portal-welcome">
                {{ $t('contacts.portalKey.welcome') }}
              </Label>
              <Textarea
                id="portal-welcome"
                v-model="portal.welcome"
                class="mt-[3px]"
                rows="2"
                @blur="savePortal"
              />
            </div>

            <!-- The access belongs to the portal, under the switches: validity,
                 a fresh link, withdrawal. -->
            <div class="flex items-center gap-3 py-[9px] shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none">
              <div class="min-w-0 flex-1 text-[13px]">
                {{ $t('contacts.portalAccess') }}
                <div class="mt-[3px] text-[12px] text-ink-2">
                  {{ portalAccessRevokedAt
                    ? $t('contacts.portalAccessRevoked', { date: day(portalAccessRevokedAt) })
                    : $t('contacts.portalAccessActive') }}
                </div>
              </div>
            </div>

            <!-- A plain form, not router.post(): the preview belongs in a
                 second tab, and target only works on a real navigation.
                 Without X-Inertia the controller's Inertia::location is a
                 302, which the new tab follows to the portal. -->
            <form
              v-if="canPreviewPortal"
              :action="`/kontakte/${contact.id}/portal-vorschau`"
              method="post"
              target="_blank"
            >
              <input type="hidden" name="_token" :value="csrf">
              <button
                class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border-0 bg-sunk-2 px-3.5 py-1.5 text-[12.5px] font-medium tracking-[-0.01em] whitespace-nowrap text-foreground transition-[filter,background] duration-[120ms] ease-out hover:bg-[#E9E9EC]"
                type="submit"
              >
                {{ $t('contacts.portalPreview') }}
              </button>
            </form>

            <AlertDialog v-if="canRevokePortalAccess && !portalAccessRevokedAt">
              <AlertDialogTrigger as-child>
                <button
                  class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border-0 bg-sunk-2 px-3.5 py-1.5 text-[12.5px] font-medium tracking-[-0.01em] whitespace-nowrap text-foreground transition-[filter,background] duration-[120ms] ease-out hover:bg-[#E9E9EC]"
                  type="button"
                >
                  {{ $t('contacts.revokePortalAccess') }}
                </button>
              </AlertDialogTrigger>
              <AlertDialogContent>
                <AlertDialogHeader>
                  <AlertDialogTitle>
                    {{ $t('contacts.revokePortalAccessConfirm', { name: contact.name }) }}
                  </AlertDialogTitle>
                </AlertDialogHeader>
                <AlertDialogFooter>
                  <AlertDialogAction @click="revokePortalAccess">{{ $t('contacts.revokePortalAccess') }}</AlertDialogAction>
                  <AlertDialogCancel>{{ $t('common.cancel') }}</AlertDialogCancel>
                </AlertDialogFooter>
              </AlertDialogContent>
            </AlertDialog>

          </RailSection>

          <RailSection v-if="connected.length" :title="$t('contacts.connected')">
            <div
              v-for="person in connected"
              :key="person.id"
              class="py-2 shadow-[inset_0_1px_0_#ECECEE] first:shadow-none"
            >
              <Link class="text-[12.5px] font-semibold tracking-[-0.012em]" :href="`/kontakte/${person.id}`">
                {{ person.name }}
              </Link>
              <div v-if="person.role" class="mt-px text-[11.5px] text-ink-2">
                {{ $t(`contacts.role.${person.role}`) }}
              </div>
            </div>
          </RailSection>

          <RailSection v-if="canManageDsgvo" :title="$t('contacts.dsgvo')">
            <!-- P-05 (#224): what the client asked for from the portal, beside
                 the two actions that answer it. -->
            <div
              v-for="request in dsgvoRequests"
              :key="request.id"
              class="py-2 shadow-[inset_0_1px_0_#ECECEE] first:shadow-none"
            >
              <div class="mt-px text-[11.5px] text-action">
                {{ $t(`contacts.dsgvoRequested.${request.kind}`, { date: day(request.requestedAt) }) }}
              </div>
            </div>

            <p v-if="dsgvoErasedAt" class="mt-px text-[11.5px] text-ink-2">
              {{ $t('contacts.dsgvoErased', { date: day(dsgvoErasedAt) }) }}
            </p>
            <template v-else>
              <a
                class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border-0 bg-sunk-2 px-3.5 py-1.5 text-[12.5px] font-medium tracking-[-0.01em] whitespace-nowrap text-foreground no-underline transition-[filter,background] duration-[120ms] ease-out hover:bg-[#E9E9EC]"
                :href="`/kontakte/${contact.id}/dsgvo/export`"
              >
                {{ $t('contacts.dsgvoExport') }}
              </a>

              <AlertDialog>
                <AlertDialogTrigger as-child>
                  <button
                    class="ml-2 inline-flex cursor-pointer items-center gap-1.5 rounded-full border-0 bg-sunk-2 px-3.5 py-1.5 text-[12.5px] font-medium tracking-[-0.01em] whitespace-nowrap text-foreground transition-[filter,background] duration-[120ms] ease-out hover:bg-[#E9E9EC]"
                    type="button"
                  >
                    {{ $t('contacts.dsgvoErase') }}
                  </button>
                </AlertDialogTrigger>
                <AlertDialogContent>
                  <AlertDialogHeader>
                    <AlertDialogTitle>{{ $t('contacts.dsgvoEraseConfirm', { name: contact.name }) }}</AlertDialogTitle>
                  </AlertDialogHeader>
                  <AlertDialogFooter>
                    <!-- the accent fill is reserved for the one action here
                         that cannot be taken back -->
                    <AlertDialogAction tone="destructive" @click="eraseDsgvoData">
                      {{ $t('contacts.dsgvoErase') }}
                    </AlertDialogAction>
                    <AlertDialogCancel>{{ $t('common.cancel') }}</AlertDialogCancel>
                  </AlertDialogFooter>
                </AlertDialogContent>
              </AlertDialog>
            </template>
          </RailSection>
        </Rail>
      </template>
    </Page>
  </AdminLayout>
</template>
