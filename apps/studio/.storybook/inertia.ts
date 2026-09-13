import { computed, defineComponent, h, reactive, type PropType } from 'vue'

/*
 * Inertia, so weit ein Katalog es braucht.
 *
 * Die Bildschirme lesen ihre geteilten Werte über usePage() und schicken über
 * router und useForm. Beides hängt an einer laufenden Anwendung, die es hier
 * nicht gibt — also ersetzt main.ts das Paket per Alias durch diese Datei.
 * Ein Alias statt einer Attrappe je Story, weil die Bildschirme sonst wissen
 * müssten, dass sie im Katalog stehen.
 *
 * Was hier fehlt, fehlt mit Absicht: es wird nichts geschickt und nichts
 * navigiert. Eine Story zeigt einen Zustand, sie führt ihn nicht fort.
 */

type PageProps = Record<string, unknown>

const page = reactive({
    component: 'Storybook',
    url: '/',
    version: null as string | null,
    props: {} as PageProps,
})

/** Die geteilten Werte, die HandleInertiaRequests auf jeden Bildschirm legt. */
export const SHARED: PageProps = {
    auth: {
        user: { id: 'u1', name: 'Christian Zepter', email: 'christian@atelier-nordlicht.de' },
        can: [],
    },
    brand: 'Atelier Nordlicht',
    portalPreview: false,
    soloStudio: false,
    flash: { status: null, secret: null },
    errors: {},
    nav: { leads: 4, unread: 2, projects: 11 },
    release: { installed: '2026.9.1', updateAvailable: false },
}

/** Setzt die Seite, auf der die Story spielt. Aufgerufen vom Dekorator. */
export function setPage(props: PageProps, url = '/'): void {
    page.props = { ...SHARED, ...props }
    page.url = url
}

export function usePage() {
    return page
}

function noop(): void {}

export const router = {
    get: noop,
    post: noop,
    put: noop,
    patch: noop,
    delete: noop,
    visit: noop,
    reload: noop,
    replace: noop,
    on: () => noop,
    remember: noop,
    restore: () => undefined,
}

/*
 * useForm ohne Versand. Die Felder sind reaktiv, damit Eingaben im Katalog
 * ankommen; processing bleibt false.
 *
 * Die Fehler kommen aus den Seiten-Props, wie bei Inertia auch: der Server
 * legt sie nach einer abgewiesenen Eingabe unter `errors` ab, useForm liest
 * sie von dort. Eine Story zeigt den abgewiesenen Zustand also über
 * `parameters.page.errors`. Ohne das hätte der Katalog keinen einzigen
 * Fehlerzustand — und den sieht man im Betrieb öfter als den geglückten.
 */
export function useForm<T extends Record<string, unknown>>(data: T) {
    const initial = JSON.parse(JSON.stringify(data)) as T

    const form = reactive({
        ...data,
        errors: { ...((page.props.errors ?? {}) as Record<string, string>) },
        processing: false,
        isDirty: false,
        hasErrors: computed(() => Object.keys(form.errors).length > 0),
        transform: () => form,
        reset: () => Object.assign(form, JSON.parse(JSON.stringify(initial))),
        clearErrors: () => {
            form.errors = {}
        },
        setError: (key: string, message: string) => {
            form.errors = { ...form.errors, [key]: message }
        },
        get: noop,
        post: noop,
        put: noop,
        patch: noop,
        delete: noop,
        submit: noop,
        cancel: noop,
        defaults: () => form,
    })

    return form
}

/*
 * Link rendert das Element, das Inertia auch rendert — ohne den Klick
 * abzufangen. `as`, `method` und `data` nimmt er entgegen und ignoriert sie:
 * die Bildschirme setzen sie, und ein unbekanntes Attribut landete sonst im
 * DOM.
 */
export const Link = defineComponent({
    name: 'Link',
    props: {
        href: { type: String, default: '#' },
        as: { type: String, default: 'a' },
        method: { type: String, default: 'get' },
        data: { type: Object as PropType<Record<string, unknown>>, default: undefined },
        preserveScroll: { type: Boolean, default: false },
        preserveState: { type: Boolean, default: false },
        only: { type: Array as PropType<string[]>, default: undefined },
        replace: { type: Boolean, default: false },
    },
    setup(props, { slots, attrs }) {
        return () =>
            h(
                props.as,
                props.as === 'a'
                    ? { ...attrs, href: props.href, onClick: (event: Event) => event.preventDefault() }
                    : { ...attrs, type: 'button' },
                slots.default?.(),
            )
    },
})

export const Head = defineComponent({
    name: 'Head',
    setup: () => () => null,
})

export function createInertiaApp(): void {}
