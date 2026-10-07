<script setup lang="ts">
import { ref } from 'vue'
import { Check } from 'lucide-vue-next'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Field } from '@/ui/field'
import { Table, TableCell, TableHead, TableRow } from '@/ui/table'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Dialog, DialogBody, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/ui/dialog'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/ui/alert-dialog'

type Member = {
  id: string
  name: string
  email: string
  role: string
  roleLabel: string
  mfaConfirmed: boolean
  transferable: boolean
}

type Invitation = { id: string; email: string; name: string; roleLabel: string; expiresOn: string }

defineProps<{
  staff: Member[]
  invitations: Invitation[]
  roles: { value: string; label: string; capabilities: string[] }[]
  capabilities: { value: string; label: string }[]
}>()

const inviting = ref(false)
const form = useForm({ name: '', email: '', role: 'contributor' })

function invite() {
  form.post('/team/einladungen', {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      inviting.value = false
    },
  })
}

const transferring = ref<Member | null>(null)
const transferForm = useForm({ current_password: '' })

function transfer() {
  transferForm.post(`/team/${transferring.value!.id}/inhaber`, {
    preserveScroll: true,
    onSuccess: () => {
      transferForm.reset()
      transferring.value = null
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
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('team.title')">
          <!-- Three fields and you are done — the page behind is the roster you
               are adding to, and it should still be there when you look up. -->
          <Dialog v-model:open="inviting">
            <DialogTrigger as-child>
              <Button class="ml-auto" variant="pill" type="button">{{ $t('team.invite') }}</Button>
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
        </PageHead>

        <div class="max-w-[900px] px-8 pt-7 pb-8">
          <section v-if="invitations.length" class="mb-7">
            <h2 class="m-0 mb-[9px] text-small leading-none font-semibold tracking-[0.07em] uppercase text-ink-2">{{ $t('team.pending') }}</h2>
            <div
              v-for="invitation in invitations"
              :key="invitation.id"
              class="flex items-center gap-3 py-2.5 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none"
            >
              <span class="flex size-8 flex-none items-center justify-center rounded-full bg-sunk-2 text-small leading-none font-semibold text-ink-2">{{ initials(invitation.name) }}</span>
              <span class="min-w-0 flex-1">
                <span class="block text-ui leading-[1.35] font-semibold tracking-[-0.015em]">{{ invitation.name }}</span>
                <span class="mt-px block text-small leading-[1.35] text-ink-2">{{ invitation.email }} · {{ invitation.roleLabel }}</span>
              </span>
              <span class="flex-none text-small tabular-nums text-ink-2">{{ $t('team.expires', { date: invitation.expiresOn }) }}</span>
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
          </section>

          <section>
            <h2 class="m-0 mb-[9px] text-small leading-none font-semibold tracking-[0.07em] uppercase text-ink-2">{{ $t('team.members') }}</h2>
            <div
              v-for="member in staff"
              :key="member.id"
              class="flex items-center gap-3 py-2.5 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none"
            >
              <span class="flex size-8 flex-none items-center justify-center rounded-full bg-sunk-2 text-small leading-none font-semibold text-ink-2">{{ initials(member.name) }}</span>
              <span class="min-w-0 flex-1">
                <span class="block text-ui leading-[1.35] font-semibold tracking-[-0.015em]">{{ member.name }}</span>
                <span class="mt-px block text-small leading-[1.35] text-ink-2">{{ member.email }}</span>
              </span>
              <span class="inline-flex items-center rounded-[var(--r-pill)] bg-sunk-2 px-2.5 py-1 text-small leading-none font-medium whitespace-nowrap text-ink-2">{{ member.roleLabel }}</span>
              <!-- An account without the second factor cannot reach anything, so
                   the list says so rather than looking complete. -->
              <span
                class="flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-small leading-[1.5] font-semibold tracking-[0.04em] uppercase"
                :class="member.mfaConfirmed ? 'bg-ok-bg text-ok' : 'bg-sunk-2 text-faint'"
              >
                {{ member.mfaConfirmed ? $t('team.mfaOn') : $t('team.mfaPending') }}
              </span>
              <Button v-if="member.transferable" type="button" variant="outline" @click="transferring = member">{{ $t('team.transfer') }}</Button>
            </div>
          </section>

          <Dialog :open="transferring !== null" @update:open="(open) => { if (!open) transferring = null }">
            <DialogContent>
              <DialogHeader>
                <DialogTitle>{{ $t('team.transferTitle', { name: transferring?.name }) }}</DialogTitle>
              </DialogHeader>

              <DialogBody>
                <form id="team-transfer-form" @submit.prevent="transfer">
                  <Field id="transfer-password" :label="$t('profile.currentPassword')" :error="transferForm.errors.current_password">
                    <Input id="transfer-password" v-model="transferForm.current_password" type="password" autocomplete="current-password" required />
                  </Field>
                </form>
              </DialogBody>

              <DialogFooter>
                <Button type="submit" form="team-transfer-form" :disabled="transferForm.processing">{{ $t('team.transferConfirm') }}</Button>
                <DialogClose as-child>
                  <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
                </DialogClose>
              </DialogFooter>
            </DialogContent>
          </Dialog>

          <section class="mt-7">
            <h2 class="m-0 text-small leading-none font-semibold tracking-[0.07em] uppercase text-ink-2">{{ $t('team.permissions') }}</h2>
            <Table class="mt-3 [&_td]:px-3 [&_td:first-child]:pl-0 [&_th]:px-3 [&_th:first-child]:pl-0">
              <thead>
                <tr>
                  <TableHead />
                  <TableHead v-for="role in roles" :key="role.value" class="text-center">
                    {{ role.label }}
                    <span class="ml-1 font-normal tabular-nums">{{ staff.filter((member) => member.role === role.value).length }}</span>
                  </TableHead>
                </tr>
              </thead>
              <tbody>
                <TableRow v-for="capability in capabilities" :key="capability.value">
                  <TableCell>{{ capability.label }}</TableCell>
                  <TableCell v-for="role in roles" :key="role.value" class="text-center align-middle">
                    <Check v-if="role.capabilities.includes(capability.value)" class="inline size-4 stroke-[1.8]" :aria-label="$t('team.allowed')" />
                  </TableCell>
                </TableRow>
              </tbody>
            </Table>
          </section>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
