<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' })

function submit() {
  form.post('/einrichtung', { onFinish: () => form.reset('password', 'password_confirmation') })
}
</script>

<template>
  <div class="authwrap">
    <div class="authcard">
      <span class="brand">{{ $page.props.brand }}</span>
      <h1>{{ $t('setup.title') }}</h1>
      <p class="hint" style="margin-bottom: var(--s5)">{{ $t('setup.hint') }}</p>

      <form @submit.prevent="submit">
        <div class="fld grp">
          <Label for="name">{{ $t('setup.name') }}</Label>
          <Input id="name" v-model="form.name" type="text" autocomplete="name" required />
          <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
        </div>

        <div class="fld grp">
          <Label for="email">{{ $t('setup.email') }}</Label>
          <Input id="email" v-model="form.email" type="email" autocomplete="email" required />
          <p v-if="form.errors.email" class="err">{{ form.errors.email }}</p>
        </div>

        <div class="fld grp">
          <Label for="password">{{ $t('setup.password') }}</Label>
          <Input
            id="password"
            v-model="form.password"
            type="password"
            autocomplete="new-password"
            required />
          <p v-if="form.errors.password" class="err">{{ form.errors.password }}</p>
        </div>

        <div class="fld grp">
          <Label for="password_confirmation">{{ $t('setup.passwordConfirm') }}</Label>
          <Input
            id="password_confirmation"
            v-model="form.password_confirmation"
            type="password"
            autocomplete="new-password"
            required />
        </div>

        <Button type="submit" style="width: 100%; justify-content: center" :disabled="form.processing">
          {{ $t('setup.submit') }}
        </Button>
      </form>
    </div>
  </div>
</template>
