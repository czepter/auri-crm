<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { toast } from "vue-sonner";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/ui/dropdown-menu";
import { Toaster } from "@/ui/sonner";
import CommandPalette from "@/admin/components/CommandPalette.vue";
import HelpButton from "@/ui/page/HelpButton.vue";

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

// C-02 (#236): two groups, and the order follows a job's life — inquiry,
// project, contact. What is addressed to the person reading — the bell and
// the tray — comes before it, the way a mail client opens on its inbox. Eleven equal-ranked entries were a list of everything the
// product has, in the order the screens happened to be built.
//
// Only entries with a screen behind them appear, which is the same ruling C-06
// applies to its tabs and S-01 to the Übersicht: an item that goes nowhere
// teaches staff to distrust the nav.
//
// From S-06 the same rule covers authority: a nav item this role would be
// refused is absent rather than rendered into a 403.
//
// Icons: 17px, stroke 1.6, round ends, and the stroke follows the text —
// grey, black in the active row. Anfragen is a board of three columns,
// Posteingang an envelope, Dokumentvorlagen a sheet with a folded corner,
// Auswertungen bars. Kontakte got the address book, or it would be the same
// person symbol as Team.
const items = computed(() =>
    [
        {
            key: "uebersicht",
            label: "Übersicht",
            href: "/",
            icon: '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/>',
        },
        {
            // T-11's bell. A sidebar row rather than a popover: the count badge and
            // the active state are already built here, and a panel would be a second
            // way to read the same list.
            key: "benachrichtigungen",
            label: "Benachrichtigungen",
            href: "/benachrichtigungen",
            count: nav.value?.unread,
            icon: '<path d="M18 16v-5a6 6 0 10-12 0v5l-1.5 2.5h15L18 16z"/><path d="M10 19.5a2 2 0 004 0"/>',
        },
        {
            // E-12: the tray for mail that arrived and matched nothing. Same
            // capability as replying to a client, because triaging one is deciding
            // which conversation it belongs to.
            key: "posteingang",
            label: "Posteingang",
            href: "/posteingang",
            needs: "manage_messages",
            icon: '<path d="M3 6.5h18v11H3z"/><path d="m3 7.5 9 6 9-6"/>',
        },
        {
            key: "anfragen",
            label: "Anfragen",
            href: "/anfragen",
            count: nav.value?.leads,
            needs: "manage_leads",
            icon: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 8v8M13 8v5M18 8v10"/>',
        },
        {
            key: "angebote",
            label: "Angebote",
            href: "/angebote",
            needs: "manage_quotes",
            icon: '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/><path d="M9.5 13h5M9.5 16h5"/>',
        },
        {
            key: "rechnungen",
            label: "Rechnungen",
            href: "/rechnungen",
            needs: "manage_contracts",
            icon: '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        },
        {
            key: "projekte",
            label: "Projekte",
            href: "/projekte",
            count: nav.value?.projects,
            icon: '<path d="M4 8h16v12H4z"/><path d="M9 8V5h6v3"/><path d="M4 13h16"/>',
        },
        {
            key: "galerien",
            label: "Galerien",
            href: "/galerien",
            needs: "manage_shoots",
            icon: '<rect x="3" y="3" width="13" height="13" rx="2"/><path d="M8 21h11a2 2 0 0 0 2-2V8"/>',
        },
        {
            key: "kontakte",
            label: "Kontakte",
            href: "/kontakte",
            needs: "manage_contacts",
            icon: '<path d="M6 3h12v18H6z"/><circle cx="12" cy="10" r="2.4"/><path d="M8.7 17c.6-1.7 1.8-2.6 3.3-2.6s2.7.9 3.3 2.6"/><path d="M6 7H3.5M6 12H3.5M6 17H3.5"/>',
        },
        {
            key: "kalender",
            label: "Kalender",
            href: "/kalender",
            icon: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>',
        },
        {
            // T-10. Not in the draft's sidebar, because C-13 (#249) folds absences
            // into the calendar's month grid as dimmed entries. Until it does, this
            // is the only way to the screen.
            key: "abwesenheiten",
            label: "Abwesenheiten",
            href: "/abwesenheiten",
            icon: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/><path d="M8 14h3M13 14h3M8 17h3"/>',
        },
        { group: "Studio" },
        {
            key: "pakete",
            label: "Pakete",
            href: "/pakete",
            needs: "manage_packages",
            studio: true,
            icon: '<path d="M21 8.5v7l-9 4.5-9-4.5v-7L12 4z"/><path d="M3 8.5 12 13l9-4.5M12 13v7"/>',
        },
        {
            // Vier Arten unter einem Eintrag (TemplateHubController). Wer nur
            // eine davon führen darf, sieht ihn deshalb auch — der Hub zeigt
            // nur die Arten, für die es reicht.
            key: "vorlagen",
            label: "Vorlagen",
            href: "/vorlagen",
            needs: [
                "manage_tasks",
                "manage_form_templates",
                "manage_contracts",
                "manage_settings",
            ],
            studio: true,
            icon: '<rect x="4" y="3" width="12" height="16" rx="2"/><path d="M8 8h4M8 12h4M19 7v12a2 2 0 0 1-2 2H8"/>',
        },
        {
            key: "team",
            label: "Team",
            href: "/team",
            needs: "manage_staff",
            studio: true,
            icon: '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M16.5 5.5a3.2 3.2 0 010 5.6M17.5 14.8c2 .7 3.5 2.6 3.5 5.2"/>',
        },
        {
            // S-13: whole-book figures, so the same capability that lets a role see
            // every project is the one that lets them see the totals over all of them.
            key: "auswertungen",
            label: "Auswertungen",
            href: "/auswertungen",
            needs: "view_all_projects",
            studio: true,
            icon: '<path d="M3.5 20h17"/><path d="M7 20v-6M12 20V6M17 20v-9"/>',
        },
        {
            key: "einstellungen",
            label: "Einstellungen",
            href: "/einstellungen",
            needs: "manage_settings",
            studio: true,
            icon: '<path d="M4 6h16M4 12h16M4 18h16"/><circle cx="9" cy="6" r="2"/><circle cx="15" cy="12" r="2"/><circle cx="8" cy="18" r="2"/>',
        },
    ].filter(
        (item) =>
            !item.needs ||
            [item.needs]
                .flat()
                .some((need) => can.value.includes(need as App.Enums.Capability)),
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
            <!-- The phone rules sit on the nav, exactly where crm.css had them.
           `order` does nothing from here — the nav is a child of this wrapper,
           not of the flex row — but that is today's behaviour, and no baseline
           covers 640px to tell a fix from a regression. The desktop `sticky`
           does work: the wrapper stretches to the flex row, so the nav pins
           against it while main scrolls. -->
            <nav
                class="flex w-[214px] flex-col px-3 py-4.5 max-[1080px]:w-14 max-[1080px]:px-2 max-[640px]:sticky max-[640px]:bottom-0 max-[640px]:z-30 max-[640px]:order-2 max-[640px]:w-auto max-[640px]:flex-row max-[640px]:justify-around max-[640px]:bg-paper max-[640px]:px-1 max-[640px]:py-1.5 max-[640px]:shadow-[inset_0_1px_0_var(--rule)] sticky top-0 h-screen overflow-y-auto max-[640px]:top-auto max-[640px]:h-auto max-[640px]:overflow-visible"
            >
                <div class="mb-px flex items-start gap-1 pb-5.5 max-[1080px]:hidden">
                    <Link
                        class="flex min-w-0 flex-1 items-center rounded-lg px-2.5 text-[17px] leading-none font-semibold tracking-[-0.024em] text-foreground no-underline transition-colors duration-[120ms] ease-out hover:bg-sunk"
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
                    class="mb-4 flex w-full cursor-pointer items-center gap-2.25 rounded-lg border-0 bg-sunk px-2.5 py-1.5 text-left text-[13px] leading-[1.47] tracking-[-0.01em] text-ink-2 transition-colors duration-[120ms] ease-out hover:bg-sunk-2 max-[1080px]:justify-center max-[1080px]:px-0 max-[1080px]:py-2 max-[640px]:hidden"
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
                    {{ $t("palette.search") }}
                    <!-- font-sans against the browser's monospace default for kbd; the
               shorthand in crm.css carried the family along. -->
                    <kbd
                        class="ml-auto font-sans text-[11px] leading-none font-medium tabular-nums text-ink-2 max-[1080px]:hidden"
                        >⌘K</kbd
                    >
                </button>

                <HelpButton />

                <template v-for="item in items" :key="item.key ?? item.group">
                    <template v-if="item.group">
                        <div class="h-4.5 max-[640px]:hidden" />
                        <div
                            class="px-2.5 pb-1.5 text-[10.5px] tracking-[0.06em] uppercase text-ink-2 max-[1080px]:hidden"
                        >
                            {{ item.group }}
                        </div>
                    </template>

                    <!-- The label rides in its own span so the narrow breakpoint can
               drop it: ten truncated words are harder to hit than ten icons.
               The second group drops out entirely on a phone — nobody sets up
               a studio from one. -->
                    <Link
                        v-else
                        class="mb-px flex items-center gap-2.25 rounded-lg px-2.5 py-1.5 text-[13px] tracking-[-0.01em] text-foreground no-underline transition-colors duration-[120ms] ease-out hover:bg-sunk max-[1080px]:justify-center max-[1080px]:px-0 max-[1080px]:py-2"
                        :class="[
                            item.key === props.active &&
                                'bg-sunk-2 font-semibold',
                            item.studio && 'max-[640px]:hidden',
                        ]"
                        :href="item.href!"
                    >
                        <svg
                            class="size-4.25 flex-none fill-none stroke-[1.6] [stroke-linecap:round] [stroke-linejoin:round]"
                            :class="
                                item.key === props.active
                                    ? 'stroke-foreground'
                                    : 'stroke-ink-2'
                            "
                            viewBox="0 0 24 24"
                            v-html="item.icon"
                        />
                        <span class="max-[1080px]:hidden">{{
                            item.label
                        }}</span>
                        <span
                            v-if="item.count !== undefined"
                            class="ml-auto text-[11.5px] tabular-nums text-ink-2 max-[640px]:hidden"
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
                <div v-if="release" class="mt-auto max-[1080px]:hidden">
                    <Link
                        class="mb-1.5 flex items-center gap-[7px] rounded-lg px-2.5 py-1.5 text-[11.5px] leading-none tabular-nums text-ink-2 no-underline transition-colors duration-[120ms] ease-out hover:bg-sunk hover:text-foreground"
                        :class="props.active === 'neuigkeiten' && 'bg-sunk-2 font-semibold text-foreground'"
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
                        :class="release ? undefined : 'mt-auto'"
                        class="flex w-full cursor-pointer items-center gap-2 rounded-lg border-0 bg-transparent p-2.5 text-left text-[12.5px] leading-none font-medium text-foreground transition-colors duration-[120ms] ease-out hover:bg-sunk data-[state=open]:bg-sunk max-[1080px]:hidden"
                    >
                        <span
                            class="flex size-6 flex-none items-center justify-center rounded-full bg-[#F0F0F2] text-[10px] font-semibold text-ink-2"
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
                        <DropdownMenuItem as-child>
                            <Link href="/hilfe">{{ $t("help.title") }}</Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem @select="router.post('/logout')">
                            {{ $t("common.signOut") }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </nav>

            <CommandPalette ref="palette" />
        </div>

        <main class="flex min-w-0 flex-1 flex-col pl-12 max-[640px]:order-1">
            <slot />
        </main>

        <Toaster position="bottom-right" rich-colors />
    </div>
</template>
