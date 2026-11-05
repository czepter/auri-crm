<script setup lang="ts">
import { Link } from "@inertiajs/vue3";

defineProps<{ items: { key: string; label: string; href?: string }[] }>();
defineEmits<{ pick: [key: string] }>();
</script>

<template>
    <div v-if="items.length" class="mt-3 flex flex-wrap items-center text-small text-ink-2">
        <template v-for="(item, index) in items" :key="item.key">
            <span v-if="index" class="px-1" aria-hidden="true">·</span>
            <Link v-if="item.href" class="py-2 text-ink-2 max-[640px]:py-3.5 no-underline hover:text-foreground" :href="item.href">{{ item.label }}</Link>
            <button v-else class="cursor-pointer py-2 max-[640px]:py-3.5 hover:text-foreground" type="button" @click="$emit('pick', item.key)">
                {{ item.label }}
            </button>
        </template>
    </div>
</template>
