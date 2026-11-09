<script setup lang="ts">
/*
 * The pane beside the case: which inquiries are open, in the group each one
 * is in. Wider than the trace, because a row carries two lines of what they
 * wrote — the lines that decide whether it is worth opening.
 */
</script>

<template>
    <nav
        class="sticky top-0 h-screen w-60 flex-none min-[1600px]:w-72 overflow-y-auto bg-background px-2 pt-3.5 max-[1080px]:w-[200px] max-[640px]:static max-[640px]:h-auto"
    >
        <slot />
    </nav>
</template>
