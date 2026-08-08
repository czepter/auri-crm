<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Alert, AlertDescription } from '@/ui/alert'
import { Button } from '@/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/ui/card'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

defineProps<{ secret: string; otpauthUri: string }>()

const form = useForm({ code: '' })

function submit() {
  form.post('/auth/mfa/confirm', { onFinish: () => form.reset('code') })
}
</script>

<template>
  <main class="flex min-h-screen items-center justify-center p-6">
    <Card class="w-full max-w-md">
      <CardHeader>
        <CardTitle>Set up two-factor authentication</CardTitle>
        <CardDescription>Required for every staff account.</CardDescription>
      </CardHeader>
      <CardContent class="space-y-4">
        <p class="text-sm text-muted-foreground">
          Add this key to your authenticator app, then enter the six-digit code it shows.
        </p>

        <code class="block rounded-md bg-muted px-3 py-2 font-mono text-sm tracking-widest">
          {{ secret }}
        </code>

        <a class="text-sm underline underline-offset-4" :href="otpauthUri">
          Open in an authenticator app
        </a>

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

          <Button type="submit" class="w-full" :disabled="form.processing">Confirm</Button>
        </form>
      </CardContent>
    </Card>
  </main>
</template>
