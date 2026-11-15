<script setup lang="ts">
import { Button } from '@/ui/button'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { X } from 'lucide-vue-next'

defineProps<{
  profiles: { platform: string; value: string }[]
  platforms: { value: string; label: string }[]
  errors: Record<string, string | undefined>
}>()
</script>

<template>
  <div v-for="(profile, index) in profiles" :key="index" class="flex items-start gap-2">
    <Select v-model="profile.platform">
      <SelectTrigger class="w-40" :aria-label="$t('contacts.platform')"><SelectValue /></SelectTrigger>
      <SelectContent>
        <SelectItem v-for="one in platforms" :key="one.value" :value="one.value">{{ one.label }}</SelectItem>
      </SelectContent>
    </Select>
    <Field :id="`c-profile-${index}`" class="flex-1" :error="errors[`social_profiles.${index}.value`]">
      <Input :id="`c-profile-${index}`" v-model="profile.value" type="text" :placeholder="$t('contacts.profileValue')" :aria-label="$t('contacts.profileValue')" />
    </Field>
    <button
      :aria-label="$t('common.remove')"
      class="inline-flex cursor-pointer border-0 bg-transparent p-2 text-ink-2"
      type="button"
      @click="profiles.splice(index, 1)"
    >
      <X class="size-4" />
    </button>
  </div>
  <div>
    <Button
      variant="pill-muted"
      type="button"
      :disabled="profiles.length >= 10"
      @click="profiles.push({ platform: 'instagram', value: '' })"
    >
      {{ $t('contacts.addProfile') }}
    </Button>
  </div>
</template>
