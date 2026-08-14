<script setup lang="ts">
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Label } from '@/ui/label'
import { Dialog, DialogBody, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/ui/dialog'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/ui/alert-dialog'

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
  router.delete(`/team/einladungen/${invitation.id}`, { preserveScroll: true })
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
      <!-- Three fields and you are done — the page behind is the roster you
           are adding to, and it should still be there when you look up. -->
      <Dialog v-model:open="inviting">
        <DialogTrigger as-child>
          <Button type="button">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
            {{ $t('team.invite') }}
          </Button>
        </DialogTrigger>

        <DialogContent>
          <DialogHeader>
            <DialogTitle>{{ $t('team.invite') }}</DialogTitle>
          </DialogHeader>

          <DialogBody>
            <form id="team-invite-form" class="fgrid" @submit.prevent="invite">
              <div class="fld wide">
                <Label for="name">{{ $t('team.name') }}</Label>
                <Input id="name" v-model="form.name" type="text" required />
                <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
              </div>
              <div class="fld">
                <Label for="email">{{ $t('auth.email') }}</Label>
                <Input id="email" v-model="form.email" type="email" required />
                <p v-if="form.errors.email" class="err">{{ form.errors.email }}</p>
              </div>
              <div class="fld">
                <Label for="role">{{ $t('team.role') }}</Label>
                <Select :model-value="form.role" @update:model-value="(value) => (form.role = String(value))">
                  <SelectTrigger id="role">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="role in roles" :key="role.value" :value="role.value">{{ role.label }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </form>
          </DialogBody>

          <DialogFooter>
            <Button type="submit" form="team-invite-form" :disabled="form.processing">{{ $t('team.send') }}</Button>
            <DialogClose as-child>
              <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
            </DialogClose>
          </DialogFooter>
        </DialogContent>
      </Dialog>
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
          <AlertDialog>
            <AlertDialogTrigger as-child>
              <Button type="button" variant="outline" tone="accent">{{ $t('team.revoke') }}</Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
              <AlertDialogHeader>
                <AlertDialogTitle>{{ $t('team.revokeConfirm', { email: invitation.email }) }}</AlertDialogTitle>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogAction @click="revoke(invitation)">{{ $t('team.revoke') }}</AlertDialogAction>
                <AlertDialogCancel>{{ $t('common.cancel') }}</AlertDialogCancel>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
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
