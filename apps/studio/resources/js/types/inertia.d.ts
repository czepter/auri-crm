import '@inertiajs/core'

declare module '@inertiajs/core' {
  export interface InertiaConfig {
    flashDataType: { status: string | null; secret: string | null }
    sharedPageProps: {
      auth: { user: App.Data.StaffProfileData | null; can: App.Enums.Capability[] }
      nav: { leads: number; projects: number; unread: number } | null
      release?: { installed: string; updateAvailable: boolean }
      live: string | null
      flash: { status: string | null; secret: string | null }
      portalPreview: boolean
      soloStudio: boolean
      settingsLinks: { stages: boolean; templates: boolean } | null
      studioDeletion: string | null
      brand: string
    }
  }
}

export {}
