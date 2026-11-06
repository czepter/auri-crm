/** The row the booked project's rail shows: the first one still waiting on the client. */
export function openDocument<T extends { settled: boolean }>(documents: T[]): T | null {
  return documents.find(document => !document.settled) ?? null
}
