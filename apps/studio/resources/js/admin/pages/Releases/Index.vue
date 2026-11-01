<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import AdminLayout from "@/admin/layouts/AdminLayout.vue"
import Page from "@/ui/page/Page.vue"
import PageHead from "@/ui/page/PageHead.vue"
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
defineProps<{
    releases: FeedItem[]
    installed: string
    mediaBase: string | null
}>()
useLiveProps([])
</script>

<template>
    <AdminLayout active="neuigkeiten">
        <Page data-tokens wide>
            <div class="min-w-0 flex-1">
                <PageHead
                    :kicker="$t('releases.kicker')"
                    :title="$t('releases.title')"
                />

                <div class="px-6 pt-5 pb-11 max-[640px]:px-5">
                    <Link
                        v-for="release in releases"
                        :key="release.version"
                        class="-mx-3 flex items-start gap-6 px-3 py-[15px] text-foreground no-underline shadow-[inset_0_1px_0_var(--rule)] hover:bg-sunk max-[640px]:gap-4"
                        :href="`/neuigkeiten/${release.version}`"
                    >
                        <div class="w-28 flex-none">
                            <div
                                class="text-small leading-[1.2] font-semibold tracking-[-0.012em]"
                            >
                                {{ release.version }}
                            </div>
                            <div
                                class="mt-[3px] text-small leading-[1.3] tabular-nums text-ink-2"
                            >
                                {{ monthDate(release.date) }}
                            </div>
                            <span
                                v-if="releaseMark(release.version, installed)"
                                class="mt-2 inline-flex rounded-[12px] px-2 py-[3px] text-small leading-[1.5] font-semibold"
                                :class="releaseMark(release.version, installed)!.class"
                            >
                                {{ $t(releaseMark(release.version, installed)!.key) }}
                            </span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <!-- Zwei von neun Releases haben eins, deshalb ein
                                 v-if und kein leerer Rahmen. Die Höhe ist
                                 gedeckelt: ein hoher Screenshot schiebt sonst die
                                 nächste Zeile aus dem Bild. -->
                            <img
                                v-if="mediaUrl(mediaBase, release.image?.src)"
                                class="mt-0.5 mb-2.5 block max-h-60 w-full max-w-[560px] rounded-[12px] border border-rule object-cover object-top"
                                :src="mediaUrl(mediaBase, release.image?.src)!"
                                :alt="release.image?.alt ?? ''"
                            />

                            <div
                                v-if="release.headline"
                                class="mb-0.5 text-body leading-[1.3] font-semibold tracking-[-0.02em]"
                            >
                                {{ release.headline }}
                            </div>

                            <p
                                v-for="(paragraph, index) in release.body ?? []"
                                :key="index"
                                class="mt-1.5 max-w-[640px] text-small leading-[1.6] text-ink-2"
                            >
                                {{ paragraph }}
                            </p>

                            <div
                                class="mt-2.5 flex gap-4 text-small leading-[1.4] text-ink-2"
                            >
                                <span
                                    v-for="part in sectionCounts(release.sections)"
                                    :key="part.key"
                                >
                                    <b
                                        class="font-semibold tabular-nums text-foreground"
                                        >{{ part.count }}</b
                                    >
                                    {{ $t(part.label, part.count) }}
                                </span>
                            </div>
                        </div>

                        <svg
                            class="mt-0.5 size-4 flex-none fill-none stroke-mark stroke-[1.6] [stroke-linecap:round] [stroke-linejoin:round]"
                            viewBox="0 0 24 24"
                        >
                            <path d="M9 6l6 6-6 6" />
                        </svg>
                    </Link>
                </div>
            </div>
        </Page>
    </AdminLayout>
</template>
