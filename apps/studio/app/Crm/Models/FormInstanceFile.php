<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One file answering a "Datei" field. Deliberately not a FormAnswer version:
 * form_answers is append-only text and stays that way, while a client who
 * removes a document they uploaded by mistake must have it actually gone.
 *
 * Deliberately not a Media\Asset either — that family is the gallery's
 * S3 quarantine-ingest pipeline (multipart, EXIF, derivatives, blurhash,
 * checksum dedup) and none of it applies to a PDF somebody attaches to a
 * question. Answer files ride the private local disk next to invoices, which is already where this app keeps client documents.
 */
class FormInstanceFile extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $fillable = ['path', 'name', 'mime', 'size', 'contact_id', 'author_id'];

    public function formInstanceField(): BelongsTo
    {
        return $this->belongsTo(FormInstanceField::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** Bytes and row go together; a row pointing at nothing is worse than neither. */
    public function purge(): void
    {
        Storage::disk('local')->delete($this->path);
        $this->delete();
    }
}
