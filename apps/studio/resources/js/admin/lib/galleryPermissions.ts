export type Audience = 'client' | 'guest'
export type Action = 'favorites' | 'comments' | 'hide' | 'share'
export type Permissions = Record<Audience, Record<Action, boolean>>

export const ACTIONS: Action[] = ['favorites', 'comments', 'hide', 'share']
export const AUDIENCES: Audience[] = ['client', 'guest']

/** Gäste haben kein Konto, das etwas für sich ausblenden könnte. */
export function offered(audience: Audience, action: Action): boolean {
  return !(audience === 'guest' && action === 'hide')
}

export function withPermission(permissions: Permissions, audience: Audience, action: Action, on: boolean): Permissions {
  return { ...permissions, [audience]: { ...permissions[audience], [action]: on } }
}
