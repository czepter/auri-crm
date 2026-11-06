<script setup lang="ts">
import { computed, nextTick, ref, watch } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { useMediaQuery } from "@vueuse/core";
import { toast } from "vue-sonner";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/ui/dropdown-menu";
import { Toaster } from "@/ui/sonner";
import CommandPalette from "@/admin/layouts/CommandPalette.vue";
import { Alert } from "@/ui/alert";
import { Button } from "@/ui/button";
import { navItems, phoneNav, studioKeys } from "@/admin/lib/nav";
import { Dialog, DialogContent, DialogTitle } from "@/ui/dialog";

const props = defineProps<{ active: string }>();

const palette = ref<InstanceType<typeof CommandPalette> | null>(null);

const page = usePage();

// The server's own flash (session()->flash('status', ...)) survives exactly
// one request — watch instead of reading it once, so a redirect that sets it
// after this layout has already mounted still surfaces it.
watch(
    () => page.props.flash?.status,
    (status) => {
        if (status) toast.success(status);
    },
    { immediate: true },
);

const user = computed(() => page.props.auth.user);
const nav = computed(() => page.props.nav);
const release = computed(() => page.props.release);

// Initials, since users carries no avatar column — the same fallback the crew
// stack uses, for the same reason.
const initials = computed(() => {
    const words = (user.value?.name ?? "").trim().split(/\s+/).filter(Boolean);
    const picked =
        words.length > 1 ? [words[0], words[words.length - 1]] : words;

    return picked
        .map((word) => word[0])
        .join("")
        .toUpperCase();
});

const can = computed(() => page.props.auth.can ?? []);

// Die Zeilen selbst stehen in lib/nav.ts, damit die Befugnisregel ohne
// gemountete Komponente pruefbar ist.
const items = computed(() => navItems(can.value, nav.value ?? {}));

const phone = computed(() => phoneNav(items.value));
const moreOpen = ref(false);
const phoneViewport = useMediaQuery("(max-width: 640px)");

function search() {
    moreOpen.value = false;
    nextTick(() => palette.value?.open());
}

// Die Aufklapper ohne gespeicherten Zustand: wer in einem Zweig steht,
// sieht ihn offen, und beim Verlassen faellt er wieder zu. Das ist der Zustand,
// den man ohnehin will, und spart den Speicher dafuer.
const studioOpen = ref(studioKeys.includes(props.active));
const collectorsOpen = ref<Record<string, boolean>>(
    Object.fromEntries(
        items.value.flatMap((item) =>
            item.children
                ? [[item.key, item.children.some((child) => child.key === props.active)]]
                : [],
        ),
    ),
);
</script>

<template>
    <div class="flex min-h-screen max-[640px]:flex-col">
        <!-- The marker alone, no value: the palette is on :root since crm.css
         stopped scoping it. What is left for the attribute to do is take the
         nav out of tokens.css's unlayered svg reset, which no utility can
         outrank. -->
        <div data-tokens class="flex-none">
            <nav
                class="flex w-[214px] flex-col px-3 py-4.5 max-[1080px]:w-14 max-[1080px]:px-2 sticky top-0 h-screen overflow-y-auto max-[640px]:hidden"
            >
                <div class="mb-px flex items-start gap-1 pb-5.5 max-[1080px]:hidden">
                    <Link
                        class="flex min-w-0 flex-1 items-center rounded-lg px-2.5 text-body leading-none font-semibold tracking-[-0.024em] text-foreground no-underline transition-colors duration-[120ms] ease-out hover:bg-sunk"
                        href="/"
                    >
                        {{ $page.props.brand }}
                    </Link>
                    <Link
                        v-if="can.includes('manage_leads')"
                        class="-mt-1 flex size-6.5 flex-none items-center justify-center rounded-lg text-ink-2 no-underline transition-colors duration-[120ms] ease-out hover:bg-sunk hover:text-foreground"
                        href="/anfragen/neu"
                        :title="$t('leads.createKicker')"
                        :aria-label="$t('leads.createKicker')"
                    >
                        <svg class="size-4.25 fill-none stroke-current stroke-[1.6] [stroke-linecap:round]" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
                    </Link>
                </div>

                <!-- Above the navigation, not in it: it does not change page, it
             opens a tool. The shortcut stands where a count would. -->
                <button
                    class="mb-4 flex w-full cursor-pointer items-center gap-2.25 rounded-lg border-0 bg-sunk px-2.5 py-1.5 text-left text-ui leading-[1.47] tracking-[-0.01em] text-ink-2 transition-colors duration-[120ms] ease-out hover:bg-sunk-2 max-[1080px]:justify-center max-[1080px]:px-0 max-[1080px]:py-2"
                    type="button"
                    @click="palette?.open()"
                >
                    <svg
                        class="size-4.25 flex-none fill-none stroke-ink-2 stroke-[1.6] [stroke-linecap:round]"
                        viewBox="0 0 24 24"
                    >
                        <circle cx="11" cy="11" r="6.5" />
                        <path d="m16 16 4.5 4.5" />
                    </svg>
                    <span>{{ $t("palette.search") }}</span>
                    <!-- font-sans against the browser's monospace default for kbd; the
               shorthand in crm.css carried the family along. -->
                    <kbd
                        class="ml-auto font-sans text-small leading-none font-medium tabular-nums text-ink-2 max-[1080px]:hidden"
                        >⌘K</kbd
                    >
                </button>

                <template v-for="item in items" :key="item.key ?? item.group">
                    <template v-if="item.group">
                        <div class="h-4.5" />
                        <!-- Foldable only where a heading is drawn: the narrow rail
                   has none, and without one there would be no handle to open
                   the rows again. -->
                        <button
                            v-if="item.fold"
                            class="flex w-full cursor-pointer items-center border-0 bg-transparent px-2.5 pb-1.5 text-left text-small font-medium text-ink-2 transition-colors duration-[120ms] ease-out hover:text-foreground max-[1080px]:hidden"
                            type="button"
                            :aria-expanded="studioOpen"
                            @click="studioOpen = !studioOpen"
                        >
                            {{ item.group }}
                            <svg
                                class="ml-auto size-3.5 flex-none fill-none stroke-ink-2 stroke-[1.8] [stroke-linecap:round] [stroke-linejoin:round] transition-transform duration-[120ms] ease-out"
                                :class="studioOpen && 'rotate-180'"
                                viewBox="0 0 24 24"
                            >
                                <path d="m6 9 6 6 6-6" />
                            </svg>
                        </button>
                        <div
                            v-else
                            class="px-2.5 pb-1.5 text-small font-medium text-ink-2 max-[1080px]:hidden"
                        >
                            {{ item.group }}
                        </div>
                    </template>

                    <!-- A collecting entry goes nowhere itself, it opens its rows.
               On the narrow rail it is dropped and the rows stand open, or
               they would be unreachable there. -->
                    <template v-else-if="item.children">
                        <button
                            class="mb-px flex w-full cursor-pointer items-center gap-2.25 rounded-lg border-0 bg-transparent px-2.5 py-1.5 text-left text-ui tracking-[-0.01em] text-foreground transition-colors duration-[120ms] ease-out hover:bg-sunk max-[1080px]:hidden"
                            type="button"
                            :aria-expanded="collectorsOpen[item.key!]"
                            @click="collectorsOpen[item.key!] = !collectorsOpen[item.key!]"
                        >
                            <svg
                                class="size-4.25 flex-none fill-none stroke-[1.6] [stroke-linecap:round] [stroke-linejoin:round]"
                                :class="
                                    collectorsOpen[item.key!]
                                        ? 'stroke-foreground'
                                        : 'stroke-ink-2'
                                "
                                viewBox="0 0 24 24"
                                v-html="item.icon"
                            />
                            <span>{{ item.label }}</span>
                            <svg
                                class="ml-auto size-3.5 flex-none fill-none stroke-ink-2 stroke-[1.8] [stroke-linecap:round] [stroke-linejoin:round] transition-transform duration-[120ms] ease-out"
                                :class="collectorsOpen[item.key!] && 'rotate-180'"
                                viewBox="0 0 24 24"
                            >
                                <path d="m6 9 6 6 6-6" />
                            </svg>
                        </button>
                        <div
                            class="mb-px ml-[19px] pl-1.5 max-[1080px]:ml-0 max-[1080px]:pl-0"
                            :class="!collectorsOpen[item.key!] && 'hidden max-[1080px]:block'"
                        >
                            <Link
                                v-for="child in item.children"
                                :key="child.key"
                                class="mb-px flex items-center gap-2.25 rounded-lg px-2.5 py-1 text-ui tracking-[-0.01em] text-ink-2 no-underline transition-colors duration-[120ms] ease-out hover:bg-sunk hover:text-foreground max-[1080px]:justify-center max-[1080px]:px-0 max-[1080px]:py-2"
                                :class="child.key === props.active && 'font-semibold text-foreground'"
                                :href="child.href!"
                                :title="child.label"
                            >
                                <svg
                                    class="hidden size-4.25 flex-none fill-none stroke-ink-2 stroke-[1.6] [stroke-linecap:round] [stroke-linejoin:round] max-[1080px]:block"
                                    viewBox="0 0 24 24"
                                    v-html="child.icon"
                                />
                                <span class="max-[1080px]:sr-only">{{
                                    child.label
                                }}</span>
                            </Link>
                        </div>
                    </template>

                    <!-- The label rides in its own span so the narrow breakpoint can
               drop it: ten truncated words are harder to hit than ten icons.
               Studio drops out entirely on a phone — nobody sets up a studio
               from one. -->
                    <Link
                        v-else
                        class="mb-px flex items-center gap-2.25 rounded-lg px-2.5 py-1.5 text-ui tracking-[-0.01em] text-foreground no-underline transition-colors duration-[120ms] ease-out hover:bg-sunk max-[1080px]:justify-center max-[1080px]:px-0 max-[1080px]:py-2"
                        :class="[
                            item.key === props.active && 'font-semibold',
                            item.studio &&
                                !studioOpen &&
                                'hidden max-[1080px]:flex',
                        ]"
                        :href="item.href!"
                        :aria-label="item.label"
                        :title="item.label"
                    >
                        <svg
                            class="size-4.25 flex-none fill-none stroke-[1.6] [stroke-linecap:round] [stroke-linejoin:round]"
                            :class="
                                item.key === props.active
                                    ? 'stroke-ring'
                                    : 'stroke-ink-2'
                            "
                            viewBox="0 0 24 24"
                            v-html="item.icon"
                        />
                        <span class="max-[1080px]:sr-only">{{
                            item.label
                        }}</span>
                        <span
                            v-if="item.count !== undefined"
                            class="ml-auto text-small tabular-nums text-ink-2"
                        >
                            {{ item.count }}
                        </span>
                    </Link>
                </template>

                <!-- Die laufende Version, und der Punkt daneben, wenn
             release-log eine hoehere kennt. Sie ist zugleich der Weg zu den
             Neuigkeiten: ein eigener Nav-Eintrag stand neben
             "Benachrichtigungen" und hiess im Deutschen fast dasselbe.
             mt-auto sitzt hier statt am Konto darunter, damit beide zusammen
             am Fuss der Leiste stehen. -->
                <div class="mt-auto mb-1.5 flex items-center gap-1 max-[1080px]:hidden">
                    <Link
                        v-if="release"
                        class="flex items-center gap-[7px] rounded-lg px-2.5 py-1.5 text-ui leading-none tabular-nums text-ink-2 no-underline transition-colors duration-[120ms] ease-out hover:bg-sunk hover:text-foreground"
                        :class="props.active === 'neuigkeiten' && 'font-semibold text-foreground'"
                        href="/neuigkeiten"
                    >
                        v{{ release.installed }}
                        <span
                            v-if="release.updateAvailable"
                            class="size-1.5 flex-none rounded-full bg-due"
                            :title="$t('releases.available')"
                        />
                    </Link>
                </div>

                <!-- The effective style here is the cascade of both sheets: crm.css
             set the box and the size, tokens.css the weight and the flex
             centring inside the avatar. -->
                <DropdownMenu>
                    <DropdownMenuTrigger
                        class="flex w-full cursor-pointer items-center gap-2 rounded-lg border-0 bg-transparent p-2.5 text-left text-ui leading-none font-medium text-foreground transition-colors duration-[120ms] ease-out hover:bg-sunk data-[state=open]:bg-sunk max-[1080px]:hidden"
                    >
                        <span
                            class="flex size-6 flex-none items-center justify-center rounded-full bg-sunk text-small font-semibold text-ink-2"
                        >
                            {{ initials }}
                        </span>
                        <span class="min-w-0 truncate">{{ user?.name }}</span>
                        <svg
                            class="ml-auto size-4 flex-none fill-none stroke-ink-2 stroke-[1.75] [stroke-linecap:round] [stroke-linejoin:round]"
                            viewBox="0 0 24 24"
                        >
                            <path d="m8 10 4-4 4 4M8 14l4 4 4-4" />
                        </svg>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent side="top" class="w-[190px]">
                        <DropdownMenuItem as-child>
                            <Link href="/profil">{{ $t("profile.nav") }}</Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem @select="router.post('/logout')">
                            {{ $t("common.signOut") }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </nav>

            <CommandPalette ref="palette" />
        </div>

        <header class="hidden items-center justify-between pl-6 pr-2 pt-2 max-[640px]:flex">
            <Link class="text-body leading-none font-semibold tracking-[-0.024em] text-foreground no-underline" href="/">
                {{ $page.props.brand }}
            </Link>
            <Link
                v-if="can.includes('manage_leads')"
                class="flex size-11 items-center justify-center rounded-lg text-ink-2 no-underline"
                href="/anfragen/neu"
                :title="$t('leads.createKicker')"
                :aria-label="$t('leads.createKicker')"
            >
                <svg class="size-5 fill-none stroke-current stroke-[1.6] [stroke-linecap:round]" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
            </Link>
        </header>

        <main class="flex min-w-0 flex-1 flex-col pl-12 max-[640px]:pb-[72px] max-[640px]:pl-0">
            <Alert
                v-if="page.props.studioDeletion"
                variant="destructive"
                class="mt-3 mr-8 max-[640px]:mr-4"
            >
                {{ $t("settings.deletionOn", { date: page.props.studioDeletion }) }}
                <Button
                    v-if="user?.role === 'admin'"
                    class="ml-auto"
                    variant="outline"
                    type="button"
                    @click="router.delete('/einstellungen/studio-loeschen', { preserveScroll: true })"
                >
                    {{ $t("common.cancel") }}
                </Button>
            </Alert>
            <slot />
        </main>

        <nav
            class="fixed inset-x-0 bottom-0 z-30 hidden h-[calc(3.5rem+env(safe-area-inset-bottom))] bg-paper pb-[env(safe-area-inset-bottom)] shadow-[inset_0_1px_0_var(--rule)] max-[640px]:flex"
            data-tokens
        >
            <Link
                v-for="item in phone.bar"
                :key="item.key"
                class="relative flex min-h-12 min-w-16 flex-1 flex-col items-center justify-center gap-1 text-[11px] leading-none text-ink-2 no-underline"
                :class="item.key === props.active && 'font-semibold text-foreground'"
                :href="item.href!"
            >
                <svg
                    class="size-4.25 flex-none fill-none stroke-current stroke-[1.6] [stroke-linecap:round] [stroke-linejoin:round]"
                    viewBox="0 0 24 24"
                    v-html="item.icon"
                />
                {{ item.label }}
                <span
                    v-if="item.count"
                    class="absolute top-1 left-1/2 ml-1.5 grid h-4 min-w-4 place-items-center rounded-full bg-foreground px-1 text-[10px] leading-none text-background"
                >
                    {{ item.count }}
                </span>
            </Link>
            <button
                class="flex min-h-12 min-w-16 flex-1 cursor-pointer flex-col items-center justify-center gap-1 border-0 bg-transparent text-[11px] leading-none text-ink-2"
                type="button"
                @click="moreOpen = true"
            >
                <svg class="size-4.25 flex-none fill-none stroke-current stroke-[1.6] [stroke-linecap:round]" viewBox="0 0 24 24">
                    <path d="M4 7h16M4 12h16M4 17h16" />
                </svg>
                {{ $t("nav.more") }}
            </button>
        </nav>

        <Dialog v-model:open="moreOpen">
            <DialogContent
                hide-close
                class="max-[640px]:top-auto max-[640px]:bottom-0 max-[640px]:left-0 max-[640px]:w-full max-[640px]:max-w-none max-[640px]:translate-x-0 max-[640px]:translate-y-0 max-[640px]:overflow-y-auto max-[640px]:rounded-b-none max-[640px]:pb-[env(safe-area-inset-bottom)]"
            >
                <DialogTitle class="sr-only">{{ $t("nav.more") }}</DialogTitle>
                <Link
                    v-for="item in phone.more"
                    :key="item.key"
                    class="flex min-h-12 flex-none items-center gap-3 px-6 text-ui text-foreground no-underline"
                    :href="item.href!"
                    @click="moreOpen = false"
                >
                    <svg
                        class="size-4.25 flex-none fill-none stroke-ink-2 stroke-[1.6] [stroke-linecap:round] [stroke-linejoin:round]"
                        viewBox="0 0 24 24"
                        v-html="item.icon"
                    />
                    {{ item.label }}
                    <span v-if="item.count" class="ml-auto text-small tabular-nums text-ink-2">{{ item.count }}</span>
                </Link>
                <div class="my-1 h-px flex-none bg-[var(--rule)]" />
                <button
                    class="flex min-h-12 flex-none cursor-pointer items-center gap-3 border-0 bg-transparent px-6 text-left text-ui text-foreground"
                    type="button"
                    @click="search"
                >
                    <svg class="size-4.25 flex-none fill-none stroke-ink-2 stroke-[1.6] [stroke-linecap:round]" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="6.5" />
                        <path d="m16 16 4.5 4.5" />
                    </svg>
                    {{ $t("palette.search") }}
                </button>
                <Link class="flex min-h-12 flex-none items-center gap-3 px-6 text-ui text-foreground no-underline" href="/hilfe" @click="moreOpen = false">
                    <svg class="size-4.25 flex-none fill-none stroke-ink-2 stroke-[1.6] [stroke-linecap:round]" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.4-1 .9-1 1.7M12 17h.01" />
                    </svg>
                    {{ $t("help.open") }}
                </Link>
                <Link class="flex min-h-12 flex-none items-center gap-3 px-6 text-ui font-medium text-foreground no-underline" href="/profil" @click="moreOpen = false">
                    <span class="flex size-6 flex-none items-center justify-center rounded-full bg-sunk text-small font-semibold text-ink-2">{{ initials }}</span>
                    {{ user?.name }}
                </Link>
                <button
                    class="flex min-h-12 flex-none cursor-pointer items-center border-0 bg-transparent px-6 text-left text-ui text-foreground"
                    type="button"
                    @click="router.post('/logout')"
                >
                    {{ $t("common.signOut") }}
                </button>
            </DialogContent>
        </Dialog>

        <Toaster :position="phoneViewport ? 'top-center' : 'bottom-right'" rich-colors />
    </div>
</template>
