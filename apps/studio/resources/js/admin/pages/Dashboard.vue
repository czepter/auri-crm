<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { Button } from '@/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/ui/card'

defineProps<{ leads: number; jobs: number }>()

// The dashboard is behind audience:staff, so the shared user is never null here.
const user = computed(() => usePage().props.auth.user!)
</script>

<template>
  <main class="mx-auto max-w-4xl space-y-6 p-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
      <Button variant="secondary" @click="router.post('/logout')">Sign out</Button>
    </div>

    <p class="text-sm text-muted-foreground">Signed in as {{ user.name }}</p>

    <div class="grid gap-4 sm:grid-cols-2">
      <Card>
        <CardHeader><CardTitle class="text-sm font-medium">Open leads</CardTitle></CardHeader>
        <CardContent class="text-3xl font-semibold tabular-nums">{{ leads }}</CardContent>
      </Card>
      <Card>
        <CardHeader><CardTitle class="text-sm font-medium">Active jobs</CardTitle></CardHeader>
        <CardContent class="text-3xl font-semibold tabular-nums">{{ jobs }}</CardContent>
      </Card>
    </div>
  </main>
</template>
