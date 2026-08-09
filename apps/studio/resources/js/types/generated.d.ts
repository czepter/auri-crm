declare namespace App {
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
};
export type FormFieldData = {
id: string,
label: string,
type: string,
typeLabel: string,
required: boolean,
options: string[] | null,
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
names: string,
date: string | null,
shootCount: number,
venue: string | null,
source: string,
stage: string,
daysInStage: number,
conflicts: App.Crm.Data.DateConflictData[],
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
export type ProductionRowData = {
projectId: string,
projectName: string,
shootId: string,
shootLabel: string | null,
kind: string,
date: string | null,
callTime: string | null,
venue: string | null,
status: string,
statusLabel: string,
daysInStatus: number,
crewCount: number,
openTasks: number,
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
portalVisible: boolean,
canManagePortalVisibility: boolean,
canManageQuotes: boolean,
canManageContracts: boolean,
canManagePaymentPlan: boolean,
tabs: {
key: string,
label: string,
href: string,
}[],
};
export type ProjectPositionData = {
source: string,
value: string | null,
label: string,
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
export type CaptureChannel = 'phone' | 'email' | 'in_person' | 'other';
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
version: number,
type: string,
typeLabel: string,
shootKind: string | null,
shootKindLabel: string | null,
};
}
namespace Enums {
export type DocumentTemplateType = 'contract' | 'quote' | 'mail';
}
}
namespace Enums {
export type Capability = 'manage_leads' | 'manage_contacts' | 'view_all_projects' | 'book_project' | 'manage_shoots' | 'manage_crew' | 'manage_tasks' | 'manage_notes' | 'manage_staff' | 'manage_packages' | 'manage_portal_access' | 'manage_quotes' | 'manage_contracts' | 'manage_settings' | 'manage_form_templates';
export type StaffRole = 'owner' | 'photographer' | 'second_shooter' | 'editor' | 'assistant';
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
messagingEnabled: boolean,
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
};
}
}
}
