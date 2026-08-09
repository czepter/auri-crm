<?php

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What someone is employed as, and — through the map below — what that lets
 * them do.
 *
 * Not CrewRole. That one says what a person does on a shooting day; an editor
 * never attends one. Keeping them apart is what lets "a second shooter sees
 * the shoots they are assigned to" be a policy over CrewAssignment rather than
 * a coincidence of two enums sharing a word.
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
     * Booking is Owner alone. The enum lists Owner and Photographer
     * separately, so a Photographer here is employed to shoot rather than to
     * commit the studio to a contract — and committing it is what booking
     * does. Moving that line is a business decision, not a technical one.
     *
     * SecondShooter and Assistant hold no project-wide capability at all:
     * everything they reach, they reach because they are crewed on the shoot.
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
            ],

            // Post-production: sees the work, moves it through culling,
            // editing and delivery, and touches neither the funnel nor the
            // crew rota.
            self::Editor => [
                Capability::ViewAllProjects,
                Capability::ManageShoots,
                Capability::ManageTasks,
                Capability::ManageNotes,
            ],

            self::SecondShooter, self::Assistant => [
                Capability::ManageTasks,
                Capability::ManageNotes,
            ],
        };
    }

    public function allows(Capability $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }
}
