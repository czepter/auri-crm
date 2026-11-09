export type ShareResult = 'shared' | 'copied' | 'failed'

export async function shareLink(
  url: string,
  title: string | null,
  nav: Pick<Navigator, 'share' | 'clipboard'> = navigator,
): Promise<ShareResult> {
  try {
    if (typeof nav.share === 'function') {
      await nav.share({ url, title: title ?? undefined })

      return 'shared'
    }

    await nav.clipboard.writeText(url)

    return 'copied'
  } catch (error) {
    // Wer das Teilen-Blatt schließt, hat nichts falsch gemacht.
    return error instanceof DOMException && error.name === 'AbortError' ? 'shared' : 'failed'
  }
}
