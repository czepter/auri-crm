export type ShareResult = 'shared' | 'copied' | 'failed'

export async function shareLink(
  url: string,
  title: string,
  nav: Pick<Navigator, 'share' | 'clipboard'> = navigator,
): Promise<ShareResult> {
  try {
    if (typeof nav.share === 'function') {
      await nav.share({ url, title })

      return 'shared'
    }

    await nav.clipboard.writeText(url)

    return 'copied'
  } catch (error) {
    // Wer das Teilen-Blatt schließt, hat nichts falsch gemacht.
    return error instanceof DOMException && error.name === 'AbortError' ? 'shared' : 'failed'
  }
}

/** Alle Teilen-Knöpfe der Seite; der Hinweis ersetzt kurz die Beschriftung. */
export function initShare(url: string, title: string): void {
  document.querySelectorAll<HTMLElement>('[data-share]').forEach((button) => {
    const label = button.querySelector<HTMLElement>('[data-share-label]') ?? button
    const original = label.textContent

    button.addEventListener('click', async () => {
      const result = await shareLink(url, title)

      if (result === 'shared') return

      label.textContent = result === 'copied' ? 'Kopiert' : 'Fehlgeschlagen'
      setTimeout(() => (label.textContent = original), 2000)
    })
  })
}
