<script setup lang="ts">
import { ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { Textarea } from '@/ui/textarea'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/ui/alert-dialog'

const { t } = useI18n()

const props = defineProps<{
  contact: App.Crm.Data.ContactSuggestionData
  billingAddress: { street: string | null; postcode: string | null; city: string | null }
  correspondence: App.Engagement.Data.MessageData[]
  projects: { id: string; name: string; role: string; date: string | null; value: number | null; booked: boolean }[]
  galleries: { id: string; title: string | null; expiresAt: string | null; assetCount: number }[]
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

function submit() {
  form.patch(`/kontakte/${props.contact.id}`)
}

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
const SWITCHES = ['accept_quotes', 'sign_contracts', 'payment_status',
  'gallery_downloads', 'gallery_lists', 'gallery_comments'] as const

const portal = useForm({
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

const money = new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 })

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

function previewPortal() {
  router.post(`/kontakte/${props.contact.id}/portal-vorschau`)
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
    <div data-theme="crm" style="display: flex; align-items: flex-start">
      <div class="sheet">
        <!-- The head carries the name and nothing else. No avatar: initials
             in a circle repeat the name beside them, and a picture has
             neither a column nor a store. No subtitle either — role and
             standing are further down, where they can be acted on. -->
        <div class="head">
          <h1>{{ contact.name }}</h1>
        </div>

        <div class="quick">
          <a v-if="contact.phone" class="primary" :href="`tel:${contact.phone}`">{{ $t('contacts.call') }}</a>
          <a v-if="contact.email" :href="`mailto:${contact.email}`">{{ $t('contacts.email') }}</a>
        </div>

        <section>
          <h2>{{ $t('contacts.reach') }}</h2>

          <div class="datarow" @click="editing = editing === 'phone' ? null : 'phone'">
            <div>
              <div v-if="editing === 'phone'" class="val">
                <input v-model="form.phone" type="tel" @keyup.enter="saveField" @blur="saveField" />
              </div>
              <div v-else class="val">{{ contact.phone ?? '—' }}</div>
              <div class="lab">{{ $t('contacts.phone') }}</div>
            </div>
          </div>

          <div class="datarow" @click="editing = editing === 'email' ? null : 'email'">
            <div>
              <div v-if="editing === 'email'" class="val">
                <input v-model="form.email" type="email" @keyup.enter="saveField" @blur="saveField" />
              </div>
              <div v-else class="val">{{ contact.email ?? '—' }}</div>
              <div class="lab">{{ $t('contacts.email') }}</div>
            </div>
          </div>

          <!-- M-06: the address every invoice carries and this screen never
               showed. -->
          <div class="datarow" @click="editing = editing === 'address' ? null : 'address'">
            <div>
              <div v-if="editing === 'address'" class="val line">
                <input v-model="form.street" type="text" autocomplete="street-address" />
                <input v-model="form.postcode" class="s" type="text" autocomplete="postal-code" />
                <input v-model="form.city" type="text" autocomplete="address-level2" @keyup.enter="saveField" />
              </div>
              <div v-else class="val">
                {{ [billingAddress.street, [billingAddress.postcode, billingAddress.city].filter(Boolean).join(' ')].filter(Boolean).join(', ') || '—' }}
              </div>
              <div class="lab">{{ $t('contacts.address') }}</div>
            </div>
            <button v-if="editing === 'address'" class="btn-2 right" type="button" @click.stop="saveField">
              {{ $t('common.save') }}
            </button>
          </div>
        </section>

        <!-- Two sections, never one list: a project is an order with money
             and dates, a gallery a delivery with an expiry. -->
        <section v-if="projects.length">
          <h2>{{ $t('contacts.projects') }}</h2>
          <div v-for="project in projects" :key="project.id" class="datarow">
            <div>
              <div class="val"><Link :href="`/auftraege/${project.id}`">{{ project.name }}</Link></div>
              <div class="lab">
                {{ $t(`contacts.role.${project.role}`) }}
                <template v-if="project.date"> · {{ day(project.date) }}</template>
              </div>
            </div>
            <span v-if="project.value" class="right">{{ money.format(project.value / 100) }}</span>
          </div>
        </section>

        <section v-if="galleries.length">
          <h2>{{ $t('contacts.galleries') }}</h2>
          <div v-for="gallery in galleries" :key="gallery.id" class="datarow">
            <div>
              <div class="val">{{ gallery.title ?? '—' }}</div>
              <div class="lab">{{ $t('contacts.assetCount', gallery.assetCount) }}</div>
            </div>
            <span v-if="gallery.expiresAt" class="right">{{ $t('contacts.expiresOn', { date: day(gallery.expiresAt) }) }}</span>
          </div>
        </section>

        <!-- E-11: what the studio has actually said to this person, across
             every project. A thread only ever knows its own project, so this
             is the one screen that can answer the question at all. -->
        <section v-if="correspondence.length">
          <h2>{{ $t('contacts.correspondence') }}</h2>
          <div v-for="message in correspondence" :key="message.id" class="msg">
            <div class="head">
              <span class="n">{{ message.author }}</span>
              <span class="t">
                {{ day(message.sentAt) }}
                <template v-if="message.projectName"> · {{ message.projectName }}</template>
                <template v-if="message.deliveryState === 'failed'"> · {{ $t('contacts.deliveryFailed') }}</template>
              </span>
            </div>
            <div class="body">{{ message.body }}</div>
          </div>
        </section>
      </div>

      <aside class="rail" style="width: 320px">
        <h2>{{ $t('contacts.portalSection') }}</h2>

        <!-- No state text beside a switch: "wie Vorgabe" and "abweichend"
             narrate what the switch already shows. What is left is a reset,
             and only where an override actually holds a value. -->
        <div v-for="key in SWITCHES" :key="key" class="setting">
          <div class="t">
            {{ $t(`contacts.portalKey.${key}`) }}
            <a v-if="portal[key] !== ''" class="link s" href="#" @click.prevent="reset(key)">
              {{ $t('contacts.portalReset') }}
            </a>
          </div>
          <span class="sw" :class="{ on: isOn(key) }" @click="toggle(key)" />
        </div>

        <div class="setting">
          <div class="t">{{ $t('contacts.portalKey.messaging_mode') }}</div>
          <select v-model="portal.messaging_mode" @change="savePortal">
            <option value="">{{ $t('contacts.portalInherit', { value: defaultLabel('messaging_mode') }) }}</option>
            <option value="off">{{ $t('portalSettings.messagingMode.off') }}</option>
            <option value="booking">{{ $t('portalSettings.messagingMode.booking') }}</option>
            <option value="any">{{ $t('portalSettings.messagingMode.any') }}</option>
          </select>
        </div>

        <div class="setting">
          <div class="t">
            {{ $t('contacts.portalKey.welcome') }}
            <Textarea v-model="portal.welcome" rows="2" @blur="savePortal" />
          </div>
        </div>

        <!-- The access belongs to the portal, under the switches: validity,
             a fresh link, withdrawal. -->
        <div class="setting">
          <div class="t">
            {{ $t('contacts.portalAccess') }}
            <div class="s">
              {{ portalAccessRevokedAt
                ? $t('contacts.portalAccessRevoked', { date: day(portalAccessRevokedAt) })
                : $t('contacts.portalAccessActive') }}
            </div>
          </div>
        </div>

        <p v-if="canPreviewPortal">
          <button class="btn-2" type="button" @click="previewPortal()">{{ $t('contacts.portalPreview') }}</button>
        </p>

        <AlertDialog v-if="canRevokePortalAccess && !portalAccessRevokedAt">
          <AlertDialogTrigger as-child>
            <button class="btn-2" type="button">{{ $t('contacts.revokePortalAccess') }}</button>
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

        <template v-if="connected.length">
          <h2>{{ $t('contacts.connected') }}</h2>
          <div v-for="person in connected" :key="person.id" class="item">
            <Link class="n" :href="`/kontakte/${person.id}`">{{ person.name }}</Link>
            <div v-if="person.role" class="m">{{ $t(`contacts.role.${person.role}`) }}</div>
          </div>
        </template>

        <template v-if="canManageDsgvo">
          <h2>{{ $t('contacts.dsgvo') }}</h2>

          <!-- P-05 (#224): what the client asked for from the portal, beside
               the two actions that answer it. -->
          <div v-for="request in dsgvoRequests" :key="request.id" class="item">
            <div class="m warn">
              {{ $t(`contacts.dsgvoRequested.${request.kind}`, { date: day(request.requestedAt) }) }}
            </div>
          </div>

          <p v-if="dsgvoErasedAt" class="m">{{ $t('contacts.dsgvoErased', { date: day(dsgvoErasedAt) }) }}</p>
          <template v-else>
            <a class="btn-2" :href="`/kontakte/${contact.id}/dsgvo/export`">{{ $t('contacts.dsgvoExport') }}</a>

            <AlertDialog>
              <AlertDialogTrigger as-child>
                <button class="btn-2" type="button" style="margin-left: var(--s2)">{{ $t('contacts.dsgvoErase') }}</button>
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
        </template>
      </aside>
    </div>
  </AdminLayout>
</template>
