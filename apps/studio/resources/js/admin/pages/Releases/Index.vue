<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import AdminLayout from "@/admin/layouts/AdminLayout.vue"
import PageHead from "@/ui/page/PageHead.vue"
import { monthDate } from "@/admin/lib/dates"
import { releaseMark, sectionCounts, type FeedItem } from "@/admin/lib/releases"

/*
 * Der Feed. release-log liefert je Release nur die Zähler je Gruppe — ein
 * Release hält bis zu hundertfünfzig Einträge — also steht hier, was ein
 * Release war, und die Einträge liegen hinter der Zeile.
 */
defineProps<{ releases: FeedItem[]; installed: string }>()
</script>

<template>
    <AdminLayout active="neuigkeiten">
        <div data-tokens>
            <PageHead
                :kicker="$t('releases.kicker')"
                :title="$t('releases.title')"
            />

            <div class="px-8 pt-5 pb-11 max-[640px]:px-5">
                <Link
                    v-for="release in releases"
                    :key="release.version"
                    class="-mx-3 flex items-start gap-6 px-3 py-[15px] text-foreground no-underline shadow-[inset_0_1px_0_#F0F0F2] hover:bg-sunk max-[640px]:gap-4"
                    :href="`/neuigkeiten/${release.version}`"
                >
                    <div class="w-28 flex-none">
                        <div
                            class="text-[13px] leading-[1.2] font-semibold tracking-[-0.012em]"
                        >
                            {{ release.version }}
                        </div>
                        <div
                            class="mt-[3px] text-[12px] leading-[1.3] tabular-nums text-ink-2"
                        >
                            {{ monthDate(release.date) }}
                        </div>
                        <span
                            v-if="releaseMark(release.version, installed)"
                            class="mt-2 inline-flex rounded-[12px] px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase"
                            :class="releaseMark(release.version, installed)!.class"
                        >
                            {{ $t(releaseMark(release.version, installed)!.key) }}
                        </span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div
                            v-if="release.headline"
                            class="mb-1.5 text-[15px] leading-[1.3] font-semibold tracking-[-0.02em]"
                        >
                            {{ release.headline }}
                        </div>
                        <div
                            class="flex gap-4 text-[12.5px] leading-[1.4] text-ink-2"
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
    </AdminLayout>
</template>
