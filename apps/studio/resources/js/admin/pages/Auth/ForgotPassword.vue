<script setup lang="ts">
import { computed } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'

const form = useForm({ email: '' })
const status = computed(() => usePage().props.flash.status)

function submit() {
  form.post('/passwort/vergessen')
}
</script>

<template>
  <div class="authwrap">
    <div class="authcard">
      <span class="brand">Nordlicht</span>
      <h1>{{ $t('reset.requestTitle') }}</h1>
      <p class="hint" style="margin-bottom: var(--s5)">{{ $t('reset.requestHint') }}</p>

      <!-- The same message whether or not the address has an account: a
           differing one tells a stranger which addresses exist. -->
      <p v-if="status" class="flag" style="margin-bottom: var(--s4)">{{ status }}</p>

      <form @submit.prevent="submit">
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
          <p v-if="form.errors.email" class="err">{{ form.errors.email }}</p>
        </div>

        <button class="btn" type="submit" style="width: 100%; justify-content: center" :disabled="form.processing">
          {{ $t('reset.send') }}
        </button>
      </form>

      <p style="margin-top: var(--s4); text-align: center">
        <Link class="txtbtn" href="/login">{{ $t('reset.backToLogin') }}</Link>
      </p>
    </div>
  </div>
</template>
