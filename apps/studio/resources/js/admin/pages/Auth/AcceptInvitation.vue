<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

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
          <label for="password">{{ $t('invite.password') }}</label>
          <input
            id="password"
            v-model="form.password"
            class="inp"
            type="password"
            autocomplete="new-password"
            required
          />
          <p v-if="form.errors.password" class="err">{{ form.errors.password }}</p>
        </div>

        <div class="fld grp">
          <label for="password_confirmation">{{ $t('invite.passwordConfirm') }}</label>
          <input
            id="password_confirmation"
            v-model="form.password_confirmation"
            class="inp"
            type="password"
            autocomplete="new-password"
            required
          />
        </div>

        <!-- Said before they meet it, because an authenticator app arriving
             unannounced is where people abandon a setup link. -->
        <p class="hint" style="margin-bottom: var(--s4)">{{ $t('invite.mfaNext') }}</p>

        <button class="btn" type="submit" style="width: 100%; justify-content: center" :disabled="form.processing">
          {{ $t('invite.submit') }}
        </button>
      </form>
    </div>
  </div>
</template>
