<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Database\Factories\Crm\ContactFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A person, role-agnostic. One row per address: a household sharing an inbox
 * is one contact, a partner with their own address is a second contact, and a
 * contact with no address has no portal access at all.
 *
 * Contacts outlive projects. Portal access in P2 hangs off this model rather
 * than off Project, so a repeat client — a portrait sitting one year, a
 * wedding the next — signs in once and switches between them.
 */
class Contact extends Model implements Authenticatable
{
    use AuthenticatableTrait;
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['name', 'email', 'phone'];

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
