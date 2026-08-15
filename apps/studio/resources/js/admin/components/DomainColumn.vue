<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Button } from '@/ui/button'

defineProps<{
  title: string
  count: string
  rows: App.Crm.Data.DomainRowData[]
  footer?: string
  footerHref?: string
}>()
</script>

<template>
  <!-- The mockup's .dom block, filled from any column's rows. Chips state,
       buttons act: the row opens the project, the action is the outline
       Button (admin-extra.css's retired .abtn, now ui/button) that dials or
       opens the screen where the decision is taken.

       `what` carries the row's own separators (a trailing "," or "—"; a
       leading "· " on whatTail) — the three fields are joined here with a
       single space and nothing else, so no punctuation is written twice.

       The join space is `{{ ' ' }}`, not a literal space in the markup:
       Vue's whitespace-condense compiler drops a bare whitespace-only text
       node sitting next to an element/`<template>` boundary even with no
       newline in the source, so a literal " " there silently compiles to
       nothing (confirmed against the compiled render output). A mustache
       is never touched by that pass, so it is the only space guaranteed to
       reach the DOM. -->
  <div class="dom">
    <div class="dh">
      <b>{{ title }}</b>
      <span class="n">{{ count }}</span>
    </div>

    <div v-for="(row, index) in rows" :key="`${row.projectId}-${index}`" class="dr">
      <Link class="who" :href="`/auftraege/${row.projectId}`">{{ row.who }}</Link>
      <span class="what">{{ row.what }}<template v-if="row.emphasis">{{ ' ' }}<i>{{ row.emphasis }}</i></template><template v-if="row.whatTail">{{ ' ' }}{{ row.whatTail }}</template></span>
      <Button
        v-if="row.action"
        as="a"
        variant="outline"
        :tone="row.hot ? 'accent' : 'default'"
        :href="row.action.target"
      >{{ row.action.label }}</Button>
    </div>

    <Link v-if="footer && footerHref" class="blkfoot" :href="footerHref">{{ footer }}</Link>
  </div>
</template>
