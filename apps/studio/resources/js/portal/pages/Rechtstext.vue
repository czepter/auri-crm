<script setup lang="ts">
import { computed } from 'vue'
import PortalBack from '@/portal/components/PortalBack.vue'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'

// Der Körper kommt aus DocumentTemplate::body, denselben Weg wie Angebot und
// Rechnung ihn nehmen — serverseitig aus den Bausteinen gerendert und beim
// Speichern bereinigt, nicht hier.
const props = defineProps<{ title: string; body: string; addressee: string[] }>()

const escape = (text: string) => text.replace(/[&<>"]/g, char => `&#${char.charCodeAt(0)};`)

// The postal address stands directly after the sentence that names it
// ("… zu richten an"), not after the last paragraph.
const html = computed(() => {
  if (!props.addressee.length) {
    return props.body
  }

  const lines = props.addressee.map(line => line.includes('@')
    ? `<a class="inline-flex min-h-11 items-center underline" href="mailto:${escape(line)}">${escape(line)}</a>`
    : escape(line))
  const block = `<address class="my-3 not-italic">${lines.map(line => `<span class="block">${line}</span>`).join('')}</address>`
  const at = props.body.search(/zu richten an/i)
  const end = at === -1 ? -1 : props.body.indexOf('</p>', at)

  return end === -1 ? props.body + block : props.body.slice(0, end + 4) + block + props.body.slice(end + 4)
})
</script>

<template>
  <PortalShell>
    <PortalHeader />

    <PortalWrap class="pb-40">
      <section class="pt-[var(--band)]">
        <PortalBack />
        <h1 class="animate-rise [animation-delay:140ms] motion-reduce:animate-none font-serif text-[clamp(36px,5vw,52px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
          {{ title }}
        </h1>
      </section>

      <article
        class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-12 max-w-[62ch] text-[15.5px] leading-[1.6] [&_h2]:mt-8 [&_h2]:mb-2 [&_h2]:text-[17px] [&_h2]:leading-[1.35] [&_h2]:font-semibold [&_h2:first-child]:mt-0 [&_p+*]:mt-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5"
        v-html="html"
      />
    </PortalWrap>

    <PortalFooter class="pb-[calc(6rem+env(safe-area-inset-bottom))]" />
  </PortalShell>
</template>
