<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Database\Factories\Crm\ContactFactory;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A person, role-agnostic. One row per address: a household sharing an inbox
 * is one contact, a partner with their own address is a second contact, and a
 * contact with no address has no portal access at all.
 *
 * Contacts outlive projects. Portal access in P2 hangs off this model rather
 * than off Project, so a repeat client — a portrait sitting one year, a
 * wedding the next — signs in once and switches between them.
 *
 * T-11: Notifiable — routeNotificationForMail()'s default reads $this->email,
 * exactly the column this model already carries, so a client-facing send can
 * name a Contact as its notifiable (App\Quotes\Notifications\QuoteSent) and
 * still reach the same inbox an on-demand route would have. Adopted here
 * rather than left on-demand everywhere, because client preferences
 * (App\Notifications\Support\NotificationPreferences) key on an identity an
 * on-demand address does not carry.
 *
 * G-01 gives it a second, independent way to prove that same identity:
 * HasApiTokens, for the api surface's guest tokens (Sanctum bearer tokens,
 * scoped to whichever contact created them) rather than the portal's session
 * cookie. Neither grants the other — a magic link never mints a token, and a
 * token never opens a portal session — they just happen to authenticate the
 * same row.
 */
class Contact extends Model implements Authenticatable
{
    use AuthenticatableTrait;
    use BelongsToTenant;
    use HasApiTokens;
    use HasFactory;
    use HasPublicId;
    use Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'street', 'postcode', 'city'];

    protected function casts(): array
    {
        return [
            'portal_access_revoked_at' => 'immutable_datetime',
            'dsgvo_erased_at' => 'immutable_datetime',
        ];
    }

    /** X-04: has App\Crm\Actions\EraseContactDsgvo already scrubbed this row? */
    public function isDsgvoErased(): bool
    {
        return $this->dsgvo_erased_at !== null;
    }

    /**
     * Ends this contact's portal access outright — every live magic link and
     * any session already open elsewhere is denied on the very next request.
     * Leaves project_contacts untouched: revocation is not the same as never
     * having been involved (A-04).
     */
    public function revokePortalAccess(): void
    {
        $this->forceFill(['portal_access_revoked_at' => now()])->save();
    }

    /**
     * Authenticatable so the portal guard has something to hold, and nothing
     * more. There is no password column here and there never will be: a client
     * proves an inbox through a magic link, which is the whole of A-02.
     * getAuthPassword() therefore answers with a value no hash can match.
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    /** Only projects the studio has chosen to show (vision §3.1). */
    public function visibleProjects(): BelongsToMany
    {
        return $this->projects()->where('portal_visible', true);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_contacts')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Declared explicitly: Laravel resolves Database\Factories\ContactFactory
     * from App\Models\Contact, and finds nothing for App\Crm\Models\Contact.
     */
    protected static function newFactory(): Factory
    {
        return ContactFactory::new();
    }
}
