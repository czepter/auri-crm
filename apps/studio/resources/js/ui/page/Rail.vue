<script setup lang="ts">
/*
 * crm.css .rail — the trace beside the case: the week around the shoot, who
 * is on it, what hangs off it. Below 1080px it goes: the trace is what one
 * reads next to the work, not instead of it.
 */
</script>

<template>
    <aside
        class="w-70 flex-none overflow-y-auto bg-sunk px-5 py-4 max-[1080px]:hidden"
    >
        <slot />
    </aside>
</template>
