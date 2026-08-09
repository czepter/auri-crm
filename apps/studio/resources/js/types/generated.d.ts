declare namespace App {
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
projectId: string,
projectName: string,
venue: string | null,
kind: string,
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
export type ProjectHeaderData = {
id: string,
name: string,
names: string,
type: string,
position: App.Crm.Data.ProjectPositionData,
primaryDate: string | null,
shootCount: number,
isBooked: boolean,
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
};
}
namespace Enums {
export type BookedVia = 'manual' | 'contract';
export type CaptureChannel = 'phone' | 'email' | 'in_person' | 'other';
export type CrewRole = 'lead_photographer' | 'second_shooter' | 'assistant';
export type LeadSource = 'referral' | 'website' | 'instagram' | 'wedding_fair' | 'other';
export type LeadStage = 'new' | 'contacted' | 'quote_sent' | 'quote_accepted' | 'contract_sent' | 'booked';
export type ProjectContactRole = 'primary' | 'partner' | 'payer' | 'planner' | 'vendor';
export type ProjectType = 'wedding' | 'portrait' | 'other';
export type ShootKind = 'engagement' | 'wedding' | 'reception' | 'portrait' | 'other';
export type ShootStatus = 'requested' | 'booked' | 'prep' | 'shot' | 'culling' | 'editing' | 'ready' | 'delivered' | 'archived';
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
namespace Enums {
export type StaffRole = 'owner' | 'photographer' | 'second_shooter' | 'editor' | 'assistant';
}
}
