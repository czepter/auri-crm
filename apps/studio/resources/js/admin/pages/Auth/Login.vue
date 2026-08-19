<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

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
      <span class="brand">{{ $page.props.brand }}</span>
      <h1>{{ $t('auth.signInTitle') }}</h1>

      <form @submit.prevent="submit">
        <p v-if="form.errors.email" class="flag" style="margin-bottom: var(--s4)">
          {{ form.errors.email }}
        </p>

        <div class="fld grp">
          <Label for="email">{{ $t('auth.email') }}</Label>
          <Input
            id="email"
            v-model="form.email"
            type="email"
            autocomplete="username"
            required />
        </div>

        <div class="fld grp">
          <Label for="password">{{ $t('auth.password') }}</Label>
          <Input
            id="password"
            v-model="form.password"
            type="password"
            autocomplete="current-password"
            required />
        </div>

        <Button type="submit" style="width: 100%; justify-content: center" :disabled="form.processing">
          {{ $t('auth.signIn') }}
        </Button>
      </form>

      <p style="margin-top: var(--s4); text-align: center">
        <Button :as="Link" variant="ghost" href="/passwort/vergessen">{{ $t('auth.forgot') }}</Button>
      </p>

      <div v-if="devLogin" style="margin-top: var(--s5); padding-top: var(--s4); border-top: 1px solid var(--line)">
        <Button variant="outline"
          type="button"
          style="width: 100%; padding: 9px"
          @click="router.post('/dev-login')"
        >
          {{ $t('auth.devLogin') }}
        </Button>
        <p class="hint" style="margin-top: var(--s2); text-align: center">
          {{ $t('auth.devLoginHint') }}
        </p>
      </div>
    </div>
  </div>
</template>
