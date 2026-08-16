<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/ui/alert-dialog'

const props = defineProps<{
  contact: App.Crm.Data.ContactSuggestionData
  billingAddress: { street: string | null; postcode: string | null; city: string | null }
  correspondence: App.Engagement.Data.MessageData[]
  portalAccessRevokedAt: string | null
  canRevokePortalAccess: boolean
  dsgvoErasedAt: string | null
  canManageDsgvo: boolean
  dsgvoRequests: { id: string; kind: string; requestedAt: string }[]
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
function eraseDsgvoData() {
  router.post(`/kontakte/${props.contact.id}/dsgvo/loeschen`)
}
</script>

<template>
  <AdminLayout active="kontakte">
    <div style="max-width: 520px">
      <h1>{{ $t('contacts.editTitle') }}</h1>

      <form style="margin-top: var(--s5)" @submit.prevent="submit">
        <div class="fld grp">
          <Label for="name">{{ $t('contacts.name') }}</Label>
          <Input id="name" v-model="form.name" type="text" />
          <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
        </div>
        <div class="fld grp">
          <Label for="email">{{ $t('contacts.email') }}</Label>
          <Input id="email" v-model="form.email" type="email" />
          <p v-if="form.errors.email" class="err">{{ form.errors.email }}</p>
        </div>
        <div class="fld grp">
          <Label for="phone">{{ $t('contacts.phone') }}</Label>
          <Input id="phone" v-model="form.phone" type="tel" />
        </div>

        <!-- M-06: the address an invoice needs. Optional here, and only
             ever missed at the moment one is issued. -->
        <div class="fld grp">
          <Label for="street">{{ $t('contacts.street') }}</Label>
          <Input id="street" v-model="form.street" type="text" autocomplete="street-address" />
        </div>
        <div class="fld grp">
          <Label for="postcode">{{ $t('contacts.postcode') }}</Label>
          <Input id="postcode" v-model="form.postcode" type="text" autocomplete="postal-code" />
        </div>
        <div class="fld grp">
          <Label for="city">{{ $t('contacts.city') }}</Label>
          <Input id="city" v-model="form.city" type="text" autocomplete="address-level2" />
        </div>

        <div class="formfoot">
          <Button type="submit" :disabled="form.processing">
            {{ $t('common.save') }}
          </Button>
          <Button as="a" href="/kontakte" variant="ghost">{{ $t('common.cancel') }}</Button>
        </div>
      </form>

      <!-- E-11: what the studio has actually said to this person, across
           every project. A thread only ever knows its own project, so this
           is the one screen that can answer the question at all. -->
      <div v-if="correspondence.length" class="card static" style="margin-top: var(--s5)">
        <div class="cardhead"><span class="kick">{{ $t('contacts.correspondence') }}</span></div>
        <div class="msg" style="gap: var(--s3); margin-top: var(--s3)">
          <div v-for="message in correspondence" :key="message.id" class="msgrow">
            <div class="msgtxt">
              <div class="msgwho">
                {{ message.author }}
                <em>{{ new Date(message.sentAt).toLocaleDateString('de-DE') }}</em>
                <em v-if="message.projectName"> · {{ message.projectName }}</em>
                <!-- A dead address belongs where the next reply gets typed,
                     not in a log nobody opens. -->
                <em v-if="message.deliveryState === 'failed'" class="err">
                  · {{ $t('contacts.deliveryFailed') }}
                </em>
              </div>
              <div class="msgbody">{{ message.body }}</div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="canRevokePortalAccess" class="card static" style="margin-top: var(--s5)">
        <div class="cardhead"><span class="kick">{{ $t('contacts.portalAccess') }}</span></div>
        <p v-if="portalAccessRevokedAt">
          {{ $t('contacts.portalAccessRevoked', { date: new Date(portalAccessRevokedAt).toLocaleDateString('de-DE') }) }}
        </p>
        <template v-else>
          <p>{{ $t('contacts.portalAccessActive') }}</p>
          <AlertDialog>
            <AlertDialogTrigger as-child>
              <Button type="button" variant="outline">{{ $t('contacts.revokePortalAccess') }}</Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
              <AlertDialogHeader>
                <AlertDialogTitle>
                  {{ $t('contacts.revokePortalAccessConfirm', { name: contact.name }) }}
                </AlertDialogTitle>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogAction @click="revokePortalAccess">
                  {{ $t('contacts.revokePortalAccess') }}
                </AlertDialogAction>
                <AlertDialogCancel>{{ $t('common.cancel') }}</AlertDialogCancel>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
        </template>
      </div>

      <div v-if="canManageDsgvo" class="card static" style="margin-top: var(--s5)">
        <div class="cardhead"><span class="kick">{{ $t('contacts.dsgvo') }}</span></div>

        <!-- P-05 (#224): what the client asked for from the portal. Shown
             here because this is where the two actions that answer it live. -->
        <p v-for="request in dsgvoRequests" :key="request.id" class="hint">
          {{ $t(`contacts.dsgvoRequested.${request.kind}`, {
            date: new Date(request.requestedAt).toLocaleDateString('de-DE'),
          }) }}
        </p>

        <p v-if="dsgvoErasedAt">
          {{ $t('contacts.dsgvoErased', { date: new Date(dsgvoErasedAt).toLocaleDateString('de-DE') }) }}
        </p>
        <template v-else>
          <Button as="a" variant="ghost" :href="`/kontakte/${contact.id}/dsgvo/export`">{{ $t('contacts.dsgvoExport') }}</Button>
          <AlertDialog>
            <AlertDialogTrigger as-child>
              <Button type="button" variant="outline" tone="accent">{{ $t('contacts.dsgvoErase') }}</Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
              <AlertDialogHeader>
                <AlertDialogTitle>
                  {{ $t('contacts.dsgvoEraseConfirm', { name: contact.name }) }}
                </AlertDialogTitle>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <!-- the accent fill is reserved for the one action here that
                     cannot be taken back -->
                <AlertDialogAction tone="destructive" @click="eraseDsgvoData">
                  {{ $t('contacts.dsgvoErase') }}
                </AlertDialogAction>
                <AlertDialogCancel>{{ $t('common.cancel') }}</AlertDialogCancel>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
        </template>
      </div>
    </div>
  </AdminLayout>
</template>
