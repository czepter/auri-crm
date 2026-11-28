<?php

use App\Automation\Notifications\AutomationReminder;
use App\Contracts\Notifications\ContractSent;
use App\Contracts\Notifications\Studio\ContractSigned;
use App\Crm\Notifications\Studio\InquiryReceived;
use App\Crm\Notifications\Studio\LeadStalled;
use App\Crm\Notifications\Studio\TasksDue;
use App\Engagement\Notifications\StaffReplyPosted;
use App\Engagement\Notifications\Studio\ClientMessagePosted;
use App\Gallery\Notifications\AssetCommented;
use App\Gallery\Notifications\AssetCommentReplied;
use App\Gallery\Notifications\AssetFavorited;
use App\Gallery\Notifications\GalleryExpiring;
use App\Gallery\Notifications\GalleryPrereleasePublished;
use App\Gallery\Notifications\GalleryPublished;
use App\Notifications\Contracts\IgnoresClientMailSwitch;
use App\Payments\Notifications\CreditNoteSent;
use App\Payments\Notifications\InvoiceSent;
use App\Payments\Notifications\Studio\InstallmentDueSoon;
use App\Payments\Notifications\Studio\InstallmentOverdue;
use App\Payments\Notifications\Studio\InstallmentPaid;
use App\Portal\Notifications\PortalAccessInvitation;
use App\Portal\Notifications\Studio\QuestionnaireSubmitted;
use App\Portal\Notifications\TaskDue;
use App\Privacy\Notifications\Studio\DsgvoRequested;
use App\Privacy\Notifications\Studio\StudioDeletionCode;
use App\Privacy\Notifications\Studio\StudioDeletionReminder;
use App\Privacy\Notifications\StudioExportReady;
use App\Quotes\Notifications\QuoteFollowUp;
use App\Quotes\Notifications\QuoteSent;
use App\Quotes\Notifications\Studio\QuoteAccepted;
use App\Staff\Notifications\MorningBriefing;
use App\Staff\Notifications\StaffOwnershipTransferred;
use App\Staff\Notifications\StudioDigest;
use App\Staff\Notifications\StudioEmailVerification;
use App\Staff\Notifications\StudioReady;
use App\Staff\Notifications\StudioSetupPending;
use App\Staff\Notifications\StudioWelcome;
use Illuminate\Notifications\Notification;

// Held back while the studio has switched client mail off. A new
// notification fails this test until it is listed here or implements
// IgnoresClientMailSwitch: GalleryZipReady was silently withheld for lack
// of exactly that decision.
$mayWait = [
    AutomationReminder::class,
    ContractSent::class,
    ContractSigned::class,
    InquiryReceived::class,
    LeadStalled::class,
    TasksDue::class,
    StaffReplyPosted::class,
    ClientMessagePosted::class,
    AssetCommentReplied::class,
    AssetCommented::class,
    AssetFavorited::class,
    GalleryExpiring::class,
    GalleryPrereleasePublished::class,
    GalleryPublished::class,
    CreditNoteSent::class,
    InvoiceSent::class,
    InstallmentDueSoon::class,
    InstallmentOverdue::class,
    InstallmentPaid::class,
    PortalAccessInvitation::class,
    QuestionnaireSubmitted::class,
    TaskDue::class,
    StudioExportReady::class,
    DsgvoRequested::class,
    StudioDeletionCode::class,
    StudioDeletionReminder::class,
    QuoteFollowUp::class,
    QuoteSent::class,
    QuoteAccepted::class,
    MorningBriefing::class,
    StaffOwnershipTransferred::class,
    StudioDigest::class,
    StudioEmailVerification::class,
    StudioReady::class,
    StudioSetupPending::class,
    StudioWelcome::class,
];

it('makes every notification decide whether it waits for client mail', function () use ($mayWait) {
    $root = dirname(__DIR__, 2).'/app/';
    $found = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if ($file->getExtension() !== 'php' || ! str_contains($file->getPathname(), '/Notifications/')) {
            continue;
        }

        $class = 'App\\'.str_replace('/', '\\', substr($file->getPathname(), strlen($root), -4));

        if (class_exists($class)
            && is_subclass_of($class, Notification::class)
            && ! (new ReflectionClass($class))->isAbstract()
            && ! is_subclass_of($class, IgnoresClientMailSwitch::class)) {
            $found[] = $class;
        }
    }

    sort($found);
    $listed = $mayWait;
    sort($listed);

    expect($found)->toBe($listed);
});
