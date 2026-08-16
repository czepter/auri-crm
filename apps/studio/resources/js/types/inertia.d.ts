import '@inertiajs/core'

declare module '@inertiajs/core' {
  export interface InertiaConfig {
    flashDataType: { status: string | null; secret: string | null }
    sharedPageProps: {
      auth: { user: App.Data.StaffProfileData | null; can: App.Enums.Capability[] }
      nav: { leads: number; projects: number } | null
      flash: { status: string | null; secret: string | null }
      portalPreview: boolean
    }
  }
}

export {}
