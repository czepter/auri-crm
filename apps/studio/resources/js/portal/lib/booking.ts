import { usePage } from '@inertiajs/vue3'

// Until a project is booked the client has an Anfrage, not a Buchung.
export function bookingKey(name: 'navBooking' | 'navBookings' | 'toBooking' | 'notFoundBooking'): string {
  const booked = (usePage().props.portalNav as { booked?: boolean } | null | undefined)?.booked ?? true

  return `portal.${booked ? name : name.replace('Booking', 'Request')}`
}
