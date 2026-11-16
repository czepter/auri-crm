import type { QuoteContact } from '@/admin/components/quote/quotePayload'

/*
 * Ein Fall für alle Bildschirme: die Hochzeit Rahn als gebuchter Auftrag, die
 * Anfrage Brandt als noch offener Fall. Zwei Datensätze statt einer, weil
 * gebucht und ungebucht andere Reiter, andere Aktionen und andere Karten
 * tragen — und weil ein Katalog beides nebeneinander zeigen muss.
 *
 * Beträge in Cent, wie im Katalog und in den Angeboten.
 */

type Option = { value: string; label: string }

const RAHN = 'p-rahn'
const BRANDT = 'p-brandt'

export const types: Option[] = [
    { value: 'wedding', label: 'Hochzeit' },
    { value: 'portrait', label: 'Porträt' },
    { value: 'family', label: 'Familie' },
    { value: 'business', label: 'Business' },
    { value: 'other', label: 'Sonstige' },
]

export const sources: Option[] = [
    { value: 'referral', label: 'Empfehlung' },
    { value: 'website', label: 'Website' },
    { value: 'instagram', label: 'Instagram' },
    { value: 'wedding_fair', label: 'Hochzeitsmesse' },
    { value: 'other', label: 'Sonstige' },
]

export const channels: Option[] = [
    { value: 'phone', label: 'Telefonisch' },
    { value: 'email', label: 'Per E-Mail' },
    { value: 'in_person', label: 'Persönlich' },
    { value: 'other', label: 'Sonstige' },
]

export const stages: (Option & { anchor: string | null })[] = [
    { value: 'new', label: 'Neu', anchor: 'entry' },
    { value: 'contacted', label: 'Kontaktiert', anchor: null },
    { value: 'quote_sent', label: 'Angebot', anchor: 'quote_sent' },
    { value: 'quote_accepted', label: 'Angebot angenommen', anchor: 'quote_accepted' },
    { value: 'booked', label: 'Gebucht', anchor: 'won' },
]

export const presets = {
    type: ['Taufe', 'Firmenporträt'],
    source: ['Fotografenkollegin', 'Gut Basthorst'],
    channel: ['WhatsApp'],
}

/** Was der Stufenbalken zeigt, wenn die Anfrage beim Angebot steht. */
const leadTrack = [
    { key: 'new', label: 'Neu', pipeline: 'lead', state: 'done' },
    { key: 'contacted', label: 'Kontaktiert', pipeline: 'lead', state: 'done' },
    { key: 'quote_sent', label: 'Angebot', pipeline: 'lead', state: 'current' },
    { key: 'quote_accepted', label: 'Angebot angenommen', pipeline: 'lead', state: 'todo' },
    { key: 'booked', label: 'Gebucht', pipeline: 'lead', state: 'todo' },
]

const projectTrack = [
    { key: 'requested', label: 'Angefragt', pipeline: 'project', state: 'done' },
    { key: 'booked', label: 'Gebucht', pipeline: 'project', state: 'current' },
    { key: 'shot', label: 'Fotografiert', pipeline: 'project', state: 'todo' },
    { key: 'culling', label: 'Auswahl', pipeline: 'project', state: 'todo' },
    { key: 'editing', label: 'Bearbeitung', pipeline: 'project', state: 'todo' },
    { key: 'ready', label: 'Fertig', pipeline: 'project', state: 'todo' },
    { key: 'delivered', label: 'Übergeben', pipeline: 'project', state: 'todo' },
]

export const people: App.Crm.Data.CasePersonData[] = [
    { id: 'c-jana', name: 'Jana Rahn', role: 'primary', email: 'jana.rahn@posteo.de', phone: '0176 2841 9930' },
    { id: 'c-michael', name: 'Michael Rahn', role: 'partner', email: 'michael.rahn@posteo.de', phone: null },
]

export const leadPeople: App.Crm.Data.CasePersonData[] = [
    { id: 'c-lena', name: 'Lena Brandt', role: 'primary', email: 'lena.brandt@web.de', phone: '0151 7742 1108' },
    { id: 'c-tom', name: 'Tom Brandt', role: 'partner', email: null, phone: null },
    { id: 'c-kerstin', name: 'Kerstin Voß', role: 'vendor', email: 'kontakt@freie-trauung-voss.de', phone: null },
]

function shootOf(row: Partial<App.Crm.Data.ShootData> & Pick<App.Crm.Data.ShootData, 'id'>): App.Crm.Data.ShootData {
    return {
        kind: 'wedding',
        label: null,
        date: null,
        callTime: null,
        endTime: null,
        venue: null,
        venueName: null,
        venueCity: null,
        status: 'requested',
        statusLabel: 'Angefragt',
        isPrimary: true,
        isCancelled: false,
        cancelledReason: null,
        crew: [],
        forwardStatuses: [],
        dueStatus: null,
        timeline: [],
        pdfUrl: `/shootings/${row.id}/ablauf.pdf`,
        icsUrl: `/shootings/${row.id}.ics`,
        crewConflicts: [],
        ...row,
    }
}

export const shoots: App.Crm.Data.ShootData[] = [
    shootOf({
        id: 's-rahn',
        date: '2026-09-13',
        callTime: '13:30',
        endTime: '23:00',
        venue: 'Gut Basthorst, Basthorst',
        venueName: 'Gut Basthorst',
        venueCity: 'Basthorst',
        status: 'booked',
        statusLabel: 'Gebucht',
        forwardStatuses: [{ value: 'shot', label: 'Fotografiert' }],
        dueStatus: null,
        crew: [
            { id: 'cr-1', userId: 'u1', name: 'Christian Zepter', initials: 'CZ' },
        ],
    }),
]

export const leadShoots: App.Crm.Data.ShootData[] = [
    shootOf({
        id: 's-brandt',
        date: '2027-06-12',
        callTime: '14:00',
        venue: 'Gut Basthorst, Basthorst',
        venueName: 'Gut Basthorst',
        venueCity: 'Basthorst',
    }),
]

function task(row: Partial<App.Crm.Data.TaskRowData> & Pick<App.Crm.Data.TaskRowData, 'id' | 'title'>): App.Crm.Data.TaskRowData {
    return {
        projectId: RAHN,
        projectName: 'Hochzeit Rahn',
        dueOn: null,
        isOverdue: false,
        isDone: false,
        shootId: null,
        shootLabel: null,
        audience: 'internal',
        audienceLabel: 'Intern',
        isBlocked: false,
        blockedBy: null,
        completedOn: null,
        dueAnchorLabel: null,
        assigneeId: null,
        assigneeName: null,
        contactId: null,
        contactName: null,
        ...row,
    }
}

export const tasks: App.Crm.Data.TaskRowData[] = [
    task({ id: 't-1', title: 'Ablauf mit Gut Basthorst abstimmen', dueOn: '2026-08-30' }),
    task({ id: 't-2', title: 'Zweitfotografin anfragen', dueOn: '2026-07-15', isOverdue: true }),
    task({ id: 't-3', title: 'Anzahlung geprüft', isDone: true, completedOn: '2026-03-02' }),
]

export const leadTasks: App.Crm.Data.TaskRowData[] = [
    task({
        id: 't-b1',
        projectId: BRANDT,
        projectName: 'Familie Brandt — Hochzeit',
        title: 'Nach dem Angebot nachfassen',
        dueOn: '2026-09-26',
    }),
]

export const thread: App.Crm.Data.ThreadEntryData[] = [
    { key: 'n-r2', at: '2026-03-02T09:12:00+01:00', body: 'Anzahlung eingegangen.', author: 'Christian Zepter', system: true, note: null },
    { key: 'n-r1', at: '2026-02-24T18:40:00+01:00', body: 'Jana möchte die Trauung ab 14:30 fotografiert haben, Sektempfang im Innenhof.', author: 'Christian Zepter', system: false, note: null },
]

export const leadThread: App.Crm.Data.ThreadEntryData[] = [
    { key: 'n-b3', at: '2026-09-08T11:02:00+02:00', body: 'Angebot 2026-0042 versendet.', author: 'Christian Zepter', system: true, note: null },
    { key: 'n-b2', at: '2026-09-05T16:20:00+02:00', body: 'Telefonat: Kerstin Voß hält die freie Trauung, Beginn gegen 14 Uhr.', author: 'Christian Zepter', system: false, note: null },
    { key: 'n-b1', at: '2026-09-04T08:45:00+02:00', body: 'Anfrage über die Website.', author: 'Christian Zepter', system: true, note: null },
]

export const queue: { key: string; rows: App.Crm.Data.LeadQueueRowData[] }[] = [
    {
        key: 'unbeantwortet',
        rows: [
            { id: 'p-osterhoff', name: 'Familie Osterhoff', date: '2027-04-24', text: 'Standesamt und Feier im Garten', open: false, waitingDays: 6, step: 'reply' },
        ],
    },
    {
        key: 'angebot',
        rows: [
            { id: BRANDT, name: 'Familie Brandt', date: '2027-06-12', text: 'Gut Basthorst, freie Trauung', open: true, waitingDays: 4, step: 'reply' },
            { id: 'p-ahrens', name: 'Marie Ahrens & David Kroll', date: '2026-07-25', text: 'Kaispeicher Hamburg', open: false, waitingDays: 11, step: 'reply' },
        ],
    },
    {
        key: 'angenommen',
        rows: [
            { id: 'p-rahn', name: 'Hochzeit Rahn', date: '2026-09-13', text: 'Gut Basthorst', open: false, waitingDays: 2, step: 'reply' },
        ],
    },
]

export const week: { date: string; what: string; projectId: string }[] = [
    { date: '2027-06-11', what: 'Standesamt Ahrens — Hamburg', projectId: 'p-ahrens' },
    { date: '2027-06-13', what: 'Porträt Osterhoff — Atelier', projectId: 'p-osterhoff' },
]

export const conflicts: App.Crm.Data.DateConflictData[] = [
    {
        source: 'shoot',
        projectId: 'p-ahrens',
        projectName: 'Hochzeit Ahrens & Kroll',
        venue: 'Kaispeicher, Hamburg',
        kind: 'wedding',
        label: 'Hochzeit Ahrens & Kroll · Kaispeicher',
        personId: null,
        booked: true,
    },
]

export function quoteItem(
    item: Partial<App.Quotes.Data.QuoteItemData> & Pick<App.Quotes.Data.QuoteItemData, 'id' | 'title' | 'lineTotal'>,
): App.Quotes.Data.QuoteItemData {
    return {
        description: null,
        quantity: 1,
        unitPrice: item.lineTotal,
        isIncluded: false,
        discountType: null,
        discountValue: null,
        discountAmount: 0,
        netTotal: item.lineTotal,
        vatRate: null,
        selectable: false,
        selected: true,
        outcome: null,
        hasImage: false,
        ...item,
    }
}

const rahnItems: App.Quotes.Data.QuoteItemData[] = [
    quoteItem({ id: 'qi-r1', title: 'Hochzeitsreportage Ganztag', description: 'bis 14 Std. am Hochzeitstag, Bearbeitung, Online-Galerie', lineTotal: 360000 }),
    quoteItem({ id: 'qi-r2', title: 'Zweitfotografin', quantity: 1, lineTotal: 66000 }),
]

const brandtItems: App.Quotes.Data.QuoteItemData[] = [
    quoteItem({ id: 'qi-b1', title: 'Hochzeitsreportage Ganztag', description: 'bis 14 Std. am Hochzeitstag, Bearbeitung, Online-Galerie', lineTotal: 300000 }),
    quoteItem({ id: 'qi-b2', title: 'Fotobuch 30×30', quantity: 1, lineTotal: 48000 }),
]

export function quoteOf(
    row: Partial<App.Quotes.Data.QuoteData> & Pick<App.Quotes.Data.QuoteData, 'id' | 'number' | 'net' | 'items'>,
): App.Quotes.Data.QuoteData {
    return {
        createdAt: null,
        showcaseCount: 0,
        predecessorId: null,
        isAddendum: false,
        predecessorNumber: null,
        vatLines: [],
        status: 'sent',
        statusLabel: 'Versendet',
        pricingMode: 'classic',
        sentChannel: null,
        currency: 'EUR',
        validUntil: null,
        sentAt: null,
        acceptedAt: null,
        total: row.net,
        discountType: null,
        discountValue: null,
        discountAmount: 0,
        discountHidden: false,
        vatMode: 'exempt',
        vatRate: 0,
        vatNote: 'Kleinunternehmer nach § 19 UStG',
        vatAmount: 0,
        intro: null,
        outro: null,
        hourlyRate: null,
        minHours: null,
        maxHours: null,
        imagesPerHour: null,
        hours: null,
        timeTitle: null,
        timeDescription: null,
        timeSortOrder: 0,
        rangeMin: null,
        rangeMax: null,
        paymentStages: [],
        recipientContact: null,
        recipientStreet: null,
        recipientPostcode: null,
        recipientCity: null,
        portalUrl: null,
        ...row,
    }
}

export const quote: App.Quotes.Data.QuoteData = quoteOf({
    id: 'q-rahn',
    number: '2026-0031',
    status: 'accepted',
    statusLabel: 'Angenommen',
    sentAt: '2026-02-26T10:00:00+01:00',
    net: 426000,
    items: rahnItems,
    paymentStages: [
        { id: 'ps-1', percent: 30, label: 'Anzahlung', minimum: 0, note: null, dueOn: null },
        { id: 'ps-2', percent: 70, label: 'Restzahlung', minimum: 0, note: null, dueOn: null },
    ],
})

export const leadQuote: App.Quotes.Data.QuoteData = quoteOf({
    id: 'q-brandt',
    number: '2026-0042',
    sentAt: '2026-09-08T11:02:00+02:00',
    validUntil: '2026-10-08',
    net: 348000,
    items: brandtItems,
})

/** Die Pakete als Kacheln, solange kein Angebot geschrieben ist. */
export const packages: { id: string; name: string; itemCount: number; total: number }[] = [
    { id: 'pk-ganztag', name: 'Ganztag', itemCount: 4, total: 360000 },
    { id: 'pk-halbtag', name: 'Halbtag', itemCount: 3, total: 215000 },
    { id: 'pk-standesamt', name: 'Standesamt', itemCount: 2, total: 98000 },
]

export const catalogPackages: App.Catalog.Data.PackageData[] = [
    {
        id: 'pk-ganztag',
        name: 'Ganztag',
        description: 'Getting Ready bis Tanz',
        isActive: true,
        itemCount: 2,
        items: [
            { id: 'pi-1', title: 'Hochzeitsreportage Ganztag', description: 'bis 14 Std. am Hochzeitstag', quantity: 1, unitPrice: 300000, vatRate: null, selectable: false, selected: true, outcome: null, hasImage: false },
            { id: 'pi-2', title: 'Online-Galerie', description: null, quantity: 1, unitPrice: 0, vatRate: null, selectable: false, selected: true, outcome: null, hasImage: false },
        ],
        portalOffered: false,
        portalPriceMode: 'exact',
        portalRequiresDate: true,
        projectTypeKey: null,
        portalHasImage: false,
        price: 300000,
        requestedCount: 0,
        bookedCount: 0,
        showcaseCount: 0, galleryPresetId: null,
    },
]

export const contacts: QuoteContact[] = [
    { id: 'c-brandt', name: 'Katrin Brandt', street: 'Am Burgfeld 4', postcode: '23568', city: 'Lübeck' },
    { id: 'c-brandt-sven', name: 'Sven Brandt', street: null, postcode: null, city: null },
]

export const catalogItems: App.Catalog.Data.CatalogItemData[] = [
    { id: 'ci-1', title: 'Zweitfotografin', description: 'ganztägig', unitPrice: 66000 },
    { id: 'ci-2', title: 'Fotobuch 30×30', description: '40 Seiten, Leinen', unitPrice: 48000 },
    { id: 'ci-3', title: 'Anreise über 50 km', description: null, unitPrice: 4500 },
]

/** Der Nachrichtenreiter: ein Thread, auf den die Kundin zuletzt geschrieben hat. */
export const conversation: App.Engagement.Data.ThreadData = {
    id: 'th-brandt',
    subject: 'Hochzeit am 12.06.2027',
    recipients: ['lena.brandt@web.de'],
    messages: [
        {
            id: 'm-1',
            author: 'Christian Zepter',
            fromStaff: true,
            body: 'Hallo Lena, der Termin ist bei mir noch frei. Angebot 2026-0042 liegt anbei.',
            sentAt: '2026-09-08T11:02:00+02:00',
            deliveryState: 'delivered',
            projectName: 'Familie Brandt — Hochzeit',
            projectId: BRANDT,
            scheduledFor: null,
        },
        {
            id: 'm-2',
            author: 'Lena Brandt',
            fromStaff: false,
            body: 'Danke! Wir besprechen es am Wochenende und melden uns Anfang nächster Woche.',
            sentAt: '2026-09-09T20:14:00+02:00',
            deliveryState: null,
            projectName: 'Familie Brandt — Hochzeit',
            projectId: BRANDT,
            scheduledFor: null,
        },
    ],
}

const permissions = {
    canBook: true,
    canManageShoots: true,
    canManageCrew: true,
    canManageTasks: true,
    canManageNotes: true,
    canManageMessages: true,
    canManagePortalVisibility: true,
    canManageQuotes: true,
    canManageContracts: true,
    canManagePaymentPlan: true,
    primaryShootCancelled: false,
    canManageForms: true,
}

/** Der gebuchte Auftrag: sieben Reiter, Stufenbalken in der Projektpipeline. */
export const header: App.Crm.Screens.CaseHeaderData = {
    id: RAHN,
    name: 'Hochzeit Rahn',
    names: 'Jana und Michael Rahn',
    type: 'Hochzeit',
    position: { source: 'shoot', value: 'booked', label: 'Gebucht' },
    primaryDate: '2026-09-13',
    shootCount: 1,
    isBooked: true,
    ...permissions,
    portalVisible: true,
    outcomeLabel: null,
    lostReason: null,
    canClose: true,
    isCompleted: false,
    canComplete: true,
    canDelete: false,
    tabs: [
        { key: 'overview', label: 'project.overview', href: `/auftraege/${RAHN}`, count: null, countTo: null, children: [] },
        { key: 'shoots', label: 'project.shoots', href: `/auftraege/${RAHN}/shootings`, count: null, countTo: null, children: [] },
        { key: 'messages', label: 'project.messages', href: `/auftraege/${RAHN}/nachrichten`, count: 0, countTo: null, children: [] },
        { key: 'galleries', label: 'contacts.galleries', href: `/auftraege/${RAHN}/galerien`, count: 0, countTo: null, children: [] },
        {
            key: 'documents',
            label: 'project.documents',
            href: `/auftraege/${RAHN}/angebote`,
            count: 1,
            countTo: null,
            children: [
                { key: 'quotes', label: 'project.quotes', translate: true, href: `/auftraege/${RAHN}/angebote`, count: 0, reorderable: false },
                { key: 'contract', label: 'project.contract', translate: true, href: `/auftraege/${RAHN}/vertrag`, count: 0, reorderable: false },
                { key: 'forms', label: 'project.forms', translate: true, href: `/auftraege/${RAHN}/frageboegen`, count: 1, reorderable: false },
                { key: 'invoices', label: 'project.invoices', translate: true, href: `/auftraege/${RAHN}/rechnungen`, count: 1, reorderable: false },
            ],
        },
    ],
    track: projectTrack,
    queue: [],
    people,
    paymentPlan: null,
    galleries: [],
    history: thread,
    nextAction: null,
}

/** Dieselbe Kopfzeile vor der Buchung: drei Reiter, Stufenbalken in der Anfragepipeline. */
export const leadHeader: App.Crm.Screens.CaseHeaderData = {
    id: BRANDT,
    name: 'Familie Brandt — Hochzeit',
    names: 'Lena und Tom Brandt',
    type: 'Hochzeit',
    position: { source: 'lead', value: 'quote_sent', label: 'Angebot' },
    primaryDate: '2027-06-12',
    shootCount: 1,
    isBooked: false,
    ...permissions,
    portalVisible: false,
    outcomeLabel: null,
    lostReason: null,
    canClose: true,
    isCompleted: false,
    canComplete: false,
    canDelete: true,
    tabs: [
        { key: 'overview', label: 'project.overview', href: `/anfragen/${BRANDT}`, count: null, countTo: null, children: [] },
        { key: 'quotes', label: 'project.quotes', href: `/anfragen/${BRANDT}/angebot`, count: 1, countTo: null, children: [] },
        { key: 'messages', label: 'project.messages', href: `/anfragen/${BRANDT}/nachrichten`, count: 1, countTo: null, children: [] },
    ],
    track: leadTrack,
    queue: [],
    people: leadPeople,
    paymentPlan: null,
    galleries: [],
    history: leadThread,
    nextAction: null,
}

/** Die `lead`-Prop des Anfragebildschirms — kein eigener Data-Typ, deshalb hier ausgeschrieben. */
export const lead = {
    id: BRANDT,
    projectId: BRANDT,
    name: 'Familie Brandt — Hochzeit',
    type: 'wedding',
    typeOther: null,
    typeLabel: 'Hochzeit',
    source: 'website',
    sourceOther: null,
    sourceLabel: 'Website',
    stage: 'quote_sent',
    stageLabel: 'Angebot',
    inquiryText:
        'Hallo, wir heiraten am 12. Juni 2027 auf Gut Basthorst. Die freie Trauung hält Kerstin Voß, danach Sektempfang im Innenhof und abends Feier in der Scheune. Wir sind ungefähr 70 Gäste und suchen jemanden, der den ganzen Tag dabei ist.',
    capturedVia: 'Über die Website',
    capturedAt: '2026-09-04T08:45:00+02:00',
    lostReason: null as string | null,
    outcomeLabel: null as string | null,
}

export const studioSettings: App.Settings.Data.StudioSettingsData = {
  id: 'st1',
  displayName: 'Atelier Nordlicht',
  legalName: 'Atelier Nordlicht — Christian Zepter',
  legalForm: 'Einzelunternehmen',
  street: 'Bei den Mühren 7',
  postcode: '20457',
  city: 'Hamburg',
  country: 'Deutschland',
  taxNumber: '43/123/45678',
  vatId: 'DE812345678',
  registerNumber: null,
  registerCourt: null,
  representative: 'Christian Zepter',
  website: 'https://atelier-nordlicht.de',
  accentColor: '#2F4858',
  fontPair: 'fraunces',
  logoUrl: null,
  bankAccountHolder: 'Christian Zepter',
  bankIban: 'DE02120300000000202051',
  bankBic: 'BYLADEM1001',
  vatMode: 'standard',
  vatRate: 1900,
  vatNote: null,
  quoteValidityDays: 14,
  paymentTermDays: 14,
  depositPercentage: 30,
  documentLayout: 'classic',
  remindersEnabled: true,
  clientRegister: 'informal',
  documentLetterheadHtml: null,
  documentFooterHtml: null,
  timezone: 'Europe/Berlin',
  workingDays: [1, 2, 3, 4, 5],
  mailFromName: 'Atelier Nordlicht',
  mailFromAddress: 'post@atelier-nordlicht.de',
  mailReplyTo: 'christian@atelier-nordlicht.de',
  mailSignature: 'Christian Zepter\nAtelier Nordlicht',
  mailBcc: null,
  mailClientsEnabled: true,
  mailDefaults: {},
  mailHeaderName: true,
  mailFooterLegal: true,
  mailFooterExtra: null,
}
