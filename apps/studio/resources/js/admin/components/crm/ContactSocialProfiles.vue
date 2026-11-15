<script setup lang="ts">
import { watch } from 'vue'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { profileHref, profileText, withOpenRow, type SocialProfile } from '@/admin/lib/socialProfile'
import { ExternalLink, X } from 'lucide-vue-next'

const props = defineProps<{
  profiles: SocialProfile[]
  platforms: { value: string; label: string }[]
  errors: Record<string, string | undefined>
}>()

watch(() => props.profiles.map((profile) => profile.value), () => {
  const open = withOpenRow(props.profiles)

  if (open !== props.profiles) {
    props.profiles.push(open[open.length - 1])
  }
})

const isOpenRow = (index: number) => index === props.profiles.length - 1 && props.profiles[index].value.trim() === ''
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
    <a
      v-if="!isOpenRow(index)"
      class="inline-flex p-2 text-ink-2 hover:text-foreground"
      :href="profileHref(profile.platform, profile.value)"
      :aria-label="profileText(profile.platform, profile.value)"
      rel="noopener noreferrer"
      target="_blank"
    >
      <ExternalLink class="size-4" />
    </a>
    <button
      :aria-label="$t('common.remove')"
      class="inline-flex cursor-pointer border-0 bg-transparent p-2 text-ink-2"
      :class="{ invisible: isOpenRow(index) }"
      :tabindex="isOpenRow(index) ? -1 : undefined"
      type="button"
      @click="profiles.splice(index, 1)"
    >
      <X class="size-4" />
    </button>
  </div>
</template>
