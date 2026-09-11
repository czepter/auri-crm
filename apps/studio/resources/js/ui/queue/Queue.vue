<script setup lang="ts">
/*
 * The pane beside the case: which inquiries are open, in the group each one
 * is in. Wider than the trace, because a row carries two lines of what they
 * wrote — the lines that decide whether it is worth opening.
 */
</script>

<template>
    <nav
        class="sticky top-0 h-screen w-72 flex-none overflow-y-auto bg-sunk pt-3.5 max-[1080px]:w-[200px] max-[640px]:static max-[640px]:h-auto"
    >
        <slot />
    </nav>
</template>
