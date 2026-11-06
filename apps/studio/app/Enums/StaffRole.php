<?php

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What someone is employed as, and — through the map below — what that lets
 * them do.
 *
 * Not the same thing as being on a shoot's crew: a contributor never attends
 * one, and being crewed is a row in CrewAssignment, not a role in this enum.
 */
#[TypeScript]
enum StaffRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Contributor = 'contributor';
    case Reader = 'reader';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Manager => 'Manager',
            self::Contributor => 'Mitwirkender',
            self::Reader => 'Leser',
        };
    }

    /**
     * The whole authority model, in one readable place.
     *
     * Two different kinds of rule live here, and the difference is the point.
     *
     * Reversible work every studio hand does — moving a lead down the funnel
     * above all — is open to Admin, Manager and Contributor alike, because
     * the control on it is the audit trail rather than a gate. Locking the
     * funnel would mean a contributor who just got off the phone has to find
     * someone else to type it in.
     *
     * Booking is different, and Admin alone. It is not reversible work: it
     * turns a pursuit into an obligation, and knowing afterwards who
     * committed the studio is no substitute for their having been allowed
     * to. Moving that line is a business decision, not a technical one.
     *
     * ViewAllCases is what turns a policy's `view` check (which otherwise
     * falls back to "crewed on this shoot") into "sees every project". Admin
     * and Manager hold it; Contributor does not, so a contributor reaches
     * only the projects they are crewed on — the same project-list scoping
     * a second shooter always had — even though ManageShoots, ManageTasks
     * and the rest let them act once they are there. Reader holds
     * ViewAllCases and nothing else: sees everything, changes nothing.
     *
     * @return list<Capability>
     */
    public function capabilities(): array
    {
        return match ($this) {
            self::Admin => Capability::cases(),

            self::Manager => [
                Capability::ManageLeads,
                Capability::ManageContacts,
                Capability::ViewAllCases,
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

            // Adds and changes ordinary studio work on the projects they can
            // reach, but never the capabilities that let someone cut a
            // client's access off, reassign crew, or touch a contract.
            self::Contributor => [
                Capability::ManageLeads,
                Capability::ManageShoots,
                Capability::ManageTasks,
                Capability::ManageNotes,
                Capability::ManageMessages,
                Capability::ManagePackages,
                Capability::ManageQuotes,
            ],

            self::Reader => [
                Capability::ViewAllCases,
            ],
        };
    }

    public function allows(Capability $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }
}
