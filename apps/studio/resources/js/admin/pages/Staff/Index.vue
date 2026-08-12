<script setup lang="ts">
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

type Member = {
  id: string
  name: string
  email: string
  role: string
  roleLabel: string
  mfaConfirmed: boolean
}

type Invitation = { id: string; email: string; name: string; roleLabel: string; expiresOn: string }

defineProps<{
  staff: Member[]
  invitations: Invitation[]
  roles: { value: string; label: string }[]
}>()

const { t } = useI18n()
const inviting = ref(false)
const form = useForm({ name: '', email: '', role: 'second_shooter' })

function invite() {
  form.post('/team/einladungen', {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      inviting.value = false
    },
  })
}

function revoke(invitation: Invitation) {
  if (confirm(t('team.revokeConfirm', { email: invitation.email }))) {
    router.delete(`/team/einladungen/${invitation.id}`, { preserveScroll: true })
  }
}

function initials(name: string) {
  const words = name.trim().split(/\s+/).filter(Boolean)

  return (words.length > 1 ? [words[0], words[words.length - 1]] : words)
    .map((word) => word[0])
    .join('')
    .toUpperCase()
}
</script>

<template>
  <AdminLayout active="team">
    <div class="hrow" style="margin-bottom: var(--s5)">
      <h1>{{ $t('team.title') }}</h1>
      <button class="btn" type="button" @click="inviting = !inviting">
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
        {{ $t('team.invite') }}
      </button>
    </div>

    <div v-if="inviting" class="card static" style="margin-bottom: var(--s5)">
      <div class="cardhead"><span class="kick">{{ $t('team.invite') }}</span></div>

      <form class="fgrid" @submit.prevent="invite">
        <div class="fld">
          <label for="name">{{ $t('team.name') }}</label>
          <input id="name" v-model="form.name" class="inp" type="text" required />
          <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
        </div>
        <div class="fld">
          <label for="email">{{ $t('auth.email') }}</label>
          <input id="email" v-model="form.email" class="inp" type="email" required />
          <p v-if="form.errors.email" class="err">{{ form.errors.email }}</p>
        </div>
        <div class="fld">
          <label for="role">{{ $t('team.role') }}</label>
          <select id="role" v-model="form.role" class="inp">
            <option v-for="role in roles" :key="role.value" :value="role.value">{{ role.label }}</option>
          </select>
        </div>
        <div class="fld wide">
          <div class="formfoot" style="margin-top: 0">
            <button class="btn" type="submit" :disabled="form.processing">{{ $t('team.send') }}</button>
            <button class="txtbtn" type="button" @click="inviting = false">{{ $t('common.cancel') }}</button>
          </div>
        </div>
      </form>
    </div>

    <section v-if="invitations.length" class="dsec">
      <div class="dlab">{{ $t('team.pending') }}</div>
      <div class="panel">
        <div v-for="invitation in invitations" :key="invitation.id" class="trow">
          <span class="pavatar">{{ initials(invitation.name) }}</span>
          <span class="pinfo">
            <span class="pname">{{ invitation.name }}</span>
            <span class="psub">{{ invitation.email }} · {{ invitation.roleLabel }}</span>
          </span>
          <span class="tdate">{{ $t('team.expires', { date: invitation.expiresOn }) }}</span>
          <button type="button" class="abtn hot" @click="revoke(invitation)">
            {{ $t('team.revoke') }}
          </button>
        </div>
      </div>
    </section>

    <section class="dsec">
      <div class="dlab">{{ $t('team.members') }}</div>
      <div class="panel">
        <div v-for="member in staff" :key="member.id" class="trow">
          <span class="pavatar">{{ initials(member.name) }}</span>
          <span class="pinfo">
            <span class="pname">{{ member.name }}</span>
            <span class="psub">{{ member.email }}</span>
          </span>
          <span class="role">{{ member.roleLabel }}</span>
          <!-- An account without the second factor cannot reach anything, so
               the list says so rather than looking complete. -->
          <span class="chip" :class="member.mfaConfirmed ? 'done' : 'off'">
            {{ member.mfaConfirmed ? $t('team.mfaOn') : $t('team.mfaPending') }}
          </span>
        </div>
      </div>
    </section>
  </AdminLayout>
</template>
