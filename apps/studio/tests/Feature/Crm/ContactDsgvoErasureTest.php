<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\InternalNote;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Task;
use App\Engagement\Models\Message;
use App\Engagement\Models\Thread;
use App\Enums\StaffRole;
use App\Gallery\Models\AssetComment;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryPrereleaseSignup;
use App\Gallery\Models\GalleryVersion;
use App\Media\Models\Asset;
use App\Models\AuditLog;
use App\Models\User;
use App\Payments\Models\Invoice;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Portal\Models\ClientAccess;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->withMfa()->create(['role' => StaffRole::Admin]);
    Storage::fake('s3');
    Storage::fake('local');
});

/** A sole-contact project with a gallery asset, a quote, a thread and a note — the ordinary case. */
function dsgvoErasableProject(Contact $contact): Project
{
    $project = Project::factory()->create(['name' => 'Meier — Hochzeit']);
    $project->attachContact($contact, ProjectContactRole::Primary);

    $gallery = Gallery::factory()->for($project)->create();
    $asset = Asset::factory()->for($gallery)->create(['key' => 'galleries/x/original.jpg']);
    Storage::disk('s3')->put($asset->key, 'bytes');

    Quote::factory()->for($project)->create();

    $thread = Thread::factory()->for($project)->create();
    Message::factory()->for($thread)->create(['contact_id' => $contact->id, 'author_id' => null]);

    Task::factory()->for($project)->create();
    InternalNote::factory()->for($project)->create(['author_id' => null]);
    Lead::factory()->for($project)->create();

    return $project;
}

it('erases a sole-contact project outright, including its storage objects', function () {
    $contact = Contact::factory()->create(['name' => 'Lena Meier', 'email' => 'lena@example.test']);
    $project = dsgvoErasableProject($contact);
    $asset = Asset::query()->sole();

    $this->actingAs($this->owner, 'staff')
        ->post("/kontakte/{$contact->public_id}/dsgvo/loeschen")
        ->assertRedirect('/kontakte');

    expect(Project::query()->whereKey($project->id)->exists())->toBeFalse()
        ->and(Gallery::query()->exists())->toBeFalse()
        ->and(Quote::query()->exists())->toBeFalse()
        ->and(Thread::query()->exists())->toBeFalse()
        ->and(Message::query()->exists())->toBeFalse()
        ->and(Task::query()->exists())->toBeFalse()
        ->and(InternalNote::query()->exists())->toBeFalse()
        ->and(Lead::query()->exists())->toBeFalse();

    Storage::disk('s3')->assertMissing($asset->key);

    $contact->refresh();
    expect($contact->name)->not->toBe('Lena Meier')
        ->and($contact->email)->toBeNull()
        ->and($contact->phone)->toBeNull()
        ->and($contact->dsgvo_erased_at)->not->toBeNull()
        // The row survives — never dropped outright, see EraseContactDsgvo's
        // own doc comment on why.
        ->and(Contact::query()->whereKey($contact->id)->exists())->toBeTrue();
});

it('keeps the payment plan, its issued invoices and their PDFs when erasing a booked project', function () {
    $contact = Contact::factory()->create(['name' => 'Lena Meier']);
    $project = dsgvoErasableProject($contact);
    $plan = PaymentPlan::factory()->for(markBooked($project))->create(['total' => 100000]);
    $invoice = Invoice::create([
        'number' => 'RE-2026-0001',
        'issued_at' => now(),
        'contact_id' => $contact->id,
        'project_id' => $project->id,
        'pdf_path' => 'invoices/re-2026-0001.pdf',
    ]);
    $installment = PaymentInstallment::factory()->for($plan, 'plan')->create(['amount' => 100000, 'invoice_id' => $invoice->id]);
    Storage::disk('local')->put($invoice->pdf_path, 'pdf');

    $this->actingAs($this->owner, 'staff')->post("/kontakte/{$contact->public_id}/dsgvo/loeschen");

    expect($installment->fresh()?->invoice?->number)->toBe('RE-2026-0001')
        ->and($project->fresh()->name)->toBe('[gelöscht]')
        ->and(Quote::query()->exists())->toBeFalse();

    Storage::disk('local')->assertExists($invoice->pdf_path);
});

it('exports and erases the address, comments and favorites of a contact on a shared project', function () {
    $contact = Contact::factory()->create(['street' => 'Lindenweg 4', 'postcode' => '07743', 'city' => 'Jena']);
    $project = Project::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);
    $project->attachContact(Contact::factory()->create(), ProjectContactRole::Partner);
    $version = GalleryVersion::factory()->for(Gallery::factory()->for($project))->create();
    AssetComment::factory()->for($version)->create(['contact_id' => $contact->id, 'body' => 'Bitte in Farbe']);
    AssetFavorite::factory()->for($version)->create(['contact_id' => $contact->id]);

    $data = json_decode(
        $this->actingAs($this->owner, 'staff')->get("/kontakte/{$contact->public_id}/dsgvo/export")->getContent(),
        true
    );

    expect($data['contact']['city'])->toBe('Jena')
        ->and($data['projects'][0]['type'])->toBe($project->type->key)
        ->and($data['asset_comments'][0]['body'])->toBe('Bitte in Farbe')
        ->and($data['asset_favorites'])->toHaveCount(1);

    $this->actingAs($this->owner, 'staff')->post("/kontakte/{$contact->public_id}/dsgvo/loeschen");

    $contact->refresh();
    expect($contact->street)->toBeNull()
        ->and($contact->postcode)->toBeNull()
        ->and($contact->city)->toBeNull()
        ->and(AssetComment::query()->exists())->toBeFalse()
        ->and(AssetFavorite::query()->exists())->toBeFalse();
});

it('records one audit event naming only the opaque public_id, never PII', function () {
    $contact = Contact::factory()->create(['name' => 'Lena Meier']);
    dsgvoErasableProject($contact);

    $this->actingAs($this->owner, 'staff')->post("/kontakte/{$contact->public_id}/dsgvo/loeschen");

    $row = AuditLog::where('event', 'contact.dsgvo_erased')->sole();

    expect($row->subject)->toBe($contact->public_id)
        ->and($row->actor_id)->toBe($this->owner->id)
        ->and(json_encode($row->context))->not->toContain('Lena Meier');
});

it("leaves a project shared with another contact alone, beyond this contact's own fingerprints", function () {
    $subject = Contact::factory()->create();
    $partner = Contact::factory()->create(['name' => 'Tom Kowalski']);
    $project = Project::factory()->create();
    $project->attachContact($subject, ProjectContactRole::Primary);
    $project->attachContact($partner, ProjectContactRole::Partner);

    $gallery = Gallery::factory()->for($project)->create();
    $thread = Thread::factory()->for($project)->create();
    $ownMessage = Message::factory()->for($thread)->create(['contact_id' => $subject->id, 'author_id' => null]);
    $partnerMessage = Message::factory()->for($thread)->create(['contact_id' => $partner->id, 'author_id' => null]);

    $this->actingAs($this->owner, 'staff')->post("/kontakte/{$subject->public_id}/dsgvo/loeschen");

    expect(Project::query()->whereKey($project->id)->exists())->toBeTrue()
        ->and(Gallery::query()->whereKey($gallery->id)->exists())->toBeTrue()
        ->and(Message::query()->whereKey($ownMessage->id)->exists())->toBeFalse()
        ->and(Message::query()->whereKey($partnerMessage->id)->exists())->toBeTrue()
        ->and($project->fresh()->contacts()->whereKey($subject->id)->exists())->toBeFalse()
        ->and($project->fresh()->contacts()->whereKey($partner->id)->exists())->toBeTrue()
        ->and($partner->fresh()->name)->toBe('Tom Kowalski');
});

it('deletes client accesses and api tokens, and cannot be run twice', function () {
    $contact = Contact::factory()->create();
    ClientAccess::create([
        'contact_id' => $contact->id,
        'token_hash' => ClientAccess::hash('raw-token'),
        'expires_at' => now()->addMinutes(15),
    ]);
    $contact->createToken('guest');

    $this->actingAs($this->owner, 'staff')->post("/kontakte/{$contact->public_id}/dsgvo/loeschen");

    expect(ClientAccess::where('contact_id', $contact->id)->exists())->toBeFalse()
        ->and($contact->fresh()->tokens()->exists())->toBeFalse();

    $this->actingAs($this->owner, 'staff')
        ->post("/kontakte/{$contact->public_id}/dsgvo/loeschen")
        ->assertStatus(409);
});

// Personal-data fix round 1: GalleryPrereleaseSignup carries no contact_id
// (same reason ClientAccess above does not need to be the model here),
// matched by address instead — including on a gallery under a *shared*
// project, the gap detaching from it alone would have left open.
it('deletes a pre-release signup by address, even on a gallery shared with another contact', function () {
    $contact = Contact::factory()->create(['email' => 'lena@example.test']);
    $partner = Contact::factory()->create();
    $project = Project::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);
    $project->attachContact($partner, ProjectContactRole::Partner);

    $gallery = Gallery::factory()->for($project)->create();
    GalleryPrereleaseSignup::create(['gallery_id' => $gallery->id, 'email' => 'Lena@Example.test']);

    $this->actingAs($this->owner, 'staff')->post("/kontakte/{$contact->public_id}/dsgvo/loeschen");

    expect(GalleryPrereleaseSignup::count())->toBe(0)
        // The shared gallery itself is untouched — only the signup goes.
        ->and(Gallery::query()->whereKey($gallery->id)->exists())->toBeTrue();
});

it("shows a contact's own pre-release signups in the DSGVO export, matched by address", function () {
    $contact = Contact::factory()->create(['email' => 'lena@example.test']);
    $gallery = Gallery::factory()->create(['title' => 'Vorab-Galerie']);
    $gallery->forceFill(['prerelease_at' => now()])->save();
    GalleryPrereleaseSignup::create(['gallery_id' => $gallery->id, 'email' => 'Lena@Example.test']);

    $response = $this->actingAs($this->owner, 'staff')->get("/kontakte/{$contact->public_id}/dsgvo/export");

    $body = json_decode($response->getContent(), true);
    expect($body['gallery_prerelease_signups'])->toHaveCount(1)
        ->and($body['gallery_prerelease_signups'][0]['gallery'])->toBe($gallery->public_id);
});

it('refuses every role but the owner', function (StaffRole $role) {
    $contact = Contact::factory()->create();

    $this->actingAs(User::factory()->withMfa()->create(['role' => $role]), 'staff')
        ->post("/kontakte/{$contact->public_id}/dsgvo/loeschen")
        ->assertForbidden();

    expect($contact->fresh()->dsgvo_erased_at)->toBeNull();
})->with([StaffRole::Manager, StaffRole::Contributor, StaffRole::Contributor, StaffRole::Contributor]);

it('rejects unauthenticated access', function () {
    $contact = Contact::factory()->create();

    $this->post("/kontakte/{$contact->public_id}/dsgvo/loeschen")->assertRedirect('/login');
});
