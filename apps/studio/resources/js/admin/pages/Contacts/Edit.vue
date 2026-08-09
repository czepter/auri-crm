<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

const props = defineProps<{ contact: App.Crm.Data.ContactSuggestionData }>()

const form = useForm({
  name: props.contact.name,
  email: props.contact.email ?? '',
  phone: props.contact.phone ?? '',
})

function submit() {
  form.patch(`/kontakte/${props.contact.id}`)
}
</script>

<template>
  <main class="mx-auto max-w-lg space-y-6 p-6">
    <h1 class="text-2xl font-semibold tracking-tight">{{ $t('contacts.editTitle') }}</h1>

    <form class="space-y-4" @submit.prevent="submit">
      <div class="space-y-2">
        <Label for="name">{{ $t('contacts.name') }}</Label>
        <Input id="name" v-model="form.name" />
        <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
      </div>
      <div class="space-y-2">
        <Label for="email">{{ $t('contacts.email') }}</Label>
        <Input id="email" v-model="form.email" type="email" />
        <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
      </div>
      <div class="space-y-2">
        <Label for="phone">{{ $t('contacts.phone') }}</Label>
        <Input id="phone" v-model="form.phone" type="tel" />
      </div>

      <div class="flex items-center gap-4">
        <Button type="submit" :disabled="form.processing">{{ $t('common.save') }}</Button>
        <a href="/kontakte" class="text-sm underline">{{ $t('common.cancel') }}</a>
      </div>
    </form>
  </main>
</template>
