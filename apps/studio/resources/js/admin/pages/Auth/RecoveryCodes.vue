<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/ui/card'

defineProps<{ codes: string[] }>()
</script>

<template>
  <main class="flex min-h-screen items-center justify-center p-6">
    <Card class="w-full max-w-md">
      <CardHeader>
        <CardTitle>Recovery codes</CardTitle>
        <CardDescription>
          Store these somewhere safe. They are shown once and each works a single time.
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-4">
        <ul class="grid grid-cols-2 gap-2 rounded-md bg-muted p-3 font-mono text-sm">
          <li v-for="code in codes" :key="code">{{ code }}</li>
        </ul>

        <Button class="w-full" @click="router.visit('/')">I have saved these</Button>
      </CardContent>
    </Card>
  </main>
</template>
