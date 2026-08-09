<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

const props = defineProps<{
  contact: App.Crm.Data.ContactSuggestionData
  portalAccessRevokedAt: string | null
  canRevokePortalAccess: boolean
}>()

const { t } = useI18n()

const form = useForm({
  name: props.contact.name,
  email: props.contact.email ?? '',
  phone: props.contact.phone ?? '',
})

function submit() {
  form.patch(`/kontakte/${props.contact.id}`)
}

// Its own confirm, worded to the one thing this button changes: every
// project this contact can reach in the portal, not one of them — the
// opposite scope from a project's own "hide from portal" control.
function revokePortalAccess() {
  if (confirm(t('contacts.revokePortalAccessConfirm', { name: props.contact.name }))) {
    router.post(`/kontakte/${props.contact.id}/portal-zugang/widerruf`)
  }
}
</script>

<template>
  <AdminLayout active="kontakte">
    <div style="max-width: 520px">
      <h1>{{ $t('contacts.editTitle') }}</h1>

      <form style="margin-top: var(--s5)" @submit.prevent="submit">
        <div class="fld grp">
          <label for="name">{{ $t('contacts.name') }}</label>
          <input id="name" v-model="form.name" class="inp" type="text" />
          <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
        </div>
        <div class="fld grp">
          <label for="email">{{ $t('contacts.email') }}</label>
          <input id="email" v-model="form.email" class="inp" type="email" />
          <p v-if="form.errors.email" class="err">{{ form.errors.email }}</p>
        </div>
        <div class="fld grp">
          <label for="phone">{{ $t('contacts.phone') }}</label>
          <input id="phone" v-model="form.phone" class="inp" type="tel" />
        </div>

        <div class="formfoot">
          <button class="btn" type="submit" :disabled="form.processing">
            {{ $t('common.save') }}
          </button>
          <a href="/kontakte" class="txtbtn">{{ $t('common.cancel') }}</a>
        </div>
      </form>

      <div v-if="canRevokePortalAccess" class="card static" style="margin-top: var(--s5)">
        <div class="cardhead"><span class="kick">{{ $t('contacts.portalAccess') }}</span></div>
        <p v-if="portalAccessRevokedAt">
          {{ $t('contacts.portalAccessRevoked', { date: new Date(portalAccessRevokedAt).toLocaleDateString('de-DE') }) }}
        </p>
        <template v-else>
          <p>{{ $t('contacts.portalAccessActive') }}</p>
          <button type="button" class="abtn" @click="revokePortalAccess">
            {{ $t('contacts.revokePortalAccess') }}
          </button>
        </template>
      </div>
    </div>
  </AdminLayout>
</template>
