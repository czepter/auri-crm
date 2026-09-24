declare namespace App {
namespace Automation {
namespace Data {
export type AutomationRuleData = {
id: string,
name: string,
triggerLabel: string,
recipient: string,
recipientLabel: string,
condition: string | null,
conditionLabel: string | null,
isActive: boolean,
lastRunLabel: string | null,
subject: string,
body: string,
triggerType: string,
eventLabel: string | null,
anchor: string | null,
offsetDays: number | null,
direction: string | null,
projectType: string | null,
action: string,
};
export type AutomationRunData = {
id: string,
firedAt: string,
anchorLabel: string,
status: string,
statusLabel: string,
};
}
namespace Enums {
export type AutomationAction = 'notice' | 'task' | 'mail_draft';
export type AutomationAnchor = 'per_shoot' | 'primary_shoot' | 'quote_validity' | 'installment_due' | 'gallery_expiry' | 'questionnaire_due' | 'delivered' | 'quote_sent' | 'inquiry_captured';
export type AutomationCondition = 'payment_open' | 'questionnaire_open' | 'gallery_unopened';
export type AutomationOffsetDirection = 'before' | 'after';
export type AutomationRecipient = 'client' | 'crew' | 'studio';
export type AutomationRunStatus = 'pending' | 'sent' | 'failed' | 'skipped' | 'awaiting_approval';
export type AutomationTriggerType = 'event' | 'date_offset';
}
}
namespace Catalog {
namespace Data {
export type CatalogItemData = {
id: string,
title: string,
description: string | null,
unitPrice: number | null,
};
export type PackageData = {
id: string,
name: string,
description: string | null,
isActive: boolean,
itemCount: number,
items: App.Catalog.Data.PackageItemData[],
portalOffered: boolean,
portalPriceMode: string,
portalRequiresDate: boolean,
projectTypeKey: string | null,
portalHasImage: boolean,
price: number | null,
requestedCount: number,
bookedCount: number,
showcaseCount: number,
};
export type PackageItemData = {
id: string,
title: string,
description: string | null,
quantity: number,
unitPrice: number | null,
vatRate: number | null,
selectable: boolean,
selected: boolean,
outcome: string | null,
hasImage: boolean,
};
export type PortalServiceData = {
id: string,
name: string,
description: string | null,
price: number | null,
priceMode: string,
requiresDate: boolean,
hasImage: boolean,
items: App.Catalog.Data.PortalServiceItemData[],
};
export type PortalServiceItemData = {
title: string,
description: string | null,
selectable: boolean,
selected: boolean,
addOnPrice: number | null,
};
export type ServiceSuggestionData = {
id: string,
contact: string,
package: string,
packageName: string,
note: string | null,
author: string | null,
suggestedOn: string,
live: boolean,
};
}
namespace Enums {
export type PortalPriceMode = 'exact' | 'from' | 'on_request';
}
}
namespace Contracts {
namespace Data {
export type ContractData = {
id: string,
version: number,
state: string,
stateLabel: string,
status: string,
statusLabel: string,
templateName: string,
templateVersion: number,
body: string,
blocks: Record<string, any> | null,
renderedAt: string | null,
sentAt: string | null,
downloadUrl: string | null,
signDeadlineOn: string | null,
signers: App.Contracts.Data.ContractSignerData[],
};
export type ContractRowData = {
id: string,
projectId: string,
projectName: string,
contact: string | null,
version: number,
state: string,
status: string,
sentOn: string | null,
signedOn: string | null,
deadlineOn: string | null,
hasDocument: boolean,
};
export type ContractSignerData = {
id: string,
name: string,
role: string | null,
signedAt: string | null,
remindedAt: string | null,
};
}
namespace Enums {
export type ContractState = 'draft' | 'valid' | 'superseded';
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
export type CaseHeaderData = {
id: string,
name: string,
names: string,
type: string,
position: App.Crm.Data.CasePositionData,
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
primaryShootCancelled: boolean,
canManageForms: boolean,
outcomeLabel: string | null,
lostReason: string | null,
canClose: boolean,
isCompleted: boolean,
canComplete: boolean,
canDelete: boolean,
tabs: {
key: string,
label: string,
href: string,
count: number | null,
countTo: number | null,
children: {
key: string,
label: string,
translate: boolean,
href: string,
count: number | null,
reorderable: boolean,
}[],
}[],
track: {
key: string,
label: string,
pipeline: string,
state: string,
}[],
queue: {
key: string,
rows: App.Crm.Data.ProjectQueueRowData[],
}[],
people: App.Crm.Data.CasePersonData[],
paymentPlan: App.Payments.Data.PaymentPlanData | null,
galleries: {
id: string,
title: string | null,
expiresAt: string | null,
}[],
history: App.Crm.Data.ThreadEntryData[],
};
export type CasePersonData = {
id: string,
name: string,
role: string,
email: string | null,
phone: string | null,
};
export type CasePositionData = {
source: string,
value: string | null,
label: string,
};
export type CaseSuggestionData = {
id: string,
name: string,
contactName: string | null,
isBooked: boolean,
galleryDefaults: {
downloads: boolean | null,
lists: boolean | null,
comments: boolean | null,
},
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
export type CrewCandidateData = {
id: string,
name: string,
status: string,
note: string | null,
};
export type CrewMemberData = {
id: string,
userId: string,
name: string,
initials: string,
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
options: Record<string | number, any> | null,
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
options: Record<string | number, any> | null,
currentAnswer: string | null,
answeredAt: string | null,
groupPhotos: {
help: string | null,
suggestions: string[],
minutesPerGroup: number,
} | null,
fileFormats: string[] | null,
fileMaxSize: number | null,
fileMultiple: boolean,
files: App.Crm.Data.FormInstanceFileData[],
};
export type FormInstanceFileData = {
id: string,
name: string,
size: number,
uploadedAt: string,
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
type: string,
typeKey: string,
names: string,
date: string | null,
shootCount: number,
venue: string | null,
source: string | null,
stage: string,
daysInStage: number,
stale: boolean,
value: number | null,
lastAt: string,
conflicts: App.Crm.Data.DateConflictData[],
};
export type LeadQueueRowData = {
id: string,
name: string,
date: string | null,
text: string | null,
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
export type ProjectGalleryData = {
id: string,
title: string | null,
shootId: string | null,
state: string,
assetCount: number,
version: number | null,
expiresAt: string | null,
inPortal: boolean,
};
export type ProjectQueueRowData = {
id: string,
name: string,
date: string | null,
stage: string,
reason: string | null,
days: number | null,
open: boolean,
};
export type ProjectRowData = {
id: string,
name: string,
type: string,
typeKey: string,
date: string | null,
venue: string | null,
position: string,
positionKey: string | null,
phase: string,
contact: string | null,
openTasks: number,
taskCount: number,
value: number | null,
lastAt: string | null,
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
venueName: string | null,
venueCity: string | null,
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
projectId: string | null,
projectName: string | null,
title: string,
dueOn: string | null,
isOverdue: boolean,
isDone: boolean,
shootId: string | null,
shootLabel: string | null,
audience: string,
audienceLabel: string,
isBlocked: boolean,
blockedBy: string | null,
completedOn: string | null,
dueAnchorLabel: string | null,
assigneeId: string | null,
assigneeName: string | null,
contactId: string | null,
contactName: string | null,
};
export type TaskTemplateData = {
id: string,
title: string,
audience: string,
audienceLabel: string,
projectType: string | null,
pipeline: string,
stage: string,
dayOffset: number,
anchor: string,
anchorLabel: string,
shootKind: string | null,
isActive: boolean,
sortOrder: number,
};
export type ThreadEntryData = {
key: string,
at: string | null,
body: string,
author: string | null,
system: boolean,
note: string | null,
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
export type CaptureChannel = 'phone' | 'email' | 'in_person' | 'web_form' | 'portal' | 'other';
export type CaseContactRole = 'primary' | 'partner' | 'payer' | 'planner' | 'vendor';
export type DueAnchor = 'stage' | 'shoot';
export type FormFieldType = 'text' | 'short_text' | 'boolean' | 'select' | 'date' | 'time' | 'name_list' | 'group_photos' | 'file';
export type LeadOutcome = 'lost' | 'declined' | 'closed';
export type LeadSource = 'referral' | 'website' | 'instagram' | 'wedding_fair' | 'other';
export type LeadStage = 'new' | 'contacted' | 'quote_sent' | 'quote_accepted' | 'booked';
export type ProjectFieldType = 'text' | 'textarea' | 'number' | 'select' | 'tags' | 'date' | 'boolean';
export type ProjectType = 'wedding' | 'portrait' | 'other';
export type ShootStatus = 'requested' | 'booked' | 'prep' | 'shot' | 'culling' | 'editing' | 'ready' | 'delivered' | 'archived';
export type StageAnchor = 'entry' | 'quote_sent' | 'quote_accepted' | 'won' | 'booked' | 'shot' | 'ready' | 'delivered' | 'archived';
export type StageColor = 'grey' | 'red' | 'amber' | 'olive' | 'green' | 'teal' | 'blue' | 'violet';
export type StagePipeline = 'lead' | 'project';
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
lastQuoteAction: string | null,
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
};
export type MailTemplateData = {
id: string,
name: string,
body: string,
unknownFields: string[],
};
}
namespace Enums {
export type DocumentTemplateType = 'contract' | 'quote' | 'invoice' | 'credit_note' | 'mail' | 'legal';
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
recipients: string[],
};
}
namespace Enums {
export type InboundMessageState = 'threaded' | 'triage' | 'quarantined';
}
}
namespace Enums {
export type AbsenceCategory = 'unavailable' | 'holiday' | 'other';
export type Capability = 'manage_leads' | 'manage_contacts' | 'view_all_cases' | 'book_project' | 'manage_shoots' | 'manage_crew' | 'manage_tasks' | 'manage_notes' | 'manage_messages' | 'manage_staff' | 'manage_packages' | 'manage_portal_access' | 'manage_quotes' | 'manage_contracts' | 'manage_settings' | 'manage_form_templates' | 'manage_dsgvo';
export type StaffRole = 'admin' | 'manager' | 'contributor' | 'reader';
}
namespace Gallery {
namespace Data {
export type GalleryNavData = {
cover: string | null,
tabs: {
key: string,
label: string,
href: string,
count: number | null,
countTo: number | null,
children: {
key: string,
label: string,
translate: boolean,
href: string,
count: number | null,
reorderable: boolean,
}[],
}[],
};
export type GalleryPresetData = {
id: string,
name: string,
design: Record<string, string> | null,
expiryMode: string,
expiryDays: number | null,
downloadsEnabled: boolean | null,
listsEnabled: boolean | null,
commentsEnabled: boolean | null,
isDefault: boolean,
};
export type GalleryRowData = {
id: string,
title: string | null,
projectName: string | null,
assetCount: number,
version: number | null,
expiresAt: string | null,
};
}
}
namespace Integration {
namespace Data {
export type CaseContactReadData = {
id: string,
name: string,
email: string | null,
phone: string | null,
street: string | null,
postcode: string | null,
city: string | null,
role: string,
updatedAt: string,
};
export type CaseDetailReadData = {
id: string,
name: string,
type: string,
typeOther: string | null,
portalVisible: boolean,
fields: object,
updatedAt: string,
lead: App.Integration.Data.CaseLeadReadData | null,
bookedAt: string | null,
bookedVia: string | null,
openTaskCount: number,
contacts: App.Integration.Data.CaseContactReadData[],
shoots: App.Integration.Data.ShootReadData[],
quotes: App.Quotes.Data.QuoteData[],
};
export type CaseLeadReadData = {
stage: string,
source: string | null,
sourceOther: string | null,
inquiryText: string | null,
capturedVia: string | null,
capturedViaOther: string | null,
capturedAt: string,
stageEnteredAt: string | null,
outcome: string | null,
lostReason: string | null,
lostAt: string | null,
};
export type CaseMoneyReadData = {
paymentPlan: App.Payments.Data.PaymentPlanData | null,
};
export type ContactReadData = {
id: string,
name: string,
email: string | null,
phone: string | null,
street: string | null,
postcode: string | null,
city: string | null,
updatedAt: string,
};
export type CrewAssignmentReadData = {
id: string,
shootId: string,
userId: string,
userName: string,
};
export type GalleryDetailData = {
id: string,
title: string,
projectId: string | null,
currentVersion: number | null,
draftAssetCount: number,
status: string,
updatedAt: string,
slug: string,
url: string,
isExternal: boolean,
externalUrl: string | null,
expiresAt: string | null,
downloadsEnabled: boolean,
listsEnabled: boolean,
commentsEnabled: boolean,
favoriteCount: number,
downloadCount: number,
commentCount: number,
viewCount: number,
};
export type GalleryReadData = {
id: string,
title: string,
projectId: string | null,
currentVersion: number | null,
draftAssetCount: number,
status: string,
updatedAt: string,
};
export type LeadReadData = {
id: string,
projectId: string,
projectName: string,
source: string | null,
sourceOther: string | null,
stage: string,
stageEnteredAt: string | null,
isLost: boolean,
outcome: string | null,
lostReason: string | null,
capturedAt: string,
lostAt: string | null,
updatedAt: string,
};
export type ProjectFieldReadData = {
id: string,
key: string,
label: string,
type: string,
options: string[],
unit: string | null,
required: boolean,
allowNewOptions: boolean,
};
export type ProjectReadData = {
id: string,
name: string,
type: string,
portalVisible: boolean,
fields: object,
updatedAt: string,
};
export type ProjectTypeReadData = {
id: string,
key: string,
name: string,
isActive: boolean,
isDefault: boolean,
fields: App.Integration.Data.ProjectFieldReadData[],
};
export type ShootReadData = {
id: string,
projectId: string,
kind: string,
label: string | null,
date: string | null,
callTime: string | null,
endTime: string | null,
venueName: string | null,
venueCity: string | null,
status: string,
isPrimary: boolean,
cancelledAt: string | null,
cancelledReason: string | null,
crew: App.Crm.Data.CrewMemberData[],
updatedAt: string,
};
export type ShotListItemReadData = {
id: string,
label: string,
checkedOff: boolean,
};
export type StaffReadData = {
id: string,
name: string,
role: string,
};
export type StudioSettingsReadData = {
vatMode: string,
vatRate: number,
quoteValidityDays: number,
paymentTermDays: number,
depositPercentage: number,
};
export type TaskReadData = {
id: string,
projectId: string | null,
contactId: string | null,
title: string,
audience: string,
dueOn: string | null,
isDone: boolean,
completedOn: string | null,
shootId: string | null,
dependsOnTaskId: string | null,
isBlocked: boolean,
assigneeId: string | null,
updatedAt: string,
};
export type TimelineEntryReadData = {
id: string,
time: string,
title: string,
location: string | null,
note: string | null,
isTransit: boolean,
};
}
}
namespace Media {
namespace Data {
export type FailedAssetData = {
publicId: string,
filename: string,
error: string,
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
namespace Notifications {
namespace Enums {
export type NotificationMode = 'immediate' | 'briefing' | 'digest' | 'off';
}
}
namespace Numbering {
namespace Enums {
export type DocumentType = 'quote' | 'contract' | 'invoice' | 'credit_note';
export type NumberRangeResetRule = 'yearly' | 'continuous';
}
}
namespace Payments {
namespace Data {
export type CreditNoteRowData = {
id: string,
number: string,
issuedOn: string,
amount: number,
reason: string | null,
invoiceId: string,
invoiceNumber: string,
projectId: string | null,
projectName: string | null,
contact: string | null,
hasDocument: boolean,
};
export type InvoiceRowData = {
id: string,
number: string,
issuedOn: string,
projectId: string | null,
projectName: string | null,
contact: string | null,
amount: number,
paidAmount: number,
credited: number,
dueOn: string | null,
status: string,
statusLabel: string,
sentOn: string | null,
viewedOn: string | null,
hasDocument: boolean,
openInstallments: {
id: string,
remaining: number,
}[],
creditNotes: {
id: string,
number: string,
amount: number,
issuedOn: string,
hasDocument: boolean,
}[],
};
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
paidAmount: number,
remaining: number,
isLate: boolean,
invoiceId: string | null,
invoiceNumber: string | null,
invoicedOn: string | null,
hasInvoiceDocument: boolean,
creditNotes: {
id: string,
number: string,
amount: number,
issuedOn: string,
hasDocument: boolean,
}[],
};
export type PaymentPlanData = {
id: string,
total: number,
openTotal: number,
reference: string | null,
settledOn: string | null,
installments: App.Payments.Data.PaymentInstallmentData[],
};
}
namespace Enums {
export type CancellationKind = 'correction' | 'void';
export type OverdueReason = 'due_date_passed' | 'payment_failed';
export type PaymentInstallmentStatus = 'scheduled' | 'paid' | 'invoiced' | 'cancelled' | 'partial';
export type SettledVia = 'stripe' | 'transfer' | 'manual';
}
}
namespace Quotes {
namespace Data {
export type QuoteData = {
id: string,
predecessorId: string | null,
isAddendum: boolean,
predecessorNumber: string | null,
status: string,
statusLabel: string,
pricingMode: string,
currency: string,
validUntil: string | null,
number: string | null,
sentAt: string | null,
sentChannel: string | null,
total: number,
discountType: string | null,
discountValue: number | null,
discountAmount: number,
discountHidden: boolean,
net: number,
vatMode: string,
vatRate: number,
vatNote: string | null,
vatAmount: number,
vatLines: {
rate: number,
net: number,
vat: number,
}[],
intro: string | null,
outro: string | null,
items: App.Quotes.Data.QuoteItemData[],
hourlyRate: number | null,
minHours: number | null,
maxHours: number | null,
imagesPerHour: number | null,
hours: number | null,
timeTitle: string | null,
timeDescription: string | null,
timeSortOrder: number,
rangeMin: number | null,
rangeMax: number | null,
paymentStages: {
id: string,
percent: number,
label: string,
minimum: number,
}[],
recipientContact: string | null,
recipientStreet: string | null,
recipientPostcode: string | null,
recipientCity: string | null,
portalUrl: string | null,
showcaseCount: number,
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
vatRate: number | null,
selectable: boolean,
selected: boolean,
outcome: string | null,
hasImage: boolean,
};
export type QuoteRowData = {
id: string,
projectName: string,
contact: string | null,
number: string | null,
status: string,
statusLabel: string,
total: number,
sentAt: string | null,
createdAt: string | null,
};
}
namespace Enums {
export type DiscountType = 'percentage' | 'amount';
export type PricingMode = 'classic' | 'choice';
export type QuoteLayout = 'classic' | 'minimal' | 'letter' | 'header_band' | 'structured';
export type QuoteStatus = 'draft' | 'sent' | 'accepted' | 'superseded' | 'expired';
}
}
namespace Reporting {
namespace Enums {
export type ReportPeriod = '12m' | '6m' | 'jahr';
}
}
namespace Settings {
namespace Data {
export type GallerySettingsData = {
id: string,
expiryDays: number | null,
downloadsEnabled: boolean,
listsEnabled: boolean,
commentsEnabled: boolean,
design: Record<string, string>,
};
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
};
export type PortalSettingsData = {
id: string,
maintenanceEnabled: boolean,
maintenanceMessage: string | null,
datenschutzUrl: string | null,
agbUrl: string | null,
impressumUrl: string | null,
widerrufUrl: string | null,
agbAttach: boolean,
widerrufAttach: boolean,
legalFamilies: Record<string, string | null>,
acceptQuotesEnabled: boolean,
signContractsEnabled: boolean,
paymentStatusEnabled: boolean,
offersEnabled: boolean,
messagingMode: string,
clientRegister: string,
welcomeInquiry: string | null,
welcomeActive: string | null,
welcomeDone: string | null,
contactName: string | null,
contactAvatarUrl: string | null,
contactPhone: string | null,
contactEmail: string | null,
contactResponseTime: string | null,
};
export type RetentionCandidateData = {
id: string,
name: string,
type: string,
text: string | null,
outcome: string,
outcomeLabel: string,
idleMonths: number,
invoiced: boolean,
leads: number,
messages: number,
offers: number,
galleries: number,
};
export type RetentionPolicyData = {
name: boolean,
text: boolean,
messages: boolean,
offers: boolean,
galleries: boolean,
order: boolean,
};
export type StudioClosureData = {
id: string,
label: string,
startsOn: string,
endsOn: string,
message: string | null,
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
documentLayout: string,
remindersEnabled: boolean,
clientRegister: string,
documentLetterheadHtml: string | null,
documentFooterHtml: string | null,
timezone: string,
workingDays: number[],
mailFromName: string,
mailFromAddress: string,
mailReplyTo: string | null,
mailSignature: string | null,
mailBcc: string | null,
mailClientsEnabled: boolean,
};
}
namespace Enums {
export type FontPair = 'hanken' | 'fraunces' | 'cormorant' | 'grotesk';
export type StudioExportKind = 'daten' | 'galerie';
}
}
}
