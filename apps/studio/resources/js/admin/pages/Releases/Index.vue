<script setup lang="ts">
import { computed, ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import AdminLayout from "@/admin/layouts/AdminLayout.vue"
import Page from "@/ui/page/Page.vue"
import PageHead from "@/ui/page/PageHead.vue"
import Empty from "@/ui/page/Empty.vue"
import { Button } from "@/ui/button"
import { monthDate } from "@/admin/lib/dates"
import {
    mediaUrl,
    releaseMark,
    sectionCounts,
    type FeedItem,
} from "@/admin/lib/releases"
import { useLiveProps } from "@/admin/lib/liveReload"

/*
 * Der Feed. Die Einträge selbst liegen hinter der Zeile — release-log gibt
 * hier nur die Zähler je Gruppe. Was die Zeile trägt, ist deshalb das, was
 * von Hand geschrieben wurde: Überschrift, Beschreibung, Bild.
 */
const props = defineProps<{
    releases: FeedItem[]
    installed: string
    mediaBase: string | null
    checkedAt: string | null
}>()
const source = props.mediaBase ? new URL(props.mediaBase).host : null
const checked = props.checkedAt
    ? new Date(props.checkedAt).toLocaleString('de-DE', { dateStyle: 'medium', timeStyle: 'short' })
    : null
useLiveProps([])

const current = computed(() => props.releases.find((row) => row.version === props.installed) ?? props.releases[0] ?? null)
const open = ref(new Set<string>())
const toggle = (version: string) => {
    if (!open.value.delete(version)) open.value.add(version)
    open.value = new Set(open.value)
}

</script>

<template>
    <AdminLayout active="neuigkeiten">
        <Page data-tokens wide>
            <div class="min-w-0 flex-1">
              <div class="mx-auto w-full max-w-[1140px]">
                <PageHead :kicker="$t('releases.kicker')" :title="$t('releases.title')">
                    <Button class="ml-auto" variant="outline" type="button" @click="router.reload()">{{ $t('releases.reload') }}</Button>
                </PageHead>

                <div class="flex items-start gap-12 px-6 pt-6 pb-11 max-[900px]:flex-col max-[900px]:gap-6 max-[640px]:px-5">
                    <div class="w-full min-w-0 max-w-[720px] flex-1">
                        <Empty v-if="!releases.length" :text="$t('releases.empty')">
                            <span v-if="source || checked" class="text-small text-ink-2">
                                {{ [source, checked && $t('releases.checked', { time: checked })].filter(Boolean).join(' · ') }}
                            </span>
                        </Empty>

                        <article
                            v-for="release in releases"
                            :key="release.version"
                            class="mb-3 surface-card px-5 py-4"
                        >
                            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                <h2 class="text-body leading-[1.3] font-semibold tracking-[-0.02em]">
                                    {{ release.headline ?? release.version }}
                                </h2>
                                <span class="text-small tabular-nums text-ink-2">{{ release.version }} · {{ monthDate(release.date) }}</span>
                                <span
                                    v-if="releaseMark(release.version, installed)"
                                    class="rounded-[12px] px-2 py-[3px] text-small leading-[1.5] font-semibold"
                                    :class="releaseMark(release.version, installed)!.class"
                                >
                                    {{ $t(releaseMark(release.version, installed)!.key) }}
                                </span>
                            </div>

                            <div class="mt-2 flex flex-wrap gap-2">
                                <span
                                    v-for="part in sectionCounts(release.sections)"
                                    :key="part.key"
                                    class="rounded-[12px] bg-sunk px-2 py-[3px] text-small leading-[1.5] font-semibold text-ink-2"
                                >
                                    {{ part.count }} {{ $t(part.label, part.count) }}
                                </span>
                            </div>

                            <img
                                v-if="open.has(release.version) && mediaUrl(mediaBase, release.image?.src)"
                                class="mt-3 block max-h-60 w-full rounded-[12px] border border-rule object-cover object-top"
                                :src="mediaUrl(mediaBase, release.image?.src)!"
                                :alt="release.image?.alt ?? ''"
                            />

                            <template v-if="release.body?.length">
                                <p
                                    v-if="!open.has(release.version)"
                                    class="mt-2 line-clamp-2 text-small leading-[1.6] text-ink-2"
                                >
                                    {{ release.body.join(' ') }}
                                </p>
                                <p
                                    v-for="(paragraph, index) in open.has(release.version) ? release.body : []"
                                    :key="index"
                                    class="mt-2 text-small leading-[1.6] text-ink-2"
                                >
                                    {{ paragraph }}
                                </p>
                            </template>

                            <div class="mt-3 flex gap-4 text-small">
                                <button
                                    v-if="release.body?.length"
                                    class="font-semibold text-foreground hover:underline"
                                    type="button"
                                    :aria-expanded="open.has(release.version)"
                                    @click="toggle(release.version)"
                                >
                                    {{ $t(open.has(release.version) ? 'releases.less' : 'releases.more') }}
                                </button>
                                <Link class="text-ink-2 hover:underline" :href="`/neuigkeiten/${release.version}`">{{ $t('releases.log') }}</Link>
                            </div>
                        </article>
                    </div>

                    <aside class="sticky top-6 w-[320px] flex-none max-[900px]:static max-[900px]:w-full">
                        <div class="surface-card px-5 py-4">
                            <div class="text-small font-semibold text-faint">{{ $t('releases.current') }}</div>
                            <div class="mt-1 text-[22px] leading-[1.2] font-semibold tabular-nums">{{ installed }}</div>
                            <div v-if="current && current.version === installed" class="mt-1 text-small tabular-nums text-ink-2">
                                {{ $t('releases.released', { date: monthDate(current.date) }) }}
                            </div>
                            <Link
                                v-if="current && current.version === installed"
                                class="mt-3 inline-block text-small font-semibold text-foreground hover:underline"
                                :href="`/neuigkeiten/${current.version}`"
                            >
                                {{ $t('releases.log') }}
                            </Link>
                            <div v-if="source || checked" class="mt-3 border-t border-border pt-3 text-small text-ink-2">
                                {{ [source, checked && $t('releases.checked', { time: checked })].filter(Boolean).join(' · ') }}
                            </div>
                        </div>
                    </aside>
                </div>
              </div>
            </div>
        </Page>
    </AdminLayout>
</template>
