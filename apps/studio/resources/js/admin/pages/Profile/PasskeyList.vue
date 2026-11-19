<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'
import FormSection from '@/ui/page/FormSection.vue'
import { Button } from '@/ui/button'
import { Dialog, DialogBody, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/ui/dialog'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { xsrfHeader } from '@/admin/lib/multipartUpload'
import { ceremonyCancelled, passkeysSupported } from '@/admin/lib/passkey'

type Passkey = { id: string; name: string; createdAt: string; lastUsedAt: string | null }

defineProps<{ passkeys: Passkey[] }>()

const { t } = useI18n()

const supported = ref(false)
onMounted(() => { supported.value = passkeysSupported() })

const adding = ref(false)
const addForm = useForm({ name: '', current_password: '' })

const deleting = ref<Passkey | null>(null)
const deleteForm = useForm({ current_password: '' })

function setAdding(open: boolean) {
  adding.value = open

  if (!open) {
    addForm.reset()
    addForm.clearErrors()
  }
}

function closeDelete() {
  deleting.value = null
  deleteForm.reset()
  deleteForm.clearErrors()
}

function date(iso: string) {
  return new Date(iso).toLocaleDateString('de-DE')
}

async function add() {
  addForm.clearErrors()

  try {
    const response = await fetch('/profil/passkeys/optionen', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...xsrfHeader() },
      credentials: 'same-origin',
      body: JSON.stringify({ current_password: addForm.current_password }),
    })

    if (response.status === 422) {
      const { errors } = (await response.json()) as { errors: Record<string, string[]> }
      addForm.setError(Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0]])))

      return
    }

    if (!response.ok) {
      throw new Error(String(response.status))
    }

    const credential = (await navigator.credentials.create({
      publicKey: PublicKeyCredential.parseCreationOptionsFromJSON(await response.json()),
    })) as PublicKeyCredential

    addForm
      .transform((data) => ({ name: data.name, credential: JSON.stringify(credential.toJSON()) }))
      .post('/profil/passkeys', {
        preserveScroll: true,
        onSuccess: () => {
          setAdding(false)
        },
        onError: (errors) => {
          if (errors.credential) {
            toast.error(t('profile.passkeyFailed'))
          }
        },
      })
  } catch (error) {
    if (!ceremonyCancelled(error)) {
      toast.error(t('profile.passkeyFailed'))
    }
  }
}

function remove() {
  deleteForm.delete(`/profil/passkeys/${deleting.value!.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      closeDelete()
    },
  })
}
</script>

<template>
  <FormSection v-if="passkeys.length || supported" :title="$t('profile.passkeysSection')">
    <div
      v-for="passkey in passkeys"
      :key="passkey.id"
      class="flex items-center gap-3 py-2.5 shadow-[inset_0_1px_0_var(--rule)] first-of-type:shadow-none"
    >
      <span class="min-w-0 flex-1">
        <span class="block truncate text-ui leading-[1.35] font-semibold tracking-[-0.015em]">{{ passkey.name }}</span>
        <span class="mt-px block text-small leading-[1.35] text-ink-2">{{ [
          $t('profile.passkeyCreated', { date: date(passkey.createdAt) }),
          passkey.lastUsedAt ? $t('profile.passkeyLastUsed', { date: date(passkey.lastUsedAt) }) : null,
        ].filter(Boolean).join(' · ') }}</span>
      </span>
      <Button type="button" variant="outline" tone="accent" :aria-label="$t('profile.passkeyDeleteTitle', { name: passkey.name })" @click="deleting = passkey">{{ $t('common.delete') }}</Button>
    </div>

    <Dialog :open="adding" @update:open="setAdding">
      <DialogTrigger as-child>
        <Button v-if="supported" type="button" variant="outline" class="mt-4">{{ $t('profile.passkeyAdd') }}</Button>
      </DialogTrigger>

      <DialogContent>
        <DialogHeader>
          <DialogTitle>{{ $t('profile.passkeyAdd') }}</DialogTitle>
        </DialogHeader>

        <DialogBody>
          <form id="passkey-add-form" class="grid gap-4" @submit.prevent="add">
            <Field id="passkey-name" :label="$t('profile.passkeyName')" :error="addForm.errors.name">
              <Input id="passkey-name" v-model="addForm.name" type="text" maxlength="100" required />
            </Field>
            <Field id="passkey-add-password" :label="$t('profile.currentPassword')" :error="addForm.errors.current_password">
              <Input id="passkey-add-password" v-model="addForm.current_password" type="password" autocomplete="current-password" required />
            </Field>
          </form>
        </DialogBody>

        <DialogFooter>
          <Button class="ml-auto" type="submit" form="passkey-add-form" :disabled="addForm.processing">{{ $t('profile.passkeyAdd') }}</Button>
          <DialogClose as-child>
            <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
          </DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog :open="deleting !== null" @update:open="(open) => { if (!open) closeDelete() }">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{{ $t('profile.passkeyDeleteTitle', { name: deleting?.name }) }}</DialogTitle>
        </DialogHeader>

        <DialogBody>
          <form id="passkey-delete-form" @submit.prevent="remove">
            <Field id="passkey-delete-password" :label="$t('profile.currentPassword')" :error="deleteForm.errors.current_password">
              <Input id="passkey-delete-password" v-model="deleteForm.current_password" type="password" autocomplete="current-password" required />
            </Field>
          </form>
        </DialogBody>

        <DialogFooter>
          <Button class="ml-auto" type="submit" tone="destructive" form="passkey-delete-form" :disabled="deleteForm.processing">{{ $t('common.delete') }}</Button>
          <DialogClose as-child>
            <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
          </DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </FormSection>
</template>
