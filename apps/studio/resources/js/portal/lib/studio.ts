/** The studio as a client meets it: a name, and at most two ways to reach it. */
export type Studio = {
  name: string | null
  avatarUrl: string | null
  messagingEnabled: boolean
  phone: string | null
  email: string | null
  responseTime: string | null
  absence: { range: string; message: string } | null
}

/*
 * Whether there is a person to put on the screen at all.
 *
 * A studio that has not been through onboarding has no name, no number and
 * no mailbox, and "Euer Kontakt" over an empty card is the exact failure
 * interface-review.md names: a section exists when it has content.
 */
export function reachable(studio: Studio): boolean {
  return (studio.name ?? '').trim() !== '' || studio.phone !== null || studio.email !== null || studio.messagingEnabled
}
