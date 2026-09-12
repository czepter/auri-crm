<script setup lang="ts">
import { computed } from 'vue'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

const props = defineProps<{ quoteId: string; recipientName: string | null }>()
const street = defineModel<string | null>('street', { required: true })
const postcode = defineModel<string | null>('postcode', { required: true })
const city = defineModel<string | null>('city', { required: true })
const emit = defineEmits<{ change: [] }>()

// Input carries a string, the draft carries a nullable one — the empty
// string is the only value that needs translating between the two.
function field(model: typeof street) {
  return computed({
    get: () => model.value ?? '',
    set: (value: string) => (model.value = value === '' ? null : value),
  })
}

const streetField = field(street)
const postcodeField = field(postcode)
const cityField = field(city)
</script>

<template>
  <div class="flex flex-col gap-4">
    <p v-if="recipientName" class="text-[13.5px] font-medium">{{ recipientName }}</p>

    <div class="grid grid-cols-[120px_1fr] gap-4">
      <div class="col-span-2 flex min-w-0 flex-col gap-1.5">
        <Label :for="`recipient-street-${props.quoteId}`">{{ $t('contacts.street') }}</Label>
        <Input :id="`recipient-street-${props.quoteId}`" v-model="streetField" type="text" @blur="emit('change')" />
      </div>
      <div class="flex min-w-0 flex-col gap-1.5">
        <Label :for="`recipient-postcode-${props.quoteId}`">{{ $t('contacts.postcode') }}</Label>
        <Input :id="`recipient-postcode-${props.quoteId}`" v-model="postcodeField" type="text" @blur="emit('change')" />
      </div>
      <div class="flex min-w-0 flex-col gap-1.5">
        <Label :for="`recipient-city-${props.quoteId}`">{{ $t('contacts.city') }}</Label>
        <Input :id="`recipient-city-${props.quoteId}`" v-model="cityField" type="text" @blur="emit('change')" />
      </div>
    </div>
  </div>
</template>
