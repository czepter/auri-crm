<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import ContactQueue from '@/admin/components/ContactQueue.vue'
import ContactPortalFields from '@/admin/components/ContactPortalFields.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import Rail from '@/ui/page/Rail.vue'
import RailSection from '@/ui/page/RailSection.vue'
import { Button } from '@/ui/button'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/ui/alert-dialog'
import { formatEurosRounded } from '@/admin/lib/money'

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
        <PageHead :kicker="$t('contacts.title')" :title="contact.name">
          <div class="ml-auto flex items-center gap-2">
            <template v-if="editing">
              <span v-if="form.isDirty" class="flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase bg-due-bg text-due">{{ $t('settings.unsaved') }}</span>
              <Button variant="pill-muted" type="button" @click="cancelEdit">{{ $t('common.cancel') }}</Button>
              <Button v-if="form.isDirty" variant="pill" type="submit" form="contact-form" :disabled="form.processing">
                {{ $t('common.save') }}
              </Button>
            </template>
            <Button v-else variant="pill-muted" type="button" @click="editing = true">{{ $t('common.edit') }}</Button>
          </div>
        </PageHead>

        <div class="px-8 pt-7">
          <div class="max-w-[760px]">
            <div v-if="!editing" class="mb-7 flex gap-2">
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
              <h2 class="mt-0 mb-3 text-[10.5px] leading-none font-semibold tracking-[0.07em] uppercase text-ink-2">
                {{ $t('contacts.reach') }}
              </h2>

              <div v-if="contact.phone" class="flex items-center gap-4 py-2.5 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none">
                <div>
                  <div class="text-[15px] leading-none font-semibold tracking-[-0.012em] tabular-nums">{{ contact.phone }}</div>
                  <div class="mt-px text-[12px] text-ink-2">{{ $t('contacts.phone') }}</div>
                </div>
              </div>

              <div v-if="contact.email" class="flex items-center gap-4 py-2.5 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none">
                <div>
                  <div class="text-[15px] leading-none font-semibold tracking-[-0.012em] tabular-nums">{{ contact.email }}</div>
                  <div class="mt-px text-[12px] text-ink-2">{{ $t('contacts.email') }}</div>
                </div>
              </div>

              <!-- M-06: the address every invoice carries and this screen never
                   showed. -->
              <div v-if="hasAddress" class="flex items-center gap-4 py-2.5 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none">
                <div>
                  <div class="text-[15px] leading-none font-semibold tracking-[-0.012em] tabular-nums">
                    {{ [billingAddress.street, [billingAddress.postcode, billingAddress.city].filter(Boolean).join(' ')].filter(Boolean).join(', ') }}
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
        </div>
      </main>

      <template #right>
        <Rail data-tokens class="w-88">
          <RailSection :title="$t('contacts.portalSection')">
            <ContactPortalFields :portal="portal" :defaults="portalDefaults" @change="savePortal" />

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
              <Button type="submit" variant="pill-muted">
                {{ $t('contacts.portalPreview') }}
              </Button>
            </form>

            <AlertDialog v-if="canRevokePortalAccess && !portalAccessRevokedAt">
              <AlertDialogTrigger as-child>
                <Button type="button" variant="pill-muted">
                  {{ $t('contacts.revokePortalAccess') }}
                </Button>
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
          </RailSection>
        </Rail>
      </template>
    </Page>
  </AdminLayout>
</template>
