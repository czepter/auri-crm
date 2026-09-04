/**
 * The Allgemein section shows the slug field wrapped in the address it
 * produces — this is the part before the editable segment, whether that
 * segment is today's slug or, before one was ever chosen, the public_id.
 */
export function addressPrefix(url: string): string {
  return url.replace(/[^/]*$/, '')
}
