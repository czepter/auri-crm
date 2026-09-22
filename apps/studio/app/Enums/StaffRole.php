<?php

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What someone is employed as, and — through the map below — what that lets
 * them do.
 *
 * Not the same thing as being on a shoot's crew: an editor never attends one,
 * and being crewed is a row in CrewAssignment, not a role in this enum.
 */
#[TypeScript]
enum StaffRole: string
{
    case Owner = 'owner';
    case Photographer = 'photographer';
    case SecondShooter = 'second_shooter';
    case Editor = 'editor';
    case Assistant = 'assistant';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Inhaber',
            self::Photographer => 'Fotograf',
            self::SecondShooter => 'Zweitfotograf',
            self::Editor => 'Bildbearbeitung',
            self::Assistant => 'Assistenz',
        };
    }

    /**
     * The whole authority model, in one readable place.
     *
     * Two different kinds of rule live here, and the difference is the point.
     *
     * Reversible work every studio hand does — moving a lead down the funnel
     * above all — is open to all staff, because the control on it is the audit
     * trail rather than a gate. AdvanceLeadStage records who moved what and
     * when, which answers the question a permission would only have prevented
     * being asked. Locking the funnel would mean a second shooter who just got
     * off the phone has to find someone else to type it in.
     *
     * Booking is different, and Owner alone. It is not reversible work: it
     * turns a pursuit into an obligation, and knowing afterwards who committed
     * the studio is no substitute for their having been allowed to. The enum
     * lists Owner and Photographer separately, so a Photographer is employed
     * to shoot rather than to sign. Moving that line is a business decision,
     * not a technical one.
     *
     * SecondShooter and Assistant hold no project-wide capability: the
     * projects they reach, they reach because they are crewed on the shoot.
     * That is the review question this sprint is measured on.
     *
     * @return list<Capability>
     */
    public function capabilities(): array
    {
        return match ($this) {
            self::Owner => Capability::cases(),

            self::Photographer => [
                Capability::ManageLeads,
                Capability::ManageContacts,
                Capability::ViewAllProjects,
                Capability::ManageShoots,
                Capability::ManageCrew,
                Capability::ManageTasks,
                Capability::ManageNotes,
                Capability::ManageMessages,
                Capability::ManagePackages,
                Capability::ManagePortalAccess,
                Capability::ManageQuotes,
                Capability::ManageContracts,
                Capability::ManageFormTemplates,
            ],

            // Post-production: sees the work and moves it through culling,
            // editing and delivery. No crew rota.
            self::Editor => [
                Capability::ManageLeads,
                Capability::ViewAllProjects,
                Capability::ManageShoots,
                Capability::ManageTasks,
                Capability::ManageNotes,
                Capability::ManageMessages,
                Capability::ManagePackages,
                Capability::ManageQuotes,
                Capability::ManageContracts,
                Capability::ManageFormTemplates,
            ],

            self::SecondShooter, self::Assistant => [
                Capability::ManageLeads,
                Capability::ManageTasks,
                Capability::ManageNotes,
                Capability::ManageMessages,
                Capability::ManagePackages,
                Capability::ManageQuotes,
            ],
        };
    }

    public function allows(Capability $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }
}
