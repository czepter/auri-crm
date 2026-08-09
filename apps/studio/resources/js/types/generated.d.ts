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
export type DateConflictData = {
projectId: string,
projectName: string,
venue: string | null,
kind: string,
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
}
namespace Enums {
export type CaptureChannel = 'phone' | 'email' | 'in_person' | 'other';
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
