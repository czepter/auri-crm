declare namespace App {
namespace Automation {
namespace Enums {
export type AutomationAnchor = 'per_shoot' | 'primary_shoot' | 'quote_validity';
export type AutomationOffsetDirection = 'before' | 'after';
export type AutomationRunStatus = 'pending' | 'sent' | 'failed';
export type AutomationTriggerType = 'event' | 'date_offset';
}
}
namespace Catalog {
namespace Data {
export type PackageData = {
id: string,
name: string,
description: string | null,
isActive: boolean,
itemCount: number,
items: App.Catalog.Data.PackageItemData[],
};
export type PackageItemData = {
id: string,
title: string,
description: string | null,
quantity: number,
unitPrice: number | null,
};
}
}
namespace Contracts {
namespace Data {
export type ContractData = {
id: string,
status: string,
statusLabel: string,
templateName: string,
templateVersion: number,
renderedAt: string | null,
downloadUrl: string | null,
};
}
namespace Enums {
export type ContractStatus = 'pending' | 'rendered' | 'failed';
}
}
namespace Crm {
namespace Data {
export type CalendarDayData = {
date: string,
inMonth: boolean,
today: boolean,
entries: App.Crm.Data.CalendarEntryData[],
};
export type CalendarEntryData = {
kind: string,
time: string | null,
what: string,
who: string | null,
projectId: string | null,
};
export type ContactRowData = {
id: string,
name: string,
email: string | null,
phone: string | null,
projectCount: number,
standing: string,
nextDate: string | null,
latestProject: string | null,
};
export type ContactSuggestionData = {
id: string,
name: string,
email: string | null,
phone: string | null,
projectCount: number,
};
export type CrewMemberData = {
id: string,
userId: string,
name: string,
initials: string,
role: string,
roleLabel: string,
};
export type DateConflictData = {
source: string,
projectId: string | null,
projectName: string | null,
venue: string | null,
kind: string | null,
label: string | null,
personId: string | null,
booked: boolean,
};
export type DomainRowData = {
projectId: string,
who: string,
what: string,
emphasis: string | null,
whatTail: string | null,
action: App.Crm.Data.RowActionData | null,
hot: boolean,
};
export type FormFieldData = {
id: string,
label: string,
type: string,
typeLabel: string,
required: boolean,
options: string[] | null,
};
export type FormInstanceData = {
id: string,
name: string,
sourceTemplateName: string | null,
updatedAt: string,
sharedAt: string | null,
fields: App.Crm.Data.FormInstanceFieldData[],
};
export type FormInstanceFieldData = {
id: string,
label: string,
type: string,
typeLabel: string,
required: boolean,
options: string[] | null,
currentAnswer: string | null,
answeredAt: string | null,
};
export type FormTemplateData = {
id: string,
name: string,
projectType: string | null,
projectTypeLabel: string | null,
isActive: boolean,
fieldCount: number,
updatedAt: string,
fields: App.Crm.Data.FormFieldData[],
};
export type InternalNoteData = {
id: string,
body: string,
author: string,
writtenAt: string,
};
export type LeadCardData = {
id: string,
leadId: string,
type: string,
names: string,
date: string | null,
shootCount: number,
venue: string | null,
source: string,
stage: string,
daysInStage: number,
value: number | null,
lastAt: string,
conflicts: App.Crm.Data.DateConflictData[],
};
export type LeadQueueRowData = {
id: string,
name: string,
date: string | null,
snippet: string | null,
open: boolean,
waitingDays: number,
};
export type NextShootData = {
projectId: string,
projectName: string,
shootId: string,
title: string,
date: string,
callTime: string | null,
endTime: string | null,
venue: string | null,
crew: string,
readiness: {
label: string,
done: boolean,
}[],
};
export type ProjectHeaderData = {
id: string,
name: string,
names: string,
type: string,
position: App.Crm.Data.ProjectPositionData,
primaryDate: string | null,
shootCount: number,
isBooked: boolean,
canBook: boolean,
canManageShoots: boolean,
canManageCrew: boolean,
canManageTasks: boolean,
canManageNotes: boolean,
canManageMessages: boolean,
portalVisible: boolean,
canManagePortalVisibility: boolean,
canManageQuotes: boolean,
canManageContracts: boolean,
canManagePaymentPlan: boolean,
canManageForms: boolean,
tabs: {
key: string,
label: string,
href: string,
}[],
};
export type ProjectPersonData = {
id: string,
name: string,
role: string,
email: string | null,
phone: string | null,
};
export type ProjectPositionData = {
source: string,
value: string | null,
label: string,
};
export type ProjectRowData = {
id: string,
name: string,
date: string | null,
venue: string | null,
position: string,
contact: string | null,
};
export type RowActionData = {
label: string,
kind: string,
target: string,
};
export type ShootData = {
id: string,
kind: string,
label: string | null,
date: string | null,
callTime: string | null,
endTime: string | null,
venue: string | null,
status: string,
statusLabel: string,
isPrimary: boolean,
isCancelled: boolean,
cancelledReason: string | null,
crew: App.Crm.Data.CrewMemberData[],
forwardStatuses: {
value: string,
label: string,
}[],
timeline: App.Crm.Data.TimelineEntryData[],
pdfUrl: string,
icsUrl: string,
crewConflicts: App.Crm.Data.DateConflictData[],
};
export type TaskRowData = {
id: string,
projectId: string,
projectName: string,
title: string,
dueOn: string | null,
isOverdue: boolean,
isDone: boolean,
shootLabel: string | null,
audience: string,
audienceLabel: string,
isBlocked: boolean,
blockedBy: string | null,
};
export type TaskTemplateData = {
id: string,
title: string,
audience: string,
audienceLabel: string,
dayOffset: number,
shootKind: string | null,
shootKindLabel: string | null,
isActive: boolean,
sortOrder: number,
};
export type ThreadEntryData = {
key: string,
at: string | null,
body: string,
author: string | null,
system: boolean,
};
export type TimelineEntryData = {
id: string,
time: string,
title: string,
location: string | null,
note: string | null,
isTransit: boolean,
};
}
namespace Enums {
export type BookedVia = 'manual' | 'contract';
export type CaptureChannel = 'phone' | 'email' | 'in_person' | 'web_form' | 'other';
export type CrewRole = 'lead_photographer' | 'second_shooter' | 'assistant';
export type FormFieldType = 'text' | 'short_text' | 'boolean' | 'select' | 'date' | 'time' | 'name_list';
export type LeadSource = 'referral' | 'website' | 'instagram' | 'wedding_fair' | 'other';
export type LeadStage = 'new' | 'contacted' | 'quote_sent' | 'quote_accepted' | 'contract_sent' | 'booked';
export type ProjectContactRole = 'primary' | 'partner' | 'payer' | 'planner' | 'vendor';
export type ProjectType = 'wedding' | 'portrait' | 'other';
export type ShootKind = 'engagement' | 'wedding' | 'reception' | 'portrait' | 'other';
export type ShootStatus = 'requested' | 'booked' | 'prep' | 'shot' | 'culling' | 'editing' | 'ready' | 'delivered' | 'archived';
export type TaskAudience = 'internal' | 'client';
}
}
namespace Data {
export type AbsenceData = {
id: string,
userId: string,
userName: string,
category: string,
categoryLabel: string,
startsOn: string,
endsOn: string,
canManage: boolean,
};
export type StaffProfileData = {
id: string,
name: string,
email: string,
role: string,
mfaConfirmed: boolean,
};
}
namespace Documents {
namespace Data {
export type DocumentTemplateData = {
id: string,
name: string,
body: string,
blocks: Record<string, any> | null,
version: number,
type: string,
typeLabel: string,
shootKind: string | null,
shootKindLabel: string | null,
};
export type MailTemplateData = {
id: string,
name: string,
body: string,
unknownFields: string[],
};
}
namespace Enums {
export type DocumentTemplateType = 'contract' | 'quote' | 'invoice' | 'mail';
}
}
namespace Engagement {
namespace Data {
export type MessageData = {
id: string,
author: string,
fromStaff: boolean,
body: string,
sentAt: string,
deliveryState: string | null,
projectName: string | null,
projectId: string | null,
scheduledFor: string | null,
};
export type ThreadData = {
id: string,
subject: string,
messages: App.Engagement.Data.MessageData[],
};
}
namespace Enums {
export type InboundMessageState = 'threaded' | 'triage' | 'quarantined';
}
}
namespace Enums {
export type AbsenceCategory = 'unavailable' | 'holiday' | 'other';
export type Capability = 'manage_leads' | 'manage_contacts' | 'view_all_projects' | 'book_project' | 'manage_shoots' | 'manage_crew' | 'manage_tasks' | 'manage_notes' | 'manage_messages' | 'manage_staff' | 'manage_packages' | 'manage_portal_access' | 'manage_quotes' | 'manage_contracts' | 'manage_settings' | 'manage_form_templates' | 'manage_dsgvo';
export type StaffRole = 'owner' | 'photographer' | 'second_shooter' | 'editor' | 'assistant';
}
namespace Integration {
namespace Data {
export type ContactReadData = {
id: string,
name: string,
email: string | null,
phone: string | null,
updatedAt: string,
};
export type JobReadData = {
id: string,
projectId: string,
bookedAt: string,
bookedVia: string,
updatedAt: string,
};
export type LeadReadData = {
id: string,
projectId: string,
source: string,
stage: string,
isLost: boolean,
updatedAt: string,
};
export type ProjectReadData = {
id: string,
name: string,
type: string,
portalVisible: boolean,
updatedAt: string,
};
export type ShootReadData = {
id: string,
projectId: string,
kind: string,
label: string | null,
date: string | null,
status: string,
updatedAt: string,
};
}
namespace Enums {
export type WebhookDeliveryStatus = 'pending' | 'delivered' | 'failed';
}
}
namespace Media {
namespace Data {
export type FailedAssetData = {
publicId: string,
filename: string,
error: string,
};
export type MediaIngestTargetData = {
galleryId: string,
galleryTitle: string,
projectId: string,
projectName: string,
shootId: string | null,
shootLabel: string | null,
partSize: number,
};
}
namespace Enums {
export type AssetStatus = 'ready' | 'failed';
export type MultipartUploadStatus = 'in_progress' | 'completed' | 'aborted';
export type ZipBuildScope = 'all' | 'list';
export type ZipBuildSize = 'original' | 'web';
export type ZipBuildStatus = 'building' | 'ready' | 'failed';
}
}
namespace Numbering {
namespace Enums {
export type DocumentType = 'quote' | 'contract' | 'invoice';
export type NumberRangeResetRule = 'yearly' | 'continuous';
}
}
namespace Payments {
namespace Data {
export type PaymentInstallmentData = {
id: string,
amount: number,
dueOn: string,
status: string,
statusLabel: string,
sortOrder: number,
label: string,
settledVia: string | null,
settledViaLabel: string | null,
paidOn: string | null,
paymentReference: string | null,
isLate: boolean,
invoiceNumber: string | null,
invoicedOn: string | null,
hasInvoiceDocument: boolean,
};
export type PaymentPlanData = {
id: string,
total: number,
openTotal: number,
isEditable: boolean,
reference: string | null,
installments: App.Payments.Data.PaymentInstallmentData[],
};
}
namespace Enums {
export type PaymentInstallmentStatus = 'scheduled' | 'paid' | 'invoiced';
export type SettledVia = 'stripe' | 'transfer' | 'manual';
}
}
namespace Quotes {
namespace Data {
export type QuoteData = {
id: string,
predecessorId: string | null,
status: string,
statusLabel: string,
currency: string,
validUntil: string | null,
number: string | null,
sentAt: string | null,
total: number,
discountType: string | null,
discountValue: number | null,
discountAmount: number,
net: number,
vatMode: string | null,
vatRate: number | null,
vatNote: string | null,
vatAmount: number | null,
items: App.Quotes.Data.QuoteItemData[],
};
export type QuoteItemData = {
id: string,
title: string,
description: string | null,
quantity: number,
unitPrice: number | null,
isIncluded: boolean,
lineTotal: number,
discountType: string | null,
discountValue: number | null,
discountAmount: number,
netTotal: number,
};
}
namespace Enums {
export type DiscountType = 'percentage' | 'amount';
export type QuoteStatus = 'draft' | 'sent' | 'accepted' | 'superseded' | 'expired';
}
}
namespace Settings {
namespace Data {
export type NumberRangeData = {
id: string,
documentType: string,
documentTypeLabel: string,
prefix: string,
resetRule: string,
startingCounter: number,
canSetStartingCounter: boolean,
nextNumberPreview: string,
};
export type PaymentSettingsData = {
id: string,
stripeEnabled: boolean,
stripeMode: string,
hasStripeTestKeys: boolean,
hasStripeLiveKeys: boolean,
remindersEnabled: boolean,
depositPercent: number,
zahlungszielDays: number,
};
export type PortalSettingsData = {
id: string,
maintenanceEnabled: boolean,
maintenanceMessage: string | null,
welcomeText: string | null,
datenschutzUrl: string | null,
agbUrl: string | null,
acceptQuotesEnabled: boolean,
signContractsEnabled: boolean,
paymentStatusEnabled: boolean,
messagingMode: string,
welcomeInquiry: string | null,
welcomeMany: string | null,
galleryExpiryDays: number | null,
galleryRequiresSettledBalance: boolean,
galleryDownloadsEnabled: boolean,
galleryListsEnabled: boolean,
galleryCommentsEnabled: boolean,
dsgvoRecipient: string | null,
dsgvoDeadlineDays: number,
contactPhone: string | null,
contactEmail: string | null,
contactResponseTime: string | null,
};
export type StudioClosureData = {
id: string,
label: string,
startsOn: string,
endsOn: string,
};
export type StudioSettingsData = {
id: string,
displayName: string | null,
legalName: string | null,
legalForm: string | null,
street: string | null,
postcode: string | null,
city: string | null,
country: string | null,
taxNumber: string | null,
vatId: string | null,
registerNumber: string | null,
registerCourt: string | null,
representative: string | null,
website: string | null,
accentColor: string | null,
fontPair: string | null,
logoUrl: string | null,
bankAccountHolder: string | null,
bankIban: string | null,
bankBic: string | null,
vatMode: string,
vatRate: number,
vatNote: string | null,
quoteValidityDays: number,
paymentTermDays: number,
depositPercentage: number,
widerrufIncluded: boolean,
widerrufText: string | null,
documentLetterheadHtml: string | null,
documentFooterHtml: string | null,
timezone: string,
locale: string,
workingDays: number[],
mailFromName: string,
mailFromAddress: string,
mailReplyTo: string | null,
mailSignature: string | null,
mailBcc: string | null,
leadTypePresets: string[],
leadSourcePresets: string[],
leadChannelPresets: string[],
};
}
namespace Enums {
export type FontPair = 'instrument' | 'fraunces' | 'cormorant' | 'grotesk';
}
}
}
