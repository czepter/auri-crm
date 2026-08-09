<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

const props = defineProps<{ token: string; email: string | null }>()

const form = useForm({
  token: props.token,
  email: props.email ?? '',
  password: '',
  password_confirmation: '',
})

function submit() {
  form.post('/passwort/neu', {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}
</script>

<template>
  <div class="authwrap">
    <div class="authcard">
      <span class="brand">Nordlicht</span>
      <h1>{{ $t('reset.newTitle') }}</h1>
      <!-- Stated up front: this changes the password and nothing else. The
           second factor is still required to get in. -->
      <p class="hint" style="margin-bottom: var(--s5)">{{ $t('reset.newHint') }}</p>

      <form @submit.prevent="submit">
        <p v-if="form.errors.email" class="flag" style="margin-bottom: var(--s4)">
          {{ form.errors.email }}
        </p>

        <div class="fld grp">
          <label for="email">{{ $t('auth.email') }}</label>
          <input id="email" v-model="form.email" class="inp" type="email" autocomplete="username" required />
        </div>

        <div class="fld grp">
          <label for="password">{{ $t('reset.password') }}</label>
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

        <button class="btn" type="submit" style="width: 100%; justify-content: center" :disabled="form.processing">
          {{ $t('reset.submit') }}
        </button>
      </form>
    </div>
  </div>
</template>
