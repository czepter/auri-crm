import '@inertiajs/core'

declare module '@inertiajs/core' {
  export interface InertiaConfig {
    flashDataType: { status: string | null }
    sharedPageProps: {
      auth: { user: App.Data.StaffProfileData | null; can: App.Enums.Capability[] }
      nav: { leads: number; projects: number } | null
    }
  }
}

export {}
