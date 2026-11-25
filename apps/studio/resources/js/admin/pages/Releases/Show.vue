<script setup lang="ts">
import { Button } from "@/ui/button"
import { computed, ref } from "vue"
import { Link } from "@inertiajs/vue3"
import AdminLayout from "@/admin/layouts/AdminLayout.vue"
import Page from "@/ui/page/Page.vue"
import { monthDate } from "@/admin/lib/dates"
import {
    mediaUrl,
    releaseMark,
    sectionLabel,
    type ReleaseDetail,
} from "@/admin/lib/releases"

/*
 * Ein Release mit allem, was darin steckt. Die Titel sind die Commit-Betreffe,
 * wie release-log sie aus git liest — die Beschreibung darunter ebenso, und
 * deshalb steht sie zugeklappt.
 */
const props = defineProps<{
    release: ReleaseDetail
    installed: string
    mediaBase: string | null
}>()

const filter = ref<string | null>(null)
const open = ref<Record<string, boolean>>({})

const sections = computed(() =>
    props.release.sections
        .filter((section) => filter.value === null || filter.value === section.key)
        .map((section) => ({ ...section, name: sectionLabel(section.key) })),
)

const mark = computed(() => releaseMark(props.release.version, props.installed))
const image = computed(() => mediaUrl(props.mediaBase, props.release.image?.src))

function toggle(key: string, index: number) {
    const id = `${key}${index}`
    open.value = { ...open.value, [id]: !open.value[id] }
}
</script>

<template>
    <AdminLayout active="neuigkeiten">
        <Page data-tokens wide>
            <div class="min-w-0 flex-1">
                <div class="px-6 pt-5 max-[640px]:px-5 max-[640px]:pt-4">
                    <Link
                        class="inline-flex items-center gap-1.5 text-small leading-none font-medium text-ink-2 no-underline hover:text-foreground"
                        href="/neuigkeiten"
                    >
                        <svg
                            class="size-3.5 fill-none stroke-current stroke-[1.6] [stroke-linecap:round] [stroke-linejoin:round]"
                            viewBox="0 0 24 24"
                        >
                            <path d="M15 6l-6 6 6 6" />
                        </svg>
                        {{ $t("releases.title") }}
                    </Link>

                    <div class="mt-2.5 flex items-baseline gap-3">
                        <h1
                            class="m-0 text-title leading-7 font-semibold tracking-[-0.02em]"
                        >
                            {{ release.version }}
                        </h1>
                        <span class="text-small leading-none tabular-nums text-ink-2">
                            {{ monthDate(release.date) }}
                        </span>
                        <span
                            v-if="mark"
                            class="inline-flex rounded-[12px] px-2 py-[3px] text-small leading-[1.5] font-semibold"
                            :class="mark.class"
                        >
                            {{ $t(mark.key) }}
                        </span>
                    </div>

                    <div
                        v-if="release.headline"
                        class="mt-3.5 text-title leading-[1.3] font-semibold tracking-[-0.022em]"
                    >
                        {{ release.headline }}
                    </div>

                    <p
                        v-for="(paragraph, index) in release.body"
                        :key="index"
                        class="mt-3 max-w-[620px] text-small leading-[1.6] text-ink-2"
                    >
                        {{ paragraph }}
                    </p>
                </div>

                <div v-if="image" class="px-6 pt-5 max-[640px]:px-5">
                    <img
                        class="block w-[560px] max-w-full rounded-[12px] border border-rule"
                        :src="image"
                        :alt="release.image?.alt ?? ''"
                    />
                </div>

                <div class="flex gap-2 px-6 pt-6 max-[640px]:px-5">
                    <Button
                        variant="outline"
                        :class="filter === null && 'border-faint bg-paper font-medium text-foreground hover:border-faint'"
                        type="button"
                        @click="filter = null"
                    >
                        {{ $t("releases.filterAll") }}
                    </Button>
                    <Button
                        v-for="section in release.sections"
                        :key="section.key"
                        variant="outline"
                        :class="filter === section.key && 'border-faint bg-paper font-medium text-foreground hover:border-faint'"
                        type="button"
                        @click="filter = section.key"
                    >
                        {{ $t(sectionLabel(section.key) ?? "", 2) }}
                    </Button>
                </div>

                <div class="px-6 pb-15 max-[640px]:px-5">
                    <section
                        v-for="section in sections"
                        :key="section.key"
                        class="mt-[30px]"
                    >
                        <h2
                            class="m-0 mb-1 flex items-center gap-2.5 text-body leading-[1.2] font-semibold tracking-[-0.02em]"
                        >
                            {{ $t(section.name ?? "", 2) }}
                            <span class="text-small font-medium tabular-nums text-ink-2">
                                {{ section.items?.length ?? 0 }}
                            </span>
                        </h2>

                        <div
                            v-for="(change, index) in section.items ?? []"
                            :key="change.commit + index"
                        >
                            <button
                                class="-mx-2.5 flex w-[calc(100%+20px)] cursor-pointer items-baseline gap-2.5 bg-transparent px-2.5 py-[9px] text-left border-t border-rule hover:bg-sunk"
                                type="button"
                                @click="toggle(section.key, index)"
                            >
                                <span class="min-w-0 flex-1 text-small leading-[1.47]">
                                    {{ change.title }}
                                </span>
                                <svg
                                    v-if="change.description"
                                    class="size-3.5 flex-none self-center fill-none stroke-mark stroke-[1.75] [stroke-linecap:round] [stroke-linejoin:round]"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :d="
                                            open[`${section.key}${index}`]
                                                ? 'M6 9l6 6 6-6'
                                                : 'M9 6l6 6-6 6'
                                        "
                                    />
                                </svg>
                            </button>

                            <div
                                v-if="open[`${section.key}${index}`] && change.description"
                                class="-mx-2.5 px-2.5 pt-0.5 pb-3 pl-[34px] text-small leading-[1.6] whitespace-pre-line text-ink-2"
                            >
                                {{ change.description }}
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </Page>
    </AdminLayout>
</template>
