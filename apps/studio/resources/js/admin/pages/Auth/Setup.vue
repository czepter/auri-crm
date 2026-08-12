<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' })

function submit() {
  form.post('/einrichtung', { onFinish: () => form.reset('password', 'password_confirmation') })
}
</script>

<template>
  <div class="authwrap">
    <div class="authcard">
      <span class="brand">Nordlicht</span>
      <h1>{{ $t('setup.title') }}</h1>
      <p class="hint" style="margin-bottom: var(--s5)">{{ $t('setup.hint') }}</p>

      <form @submit.prevent="submit">
        <div class="fld grp">
          <label for="name">{{ $t('setup.name') }}</label>
          <input id="name" v-model="form.name" class="inp" type="text" autocomplete="name" required />
          <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
        </div>

        <div class="fld grp">
          <label for="email">{{ $t('setup.email') }}</label>
          <input id="email" v-model="form.email" class="inp" type="email" autocomplete="email" required />
          <p v-if="form.errors.email" class="err">{{ form.errors.email }}</p>
        </div>

        <div class="fld grp">
          <label for="password">{{ $t('setup.password') }}</label>
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
          <label for="password_confirmation">{{ $t('setup.passwordConfirm') }}</label>
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
          {{ $t('setup.submit') }}
        </button>
      </form>
    </div>
  </div>
</template>
