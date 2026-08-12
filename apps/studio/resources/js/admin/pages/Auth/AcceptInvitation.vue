<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

const props = defineProps<{
  name: string
  email: string
  roleLabel: string
  token: string
}>()

const form = useForm({ password: '', password_confirmation: '' })

function submit() {
  form.post(`/einladung/${props.token}`, {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}
</script>

<template>
  <div class="authwrap">
    <div class="authcard">
      <span class="brand">Nordlicht</span>
      <h1>{{ $t('invite.title', { name }) }}</h1>
      <p class="hint" style="margin-bottom: var(--s5)">
        {{ $t('invite.hint', { email, role: roleLabel }) }}
      </p>

      <form @submit.prevent="submit">
        <div class="fld grp">
          <Label for="password">{{ $t('invite.password') }}</Label>
          <Input
            id="password"
            v-model="form.password"
            type="password"
            autocomplete="new-password"
            required />
          <p v-if="form.errors.password" class="err">{{ form.errors.password }}</p>
        </div>

        <div class="fld grp">
          <Label for="password_confirmation">{{ $t('invite.passwordConfirm') }}</Label>
          <Input
            id="password_confirmation"
            v-model="form.password_confirmation"
            type="password"
            autocomplete="new-password"
            required />
        </div>

        <!-- Said before they meet it, because an authenticator app arriving
             unannounced is where people abandon a setup link. -->
        <p class="hint" style="margin-bottom: var(--s4)">{{ $t('invite.mfaNext') }}</p>

        <Button type="submit" style="width: 100%; justify-content: center" :disabled="form.processing">
          {{ $t('invite.submit') }}
        </Button>
      </form>
    </div>
  </div>
</template>
