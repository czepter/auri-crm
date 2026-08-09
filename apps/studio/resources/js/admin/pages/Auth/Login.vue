<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3'

// Only ever true in local. The server decides; the button cannot talk itself
// into existence.
defineProps<{ devLogin?: boolean }>()

const form = useForm({ email: '', password: '' })

function submit() {
  form.post('/login', { onFinish: () => form.reset('password') })
}
</script>

<template>
  <!-- No sidebar: there is no navigation available to someone not signed in. -->
  <div class="authwrap">
    <div class="authcard">
      <span class="brand">Nordlicht</span>
      <h1>{{ $t('auth.signInTitle') }}</h1>
      <p class="hint" style="margin-bottom: var(--s5)">{{ $t('auth.signInHint') }}</p>

      <form @submit.prevent="submit">
        <p v-if="form.errors.email" class="flag" style="margin-bottom: var(--s4)">
          {{ form.errors.email }}
        </p>

        <div class="fld grp">
          <label for="email">{{ $t('auth.email') }}</label>
          <input
            id="email"
            v-model="form.email"
            class="inp"
            type="email"
            autocomplete="username"
            required
          />
        </div>

        <div class="fld grp">
          <label for="password">{{ $t('auth.password') }}</label>
          <input
            id="password"
            v-model="form.password"
            class="inp"
            type="password"
            autocomplete="current-password"
            required
          />
        </div>

        <button class="btn" type="submit" style="width: 100%; justify-content: center" :disabled="form.processing">
          {{ $t('auth.signIn') }}
        </button>
      </form>

      <p style="margin-top: var(--s4); text-align: center">
        <Link class="txtbtn" href="/passwort/vergessen">{{ $t('auth.forgot') }}</Link>
      </p>

      <div v-if="devLogin" style="margin-top: var(--s5); padding-top: var(--s4); border-top: 1px solid var(--line)">
        <button
          class="abtn"
          type="button"
          style="width: 100%; padding: 9px"
          @click="router.post('/dev-login')"
        >
          {{ $t('auth.devLogin') }}
        </button>
        <p class="hint" style="margin-top: var(--s2); text-align: center">
          {{ $t('auth.devLoginHint') }}
        </p>
      </div>
    </div>
  </div>
</template>
