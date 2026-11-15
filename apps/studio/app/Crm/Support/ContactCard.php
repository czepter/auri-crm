<?php

namespace App\Crm\Support;

use App\Crm\Models\Contact;
use App\Crm\Support\Concerns\FormatsIcs;

/** RFC 2426 vCard 3.0 for one contact, the shape the iPhone address book reads. */
class ContactCard
{
    use FormatsIcs;

    public function render(Contact $contact): string
    {
        [$given, $family] = $this->splitName($contact->name);

        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'PRODID:-//Studio//Studio//DE',
            'UID:'.$this->uid($contact->public_id),
            'FN:'.$this->escape($contact->name),
            'N:'.$this->escape($family).';'.$this->escape($given).';;;',
        ];

        if (filled($contact->email)) {
            $lines[] = 'EMAIL;TYPE=INTERNET:'.$this->escape($contact->email);
        }

        if (filled($contact->phone)) {
            $lines[] = 'TEL;TYPE=VOICE:'.$this->escape($contact->phone);
        }

        if (filled($contact->street) || filled($contact->postcode) || filled($contact->city)) {
            $lines[] = 'ADR;TYPE=HOME:;;'.$this->escape((string) $contact->street)
                .';'.$this->escape((string) $contact->city)
                .';;'.$this->escape((string) $contact->postcode).';';
        }

        $lines[] = 'REV:'.$contact->updated_at->utc()->format('Ymd\THis\Z');
        $lines[] = 'END:VCARD';

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    public function etag(Contact $contact): string
    {
        return md5($this->render($contact));
    }

    /**
     * iOS builds the display and sort name from N, so the last word is the
     * family name and everything before it the given name.
     *
     * @return array{0: string, 1: string} given, family
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+(?=\S+$)/u', trim($name), 2);

        return count($parts) === 2 ? [$parts[0], $parts[1]] : ['', $parts[0]];
    }
}
