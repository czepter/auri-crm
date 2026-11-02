/**
 * The Allgemein section shows the slug field wrapped in the address it
 * produces — this is the part before the editable segment: host and prefix.
 */
export function addressPrefix(url: string): string {
  return url.replace(/[^/]*$/, '')
}
