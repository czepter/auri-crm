<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ContactQueue from '@/admin/components/ContactQueue.vue'
import ContactPortalFields from '@/admin/components/ContactPortalFields.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import Panel from '@/admin/components/Panel.vue'
import Metric from '@/admin/components/Metric.vue'
import { Button } from '@/ui/button'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Textarea } from '@/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/ui/alert-dialog'
import { formatEurosRounded } from '@/lib/money'
import { useI18n } from 'vue-i18n'
import { MAX_DOTS, type Tone } from '@/admin/lib/metricDots'
import { useLiveProps } from '@/admin/lib/liveReload'

const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''

/*
 * Vier Reiter statt eines langen Blatts. Der Zustand liegt hier und nicht in
 * der URL: alle vier lesen aus derselben Antwort, ein Wechsel ist keine neue
 * Frage an den Server.
 */
/*
 * Der einzelne Vorschlag. Das Regal am Paket sieht jeder Bestandskunde
 * gleich; hier steht, was jemand über diese eine Kundenbeziehung geschrieben
 * hat. Ein Paket höchstens einmal — der Server macht daraus ein
 * updateOrCreate, das Formular trägt deshalb auch das Ändern.
 */
const suggestion = useForm({ package: '', note: '' })

function editSuggestion(row: { package: string; note: string | null }) {
  suggestion.package = row.package
  suggestion.note = row.note ?? ''
}

function saveSuggestion() {
  suggestion.post(`/kontakte/${props.contact.id}/leistungen`, {
    preserveScroll: true,
    onSuccess: () => suggestion.reset(),
  })
}

function removeSuggestion(id: string) {
  router.delete(`/kontakte/${props.contact.id}/leistungen/${id}`, { preserveScroll: true })
}

const TABS = ['overview', 'bookings', 'correspondence', 'portal'] as const

const tab = ref<(typeof TABS)[number]>('overview')

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
  canSendPortalAccess: boolean
  dsgvoErasedAt: string | null
  canManageDsgvo: boolean
  dsgvoRequests: { id: string; kind: string; requestedAt: string }[]
  portalDefaults: Record<string, string | boolean | null>
  portalOverrides: Record<string, string | boolean>
  canPreviewPortal: boolean
  suggestions: {
    id: string
    package: string
    name: string
    note: string | null
    author: string | null
    suggestedOn: string | null
    live: boolean
  }[]
  offerablePackages: { id: string; name: string; price: number | null; priceMode: string; requiresDate: boolean }[]
  portalLogins: { count: number; firstAt: string | null; lastAt: string | null }
}>()
useLiveProps(['contacts', 'contact', 'billingAddress', 'correspondence', 'projects', 'galleries', 'connected', 'suggestions', 'offerablePackages', 'portalLogins', 'portalAccessRevokedAt', 'canRevokePortalAccess', 'canSendPortalAccess', 'dsgvoErasedAt', 'canManageDsgvo', 'portalDefaults', 'portalOverrides', 'canPreviewPortal', 'dsgvoRequests'])

const { t } = useI18n()

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
  offers: asChoice(props.portalOverrides.offers),
  welcome_inquiry: (props.portalOverrides.welcome_inquiry as string) ?? '',
  welcome_active: (props.portalOverrides.welcome_active as string) ?? '',
  welcome_done: (props.portalOverrides.welcome_done as string) ?? '',
})

function asChoice(value: string | boolean | undefined) {
  return value === undefined ? '' : value ? '1' : '0'
}

function savePortal() {
  portal
    .transform((data) => Object.fromEntries(
      Object.entries(data).map(([key, value]) => [
        key,
        value === '' ? null : value === '0' || value === '1' ? value === '1' : value,
      ]),
    ))
    .patch(`/kontakte/${props.contact.id}/portal`, { preserveScroll: true })
}

function day(iso: string | null) {
  return iso ? new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' }) : '—'
}

function time(iso: string) {
  return new Date(iso).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })
}

function lastSeenDay(iso: string) {
  const seen = new Date(iso)

  return seen.toDateString() === new Date().toDateString() ? t('contacts.portalToday') : day(iso)
}

// Was dieser Kunde unter „Weitere Leistungen" vorfindet: das Regal des
// Studios, und dazu, wo einer hängt, der Satz, den jemand für ihn
// geschrieben hat. Ein Vorschlag auf eine zurückgezogene Leistung steht
// unten weiter — sonst verschwände er, ohne entfernt worden zu sein.
const offerRows = computed(() => {
  const shelf = props.offerablePackages.map((one) => ({
    id: one.id,
    name: one.name,
    terms: [priceLabel(one), one.requiresDate ? t('packages.portalWithDate') : t('packages.portalWithoutDate')].join(' · '),
    suggestion: props.suggestions.find((row) => row.package === one.id) ?? null,
  }))

  const withdrawn = props.suggestions
    .filter((row) => !row.live)
    .map((row) => ({ id: row.package, name: row.name, terms: '', suggestion: row }))

  return [...shelf, ...withdrawn]
})

// Ein Punkt ist eine Anmeldung. Die genaue Zahl steht darunter im Satz, also
// braucht die Reihe die Überzähligen nicht auch noch anzuschreiben.
const loginDots = computed<Tone[]>(() => Array(Math.min(props.portalLogins.count, MAX_DOTS)).fill('on'))

function priceLabel(one: { price: number | null; priceMode: string }) {
  if (one.priceMode === 'on_request') {
    return t('packages.priceOnRequest')
  }

  const price = formatEurosRounded(one.price ?? 0)

  return one.priceMode === 'from' ? t('packages.priceFromValue', { price }) : price
}

// C-18 (#254) wanted every row operable where it stands, without a
// "Bearbeiten". A row that only becomes a field once clicked cannot say so,
// and three unnamed boxes side by side cannot say which is which — so the
// sheet reads, and the form edits.
const editing = ref(false)

function submit() {
  form.patch(`/kontakte/${props.contact.id}`, {
    preserveScroll: true,
    onSuccess: () => (editing.value = false),
  })
}

function cancelEdit() {
  form.reset()
  form.clearErrors()
  editing.value = false
}

const hasAddress = computed(() =>
  Boolean(props.billingAddress.street || props.billingAddress.postcode || props.billingAddress.city),
)

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

      <main data-tokens class="min-w-0 flex-1 overflow-y-auto pb-8">
        <PageHead :kicker="$t('contacts.title')" kicker-href="/kontakte" :title="contact.name">
          <div class="ml-auto flex items-center gap-2">
            <template v-if="editing">
              <span v-if="form.isDirty" class="flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-small leading-[1.5] font-semibold bg-due-bg text-due">{{ $t('settings.unsaved') }}</span>
              <Button variant="pill-muted" type="button" @click="cancelEdit">{{ $t('common.cancel') }}</Button>
              <Button v-if="form.isDirty" variant="pill" type="submit" form="contact-form" :disabled="form.processing">
                {{ $t('common.save') }}
              </Button>
            </template>
            <Button v-else variant="pill-muted" type="button" @click="editing = true">{{ $t('common.edit') }}</Button>
          </div>
        </PageHead>

        <!-- Dieselbe Reiterspur wie im Auftrag (ProjectShell). -->
        <div class="mx-6 mt-4 inline-flex max-w-full gap-0.5 overflow-x-auto rounded-lg bg-sunk p-[3px] text-small font-medium">
          <button
            v-for="key in TABS"
            :key="key"
            class="cursor-pointer rounded-[var(--r-sm)] border-0 bg-transparent px-2.5 py-1 leading-[1.4] whitespace-nowrap"
            :class="tab === key ? 'bg-paper text-foreground shadow-[0_1px_2px_rgba(24,24,27,.08)]' : 'text-ink-2 hover:text-foreground'"
            type="button"
            @click="tab = key"
          >
            {{ $t(`contacts.tab.${key}`) }}
          </button>
        </div>

        <div class="px-6 pt-6">
          <!-- Nur der Portal-Reiter hat eine zweite Spalte und braucht das
               volle Maß der Fallseite; die übrigen bleiben auf Lesebreite. -->
          <div :class="tab === 'portal' ? 'max-w-[1100px]' : 'max-w-[760px]'">
            <div v-if="tab === 'overview'">
              <div v-if="!editing" class="mb-7 flex gap-2">
                <a
                  v-if="contact.phone"
                  class="inline-flex items-center gap-[7px] rounded-[var(--r-sm)] bg-action px-[15px] py-2 text-small text-action-foreground no-underline"
                  :href="`tel:${contact.phone}`"
                >
                  {{ $t('contacts.call') }}
                </a>
                <a
                  v-if="contact.email"
                  class="inline-flex items-center gap-[7px] rounded-[var(--r-sm)] bg-sunk px-[15px] py-2 text-small text-foreground no-underline hover:bg-sunk-2"
                  :href="`mailto:${contact.email}`"
                >
                  {{ $t('contacts.email') }}
                </a>
              </div>

              <!-- Every field the sheet holds, each one named. Empty ones live in
                   the form, not here: a row reading "—" states nothing the form
                   does not state better. -->
              <form v-if="editing" id="contact-form" class="mb-7 flex flex-col gap-4" @submit.prevent="submit">
                <Field id="c-name" :label="$t('contacts.name')" :error="form.errors.name">
                  <Input id="c-name" v-model="form.name" type="text" autofocus />
                </Field>
                <Field id="c-phone" :label="$t('contacts.phone')" :error="form.errors.phone">
                  <Input id="c-phone" v-model="form.phone" type="tel" autocomplete="tel" />
                </Field>
                <Field id="c-email" :label="$t('contacts.email')" :error="form.errors.email">
                  <Input id="c-email" v-model="form.email" type="email" autocomplete="email" />
                </Field>
                <Field id="c-street" :label="$t('contacts.street')" :error="form.errors.street">
                  <Input id="c-street" v-model="form.street" type="text" autocomplete="street-address" />
                </Field>
                <Field id="c-postcode" :label="$t('contacts.postcode')" :error="form.errors.postcode">
                  <Input id="c-postcode" v-model="form.postcode" type="text" autocomplete="postal-code" />
                </Field>
                <Field id="c-city" :label="$t('contacts.city')" :error="form.errors.city">
                  <Input id="c-city" v-model="form.city" type="text" autocomplete="address-level2" />
                </Field>
              </form>

              <section v-else-if="contact.phone || contact.email || hasAddress" class="mb-7">
                <h2 class="mt-0 mb-3 text-small leading-none font-semibold text-ink-2">
                  {{ $t('contacts.reach') }}
                </h2>

                <div v-if="contact.phone" class="flex items-center gap-4 py-2.5 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none">
                  <div>
                    <div class="text-body leading-none font-semibold tracking-[-0.012em] tabular-nums">{{ contact.phone }}</div>
                    <div class="mt-px text-small text-ink-2">{{ $t('contacts.phone') }}</div>
                  </div>
                </div>

                <div v-if="contact.email" class="flex items-center gap-4 py-2.5 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none">
                  <div>
                    <div class="text-body leading-none font-semibold tracking-[-0.012em] tabular-nums">{{ contact.email }}</div>
                    <div class="mt-px text-small text-ink-2">{{ $t('contacts.email') }}</div>
                  </div>
                </div>

                <!-- M-06: the address every invoice carries and this screen never
                     showed. -->
                <div v-if="hasAddress" class="flex items-center gap-4 py-2.5 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none">
                  <div>
                    <div class="text-body leading-none font-semibold tracking-[-0.012em] tabular-nums">
                      {{ [billingAddress.street, [billingAddress.postcode, billingAddress.city].filter(Boolean).join(' ')].filter(Boolean).join(', ') }}
                    </div>
                    <div class="mt-px text-small text-ink-2">{{ $t('contacts.address') }}</div>
                  </div>
                </div>
              </section>

              <section v-if="connected.length" class="mb-7">
                <h2 class="mt-0 mb-3 text-small leading-none font-semibold text-ink-2">
                  {{ $t('contacts.connected') }}
                </h2>

              <div
                v-for="person in connected"
                :key="person.id"
                class="py-2 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none"
              >
                <Link class="text-small font-semibold tracking-[-0.012em]" :href="`/kontakte/${person.id}`">
                  {{ person.name }}
                </Link>
                <div v-if="person.role" class="mt-px text-small text-ink-2">
                  {{ $t(`contacts.role.${person.role}`) }}
                </div>
              </div>
              </section>
              <section v-if="canManageDsgvo" class="mb-7">
                <h2 class="mt-0 mb-3 text-small leading-none font-semibold text-ink-2">
                  {{ $t('contacts.dsgvo') }}
                </h2>

              <!-- P-05 (#224): what the client asked for from the portal, beside
                   the two actions that answer it. -->
              <div
                v-for="request in dsgvoRequests"
                :key="request.id"
                class="py-2 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none"
              >
                <div class="mt-px text-small text-due">
                  {{ $t(`contacts.dsgvoRequested.${request.kind}`, { date: day(request.requestedAt) }) }}
                </div>
              </div>

              <p v-if="dsgvoErasedAt" class="mt-px text-small text-ink-2">
                {{ $t('contacts.dsgvoErased', { date: day(dsgvoErasedAt) }) }}
              </p>
              <template v-else>
                <Button
                  as="a"
                  :href="`/kontakte/${contact.id}/dsgvo/export`"
                  variant="pill-muted"
                >
                  {{ $t('contacts.dsgvoExport') }}
                </Button>

                <AlertDialog>
                  <AlertDialogTrigger as-child>
                    <Button class="ml-2" type="button" variant="pill-muted">
                      {{ $t('contacts.dsgvoErase') }}
                    </Button>
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
              </section>
            </div>

            <div v-else-if="tab === 'bookings'">
              <!-- Two sections, never one list: a project is an order with money
                   and dates, a gallery a delivery with an expiry. -->
              <section v-if="projects.length" class="mb-7">
                <h2 class="mt-0 mb-3 text-small leading-none font-semibold text-ink-2">
                  {{ $t('contacts.projects') }}
                </h2>
                <div
                  v-for="project in projects"
                  :key="project.id"
                  class="flex items-center gap-4 py-2.5 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none"
                >
                  <div>
                    <div class="text-body leading-none font-semibold tracking-[-0.012em] tabular-nums">
                      <Link class="no-underline hover:underline" :href="`/auftraege/${project.id}`">{{ project.name }}</Link>
                    </div>
                    <div class="mt-px text-small text-ink-2">
                      {{ $t(`contacts.role.${project.role}`) }}
                      <template v-if="project.date"> · {{ day(project.date) }}</template>
                    </div>
                  </div>
                  <span v-if="project.value" class="ml-auto text-small text-ink-2">
                    {{ formatEurosRounded(project.value) }}
                  </span>
                </div>
              </section>

              <section v-if="galleries.length" class="mb-7">
                <h2 class="mt-0 mb-3 text-small leading-none font-semibold text-ink-2">
                  {{ $t('contacts.galleries') }}
                </h2>
                <div
                  v-for="gallery in galleries"
                  :key="gallery.id"
                  class="flex items-center gap-4 py-2.5 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none"
                >
                  <div>
                    <div class="text-body leading-none font-semibold tracking-[-0.012em] tabular-nums">
                      <Link class="no-underline hover:underline" :href="`/galerien/${gallery.id}`">{{ gallery.title ?? '—' }}</Link>
                    </div>
                    <div class="mt-px text-small text-ink-2">
                      {{ $t('contacts.assetCount', gallery.assetCount) }}
                      ·
                      {{ gallery.openedOn ? $t('contacts.openedOn', { date: day(gallery.openedOn) }) : $t('contacts.notOpened') }}
                    </div>
                  </div>
                  <span v-if="gallery.expiresAt" class="ml-auto text-small text-ink-2">
                    {{ $t('contacts.expiresOn', { date: day(gallery.expiresAt) }) }}
                  </span>
                </div>
              </section>

            </div>

            <div v-else-if="tab === 'correspondence'">
              <!-- E-11: what the studio has actually said to this person, across
                   every project. A thread only ever knows its own project, so this
                   is the one screen that can answer the question at all. -->
              <section v-if="correspondence.length" class="mb-7">
                <h2 class="mt-0 mb-3 text-small leading-none font-semibold text-ink-2">
                  {{ $t('contacts.correspondence') }}
                </h2>
                <div
                  v-for="message in correspondence"
                  :key="message.id"
                  class="py-3 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none"
                >
                  <div class="flex items-baseline gap-2.25">
                    <span class="text-small font-semibold tracking-[-0.012em]">{{ message.author }}</span>
                    <span class="ml-auto text-small tabular-nums text-ink-2">
                      {{ day(message.sentAt) }}
                      <template v-if="message.projectName">
                        ·
                        <Link class="no-underline hover:underline" :href="`/auftraege/${message.projectId}/nachrichten`">{{ message.projectName }}</Link>
                      </template>
                      <template v-if="message.deliveryState === 'failed'"> · {{ $t('contacts.deliveryFailed') }}</template>
                    </span>
                  </div>
                  <div class="mt-[5px] max-w-[68ch] text-body">{{ message.body }}</div>
                </div>
              </section>
            </div>

            <div v-else-if="tab === 'portal'" class="flex items-start gap-6 max-[1180px]:flex-col">
              <div class="min-w-0 flex-1 space-y-7">
                <Panel :title="$t('portalSettings.welcomeSection')">
                  <ContactPortalFields group="welcome" :portal="portal" :defaults="portalDefaults" @change="savePortal" />
                </Panel>

                <Panel :title="$t('contacts.portalPermissions')">
                  <ContactPortalFields group="permissions" :portal="portal" :defaults="portalDefaults" @change="savePortal" />
                </Panel>

                <Panel :title="$t('contacts.portalGallery')">
                  <ContactPortalFields group="gallery" :portal="portal" :defaults="portalDefaults" @change="savePortal" />
                </Panel>

                <!-- Jede Leistung eine Zeile, auch die, die alle Kunden sehen: was
                     dieser Kunde im Portal vorfindet, steht hier einzeln und nicht
                     als Zahl. Rechts steht, warum sie dort steht. -->
                <Panel :title="$t('contacts.suggestionsSection')">
                  <template #action>
                    <Link class="ml-auto text-small text-ring no-underline hover:underline" href="/pakete">
                      {{ $t('contacts.changeInPackages') }}
                    </Link>
                  </template>

                  <ContactPortalFields group="offers" :portal="portal" :defaults="portalDefaults" @change="savePortal" />

                  <template v-for="row in offerRows" :key="row.id">
                    <div class="flex items-center gap-4 py-2.5 shadow-[inset_0_1px_0_var(--rule)]">
                      <div class="min-w-0">
                        <div class="text-body leading-none font-semibold tracking-[-0.012em]">{{ row.name }}</div>
                        <div class="mt-px text-small text-ink-2">{{ row.terms }}</div>
                      </div>
                      <span class="ml-auto flex-none text-small" :class="row.suggestion ? 'text-ring' : 'text-ink-2'">
                        {{ row.suggestion ? $t('contacts.offerSuggested', { name: contact.name }) : $t('contacts.offerForAll') }}
                      </span>
                    </div>

                    <div v-if="row.suggestion" class="flex items-start gap-4 pb-2.5">
                      <p class="m-0 max-w-[62ch] flex-1 text-ui leading-[1.55]">
                        {{ row.suggestion.note }}
                        <span class="text-small text-ink-2">
                          <template v-if="row.suggestion.author">{{ row.suggestion.author }}</template>
                          <template v-if="row.suggestion.suggestedOn"> {{ $t('contacts.suggestedOn', { date: day(row.suggestion.suggestedOn) }) }}</template>
                          <template v-if="!row.suggestion.live"> · {{ $t('contacts.suggestionWithdrawn') }}</template>
                        </span>
                      </p>
                      <span class="flex flex-none gap-2">
                        <Button type="button" variant="pill-muted" @click="editSuggestion(row.suggestion)">{{ $t('common.edit') }}</Button>
                        <Button type="button" variant="pill-muted" @click="removeSuggestion(row.suggestion.id)">{{ $t('contacts.suggestionRemove') }}</Button>
                      </span>
                    </div>
                  </template>

                  <form v-if="offerablePackages.length" class="mt-4 flex flex-col gap-3" @submit.prevent="saveSuggestion">
                    <Field id="s-package" :label="$t('contacts.suggestionPackage')" :error="suggestion.errors.package">
                      <Select
                        :model-value="suggestion.package || undefined"
                        @update:model-value="(v) => (suggestion.package = String(v))"
                      >
                        <SelectTrigger id="s-package"><SelectValue /></SelectTrigger>
                        <SelectContent>
                          <SelectItem v-for="one in offerablePackages" :key="one.id" :value="one.id">{{ one.name }}</SelectItem>
                        </SelectContent>
                      </Select>
                    </Field>
                    <Field id="s-note" :label="$t('contacts.suggestionNote')" :error="suggestion.errors.note">
                      <Textarea id="s-note" v-model="suggestion.note" rows="3" />
                    </Field>
                    <div>
                      <Button type="submit" variant="pill" :disabled="!suggestion.package || suggestion.processing">
                        {{ $t('contacts.suggest') }}
                      </Button>
                    </div>
                  </form>
                </Panel>
              </div>

              <!-- Zugang ist Zustand, keine Einstellung: nachsehen, nicht
                   bedienen. Deshalb rechts und in Karten wie die Übersicht,
                   nicht in einer grauen Randspalte. -->
              <aside class="w-[280px] flex-none max-[1180px]:w-full">
                <h2 class="mt-0 mb-2.5 text-small leading-none font-semibold text-ink-2">
                  {{ $t('contacts.portalAccess') }}
                </h2>

                <Metric
                  :label="$t('contacts.portalLastSeen')"
                  :value="portalLogins.lastAt ? lastSeenDay(portalLogins.lastAt) : '—'"
                  :unit="portalLogins.lastAt ? time(portalLogins.lastAt) : undefined"
                  :dots="loginDots"
                >
                  <p v-if="portalLogins.count">
                    {{ $t('contacts.portalLoginCount', { count: portalLogins.count, date: day(portalLogins.firstAt) }) }}
                  </p>
                  <p v-if="portalAccessRevokedAt">
                    {{ $t('contacts.portalAccessRevoked', { date: day(portalAccessRevokedAt) }) }}
                  </p>
                </Metric>

                <div class="mt-4 flex flex-wrap gap-2">
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
                    <Button type="submit" variant="pill-muted">{{ $t('contacts.portalPreview') }}</Button>
                  </form>

                  <Button
                    v-if="canSendPortalAccess && !portalAccessRevokedAt"
                    type="button"
                    variant="pill-muted"
                    @click="router.visit(`/kontakte/${contact.id}/portal-zugang/senden`)"
                  >
                    {{ $t('contacts.sendPortalAccess') }}
                  </Button>

                  <AlertDialog v-if="canRevokePortalAccess && !portalAccessRevokedAt">
                    <AlertDialogTrigger as-child>
                      <Button type="button" variant="pill-muted">{{ $t('contacts.revokePortalAccess') }}</Button>
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
                </div>
              </aside>
            </div>
          </div>
        </div>
      </main>

    </Page>
  </AdminLayout>
</template>
