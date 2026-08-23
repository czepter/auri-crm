export interface SelectionState {
  selected: Set<string>
  anchor: string | null
}

export interface Modifiers {
  shift?: boolean
  meta?: boolean
}

export function toggle(selected: Set<string>, id: string): Set<string> {
  const next = new Set(selected)

  if (!next.delete(id)) {
    next.add(id)
  }

  return next
}

export function range(ids: string[], anchor: string, target: string): string[] {
  const from = ids.indexOf(anchor)
  const to = ids.indexOf(target)

  return ids.slice(Math.min(from, to), Math.max(from, to) + 1)
}

export function applyClick(state: SelectionState, ids: string[], id: string, modifiers: Modifiers): SelectionState {
  // Der Anker bleibt stehen, solange mit Shift gezogen wird — sonst wanderte
  // die Strecke mit jedem Klick und man koennte sie nie verkuerzen.
  if (modifiers.shift && state.anchor !== null) {
    return { selected: new Set(range(ids, state.anchor, id)), anchor: state.anchor }
  }

  if (modifiers.meta) {
    return { selected: toggle(state.selected, id), anchor: id }
  }

  return { selected: new Set([id]), anchor: id }
}
