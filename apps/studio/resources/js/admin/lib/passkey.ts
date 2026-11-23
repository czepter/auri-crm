export function passkeysSupported(): boolean {
  return typeof window.PublicKeyCredential?.parseRequestOptionsFromJSON === 'function'
}

export function ceremonyCancelled(error: unknown): boolean {
  return error instanceof DOMException && (error.name === 'NotAllowedError' || error.name === 'AbortError')
}
