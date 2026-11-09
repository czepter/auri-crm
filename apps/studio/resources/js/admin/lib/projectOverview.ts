/** Höchstens `size` Nachbarn um den aktuellen Eintrag: Vorgänger, er selbst, danach die nächsten. */
export function timelineWindow<T>(items: T[], current: number, size = 4): T[] {
  const at = current < 0 ? items.length - 1 : current
  const from = Math.max(0, Math.min(at - 1, items.length - size))

  return items.slice(from, from + size)
}

export function cap<T>(items: T[], limit: number): { shown: T[]; total: number; capped: boolean } {
  return { shown: items.slice(0, limit), total: items.length, capped: items.length > limit }
}

/** Die erste überfällige offene Aufgabe wird "Nächster Schritt"; die Liste zeigt sie nicht noch einmal. */
export function splitNextStep<T extends { isOverdue: boolean; isDone: boolean }>(tasks: T[]): { step: T | null; rest: T[] } {
  const open = tasks.filter((task) => !task.isDone)
  const step = open.find((task) => task.isOverdue) ?? null

  return { step, rest: open.filter((task) => task !== step) }
}

/** Ohne Termine, Angebot, Aufgaben und Zahlungen ersetzen Kacheln die leeren Karten. */
export function isBare(parts: { shoots: number; quote: boolean; tasks: number; payments: number }): boolean {
  return !parts.shoots && !parts.quote && !parts.tasks && !parts.payments
}

/** Ein Band mit einem einzigen Wert ist keins. */
export function bandMetrics<T extends { value: string | null }>(metrics: T[]): T[] {
  const filled = metrics.filter((metric) => metric.value !== null)

  return filled.length > 1 ? filled : []
}

export function groupBy<T>(items: T[], key: (item: T) => string): { key: string; items: T[] }[] {
  const groups: { key: string; items: T[] }[] = []

  for (const item of items) {
    const name = key(item)
    const group = groups.find((entry) => entry.key === name)
    group ? group.items.push(item) : groups.push({ key: name, items: [item] })
  }

  return groups
}

/** Die Stufen hinter der aktuellen; die erste ist der Schritt, den "Weiter" nennt. */
export function laterStages<T extends { value: string }>(stages: T[], current: string | null): T[] {
  const at = stages.findIndex((stage) => stage.value === current)

  return at < 0 ? [] : stages.slice(at + 1)
}

/**
 * Überfällig zuerst, dann je Monat des laufenden Jahres, danach je Jahr; was
 * keinen Tag hat, steht zuletzt. Die Zeilen kommen schon nach Fälligkeit sortiert.
 */
export function taskGroups<T extends { dueOn: string | null }>(rows: T[], today: string): { key: string; rows: T[] }[] {
  const groups = groupBy(rows, (row) => {
    if (row.dueOn === null) return 'undated'
    if (row.dueOn < today) return 'late'

    return row.dueOn.slice(0, 4) === today.slice(0, 4) ? row.dueOn.slice(0, 7) : row.dueOn.slice(0, 4)
  })

  return [...groups.filter((group) => group.key !== 'undated'), ...groups.filter((group) => group.key === 'undated')].map(
    ({ key, items }) => ({ key, rows: items }),
  )
}

export function taskGroupLabel(key: string, t: (key: string) => string): string {
  if (key === 'late') return t('project.tasksLate')
  if (key === 'undated') return t('project.tasksUndated')
  if (key.length === 4) return key

  return new Date(`${key}-01T12:00:00`).toLocaleDateString('de-DE', { month: 'long', year: 'numeric' })
}

/** Überfällige Aufgabe vor Angebotsentwurf: was zuerst liegen bleibt, steht oben. */
export function leadNextStep<T extends { isOverdue: boolean; isDone: boolean }>(
  tasks: T[],
  quote: { status: string } | null,
): { kind: 'task'; task: T } | { kind: 'quote' } | null {
  const { step } = splitNextStep(tasks)
  if (step) return { kind: 'task', task: step }

  return quote?.status === 'draft' ? { kind: 'quote' } : null
}
