<script setup lang="ts">
import { ArrowLeft } from 'lucide-vue-next'
import { Button } from '@/ui/button'

defineProps<{ href?: string }>()
</script>

<template>
  <Button :as="href ? 'a' : 'button'" :href="href" :type="href ? undefined : 'button'" variant="outline">
    <ArrowLeft />
    {{ $t('common.back') }}
  </Button>
</template>
