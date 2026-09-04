import { computed, watch } from "vue"

/*
 * A queue pane narrows itself in the URL, so the pane a link opens is the pane
 * the sender was looking at. Returns the suffix its rows hang on their href.
 */
export function useQueueQuery(read: () => Record<string, string | null>) {
    const query = computed(() => {
        const params = new URLSearchParams()

        for (const [key, value] of Object.entries(read())) {
            if (value !== null) params.set(key, value)
        }

        return params.size ? `?${params}` : ""
    })

    // Replace rather than push: a history entry per keystroke makes the back
    // button unusable. Set and delete on the live URL, because the board keeps
    // its own ?ansicht= there.
    watch(query, () => {
        const url = new URL(window.location.href)

        for (const [key, value] of Object.entries(read())) {
            value === null ? url.searchParams.delete(key) : url.searchParams.set(key, value)
        }

        history.replaceState(history.state, "", url)
    })

    return query
}
