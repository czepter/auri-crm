<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Alert, AlertDescription } from '@/ui/alert'
import { Button } from '@/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/ui/card'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

const form = useForm({ email: '', password: '' })

function submit() {
  form.post('/login', { onFinish: () => form.reset('password') })
}
</script>

<template>
  <main class="flex min-h-screen items-center justify-center p-6">
    <Card class="w-full max-w-sm">
      <CardHeader>
        <CardTitle>{{ $t('auth.signInTitle') }}</CardTitle>
        <CardDescription>{{ $t('auth.signInHint') }}</CardDescription>
      </CardHeader>
      <CardContent>
        <form class="space-y-4" @submit.prevent="submit">
          <Alert v-if="form.errors.email" variant="destructive">
            <AlertDescription>{{ form.errors.email }}</AlertDescription>
          </Alert>

          <div class="space-y-2">
            <Label for="email">{{ $t('auth.email') }}</Label>
            <Input
              id="email"
              v-model="form.email"
              type="email"
              autocomplete="username"
              required
            />
          </div>

          <div class="space-y-2">
            <Label for="password">{{ $t('auth.password') }}</Label>
            <Input
              id="password"
              v-model="form.password"
              type="password"
              autocomplete="current-password"
              required
            />
          </div>

          <Button type="submit" class="w-full" :disabled="form.processing">{{ $t('auth.signIn') }}</Button>
        </form>
      </CardContent>
    </Card>
  </main>
</template>
