<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Database\Factories\Crm\ContactFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A person, role-agnostic. One row per address: a household sharing an inbox
 * is one contact, a partner with their own address is a second contact, and a
 * contact with no address has no portal access at all.
 *
 * Contacts outlive projects. Portal access in P2 hangs off this model rather
 * than off Project, so a repeat client — a portrait sitting one year, a
 * wedding the next — signs in once and switches between them.
 */
class Contact extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['name', 'email', 'phone'];

    /**
     * Declared explicitly: Laravel resolves Database\Factories\ContactFactory
     * from App\Models\Contact, and finds nothing for App\Crm\Models\Contact.
     */
    protected static function newFactory(): Factory
    {
        return ContactFactory::new();
    }
}
