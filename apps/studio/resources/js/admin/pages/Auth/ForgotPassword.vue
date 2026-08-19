<script setup lang="ts">
import { computed } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

const form = useForm({ email: '' })
const status = computed(() => usePage().props.flash.status)

function submit() {
  form.post('/passwort/vergessen')
}
</script>

<template>
  <div class="authwrap">
    <div class="authcard">
      <span class="brand">{{ $page.props.brand }}</span>
      <h1>{{ $t('reset.requestTitle') }}</h1>

      <!-- The same message whether or not the address has an account: a
           differing one tells a stranger which addresses exist. -->
      <p v-if="status" class="flag" style="margin-bottom: var(--s4)">{{ status }}</p>

      <form @submit.prevent="submit">
        <div class="fld grp">
          <Label for="email">{{ $t('auth.email') }}</Label>
          <Input
            id="email"
            v-model="form.email"
            type="email"
            autocomplete="username"
            required />
          <p v-if="form.errors.email" class="err">{{ form.errors.email }}</p>
        </div>

        <Button type="submit" style="width: 100%; justify-content: center" :disabled="form.processing">
          {{ $t('reset.send') }}
        </Button>
      </form>

      <p style="margin-top: var(--s4); text-align: center">
        <Button :as="Link" variant="ghost" href="/login">{{ $t('reset.backToLogin') }}</Button>
      </p>
    </div>
  </div>
</template>
