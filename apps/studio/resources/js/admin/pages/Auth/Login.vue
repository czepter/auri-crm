<script setup lang="ts">
import { Button } from '@/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/ui/card'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
</script>

<template>
  <main class="flex min-h-screen items-center justify-center p-6">
    <Card class="w-full max-w-sm">
      <CardHeader>
        <CardTitle>Sign in</CardTitle>
        <CardDescription>Staff access to the studio.</CardDescription>
      </CardHeader>
      <CardContent>
        <form class="space-y-4">
          <div class="space-y-2">
            <Label for="email">Email</Label>
            <Input id="email" type="email" autocomplete="username" required />
          </div>

          <div class="space-y-2">
            <Label for="password">Password</Label>
            <Input id="password" type="password" autocomplete="current-password" required />
          </div>

          <Button type="submit" class="w-full">Sign in</Button>
        </form>
      </CardContent>
    </Card>
  </main>
</template>
