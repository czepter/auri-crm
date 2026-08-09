<?php

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a staff role is allowed to do, named once.
 *
 * Deliberately a vocabulary rather than a set of booleans scattered through
 * policies: P6's I-01 expresses API token scopes as exactly these values, so a
 * token can never do something no staff role can. Adding a capability here is
 * the only way to grow either surface.
 *
 * These are capabilities, not routes. Whether a given row is *yours* — the
 * shoots a second shooter is assigned to — is a question the policies answer
 * on top of these, because it depends on the record and not on the role.
 */
#[TypeScript]
enum Capability: string
{
    /** The sales funnel: the board, the capture form, stage transitions. */
    case ManageLeads = 'manage_leads';

    case ManageContacts = 'manage_contacts';

    /** See every project, rather than only the ones you are crewed on. */
    case ViewAllProjects = 'view_all_projects';

    /**
     * Commit the studio: turn a pursuit into a Job. The narrowest capability
     * in the list, and the reason S-06 exists — P-05a shipped this action open
     * to every role in sprint 3.
     */
    case BookProject = 'book_project';

    /** Add, advance, cancel and re-designate shoots. */
    case ManageShoots = 'manage_shoots';

    case ManageCrew = 'manage_crew';

    case ManageTasks = 'manage_tasks';

    case ManageNotes = 'manage_notes';

    /** Invite staff and reach the studio's own settings. */
    case ManageStaff = 'manage_staff';

    /**
     * Define, edit and retire the service catalog a quote starts from (Q-01).
     * Ordinary studio work, open to every role — the audit log is the control,
     * the same shape as ManageLeads.
     */
    case ManagePackages = 'manage_packages';

    /**
     * Hide a project from the portal, or cut a contact's portal access off
     * entirely. Two distinct, deliberately separate actions (A-04) sharing
     * one capability: whoever may manage a contact's access to the studio
     * also decides whether one of their projects is ready to show.
     */
    case ManagePortalAccess = 'manage_portal_access';

    /**
     * Create and edit a project's quotes (Q-02). Reversible while a quote is
     * still a draft — the only state reachable before Q-06/Q-07 — so this
     * gets the same ordinary-work treatment as ManagePackages rather than a
     * narrower gate.
     */
    case ManageQuotes = 'manage_quotes';

    /**
     * Author contract templates and generate a rendered contract for a
     * project (M-01). Narrower than ManagePackages: a contract is what a
     * client signs, so this stays with the roles ManageShoots already
     * trusts rather than opening to every crew role the way the catalog does.
     */
    case ManageContracts = 'manage_contracts';

    /**
     * Read and write the tenant's settings row (T-01) — legal details,
     * branding, VAT mode and whatever else T-02 and T-03 add to it. One
     * capability rather than several, the same narrowness as ManageStaff:
     * the crew today is the owner plus second shooters, and a second
     * shooter reaches no settings at all. Revisit at the first non-owner
     * role that needs partial access.
     */
    case ManageSettings = 'manage_settings';
}
