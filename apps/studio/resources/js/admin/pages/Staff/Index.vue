<script setup lang="ts">
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Field } from '@/ui/field'
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
    <div class="mb-6 flex items-center gap-4">
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
            <form id="team-invite-form" class="grid grid-cols-2 gap-x-6 gap-y-4" @submit.prevent="invite">
              <Field id="name" wide :label="$t('team.name')" :error="form.errors.name">
                <Input id="name" v-model="form.name" type="text" required />
              </Field>
              <Field id="email" :label="$t('auth.email')" :error="form.errors.email">
                <Input id="email" v-model="form.email" type="email" required />
              </Field>
              <Field id="role" :label="$t('team.role')">
                <Select :model-value="form.role" @update:model-value="(value) => (form.role = String(value))">
                  <SelectTrigger id="role">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="role in roles" :key="role.value" :value="role.value">{{ role.label }}</SelectItem>
                  </SelectContent>
                </Select>
              </Field>
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

    <section v-if="invitations.length" class="mt-8 first-of-type:mt-6">
      <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('team.pending') }}</div>
      <div class="overflow-hidden rounded-lg border border-border">
        <div v-for="invitation in invitations" :key="invitation.id" class="flex items-center gap-2.5 border-t border-border p-2.5 text-inherit no-underline transition-colors duration-[120ms] ease-out first:border-t-0 hover:bg-muted">
          <span class="flex size-8 flex-none items-center justify-center rounded-full bg-muted text-[11px] leading-none font-semibold text-muted-foreground">{{ initials(invitation.name) }}</span>
          <span class="min-w-0 flex-1">
            <span class="block text-[13px] leading-[1.3] font-semibold">{{ invitation.name }}</span>
            <span class="mt-px block text-[12px] leading-[1.3] font-normal text-muted-foreground">{{ invitation.email }} · {{ invitation.roleLabel }}</span>
          </span>
          <span class="flex-none text-[12px] tabular-nums text-faint">{{ $t('team.expires', { date: invitation.expiresOn }) }}</span>
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

    <section class="mt-8 first-of-type:mt-6">
      <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('team.members') }}</div>
      <div class="overflow-hidden rounded-lg border border-border">
        <div v-for="member in staff" :key="member.id" class="flex items-center gap-2.5 border-t border-border p-2.5 text-inherit no-underline transition-colors duration-[120ms] ease-out first:border-t-0 hover:bg-muted">
          <span class="flex size-8 flex-none items-center justify-center rounded-full bg-muted text-[11px] leading-none font-semibold text-muted-foreground">{{ initials(member.name) }}</span>
          <span class="min-w-0 flex-1">
            <span class="block text-[13px] leading-[1.3] font-semibold">{{ member.name }}</span>
            <span class="mt-px block text-[12px] leading-[1.3] font-normal text-muted-foreground">{{ member.email }}</span>
          </span>
          <span class="inline-flex items-center rounded-[5px] bg-muted px-2 py-[3px] text-[11px] leading-[1.4] font-medium whitespace-nowrap text-muted-foreground">{{ member.roleLabel }}</span>
          <!-- An account without the second factor cannot reach anything, so
               the list says so rather than looking complete. -->
          <span
            class="flex-none rounded-[5px] px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase"
            :class="member.mfaConfirmed ? 'bg-ok-bg text-ok' : 'bg-muted text-faint'"
          >
            {{ member.mfaConfirmed ? $t('team.mfaOn') : $t('team.mfaPending') }}
          </span>
        </div>
      </div>
    </section>
  </AdminLayout>
</template>
