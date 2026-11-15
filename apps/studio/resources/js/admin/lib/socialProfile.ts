const HANDLE_URL: Record<string, (handle: string) => string> = {
  instagram: (h) => `https://www.instagram.com/${h}`,
  facebook: (h) => `https://www.facebook.com/${h}`,
  tiktok: (h) => `https://www.tiktok.com/@${h}`,
  pinterest: (h) => `https://www.pinterest.com/${h}`,
  linkedin: (h) => `https://www.linkedin.com/in/${h}`,
}

const HTTP = /^https?:\/\//i

function bare(value: string) {
  return value.trim().replace(/^@/, '')
}

// Handles may contain dots (anna.und.ben), so only a slash marks a link.
function isLink(platform: string, value: string) {
  return platform === 'website' || HTTP.test(value.trim()) || value.includes('/')
}

export function profileHref(platform: string, value: string) {
  const trimmed = value.trim()

  if (HTTP.test(trimmed)) {
    return trimmed
  }

  return isLink(platform, trimmed) ? `https://${bare(trimmed)}` : HANDLE_URL[platform](bare(trimmed))
}

export function profileText(platform: string, value: string) {
  if (isLink(platform, value)) {
    return bare(value.trim().replace(HTTP, ''))
      .replace(/^www\./i, '')
      .replace(/\/+$/, '')
  }

  return `@${bare(value)}`
}
