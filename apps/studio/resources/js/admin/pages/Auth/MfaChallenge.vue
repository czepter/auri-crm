<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Alert, AlertDescription } from '@/ui/alert'
import { Button } from '@/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/ui/card'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

const form = useForm({ code: '' })

function submit() {
  form.post('/auth/mfa', { onFinish: () => form.reset('code') })
}
</script>

<template>
  <main class="flex min-h-screen items-center justify-center p-6">
    <Card class="w-full max-w-sm">
      <CardHeader>
        <CardTitle>Two-factor authentication</CardTitle>
        <CardDescription>Enter the code from your authenticator app.</CardDescription>
      </CardHeader>
      <CardContent>
        <form class="space-y-4" @submit.prevent="submit">
          <Alert v-if="form.errors.code" variant="destructive">
            <AlertDescription>{{ form.errors.code }}</AlertDescription>
          </Alert>

          <div class="space-y-2">
            <Label for="code">Authentication code</Label>
            <Input
              id="code"
              v-model="form.code"
              inputmode="numeric"
              autocomplete="one-time-code"
              required
            />
          </div>

          <Button type="submit" class="w-full" :disabled="form.processing">Verify</Button>
        </form>
      </CardContent>
    </Card>
  </main>
</template>
