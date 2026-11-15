<?php

namespace App\Crm\Support;

use App\Crm\Enums\SocialPlatform;

/** A client list as another tool exports it: semicolon or comma separated, UTF-8 or Windows-1252. */
class ContactCsv
{
    private const HEADERS = [
        'name' => ['name', 'fullname', 'vollständigername', 'kunde', 'kontakt', 'anzeigename'],
        'first_name' => ['vorname', 'firstname', 'givenname'],
        'last_name' => ['nachname', 'lastname', 'surname', 'familienname'],
        'email' => ['email', 'emailadresse', 'mail', 'emailaddress'],
        'phone' => ['telefon', 'telefonnummer', 'tel', 'phone', 'phonenumber', 'mobil', 'mobile', 'handy'],
        'street' => ['straße', 'strasse', 'str', 'street', 'streetaddress', 'adresse', 'address', 'address1'],
        'postcode' => ['plz', 'postleitzahl', 'postcode', 'postalcode', 'zip', 'zipcode'],
        'city' => ['ort', 'stadt', 'wohnort', 'city', 'town'],
        'instagram' => ['instagram', 'insta'],
        'facebook' => ['facebook'],
        'tiktok' => ['tiktok'],
        'pinterest' => ['pinterest'],
        'linkedin' => ['linkedin'],
        'website' => ['website', 'webseite', 'homepage', 'web', 'url'],
    ];

    /** @return list<string> */
    public static function fields(): array
    {
        return array_keys(self::HEADERS);
    }

    /** @return array{headers: list<string>, rows: array<int, list<string>>} rows keyed by their line in the file */
    public static function read(string $path): array
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($path));

        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }

        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $text);
        rewind($stream);

        $first = (string) fgets($stream);
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        rewind($stream);

        $records = [];
        $line = 1;

        while (($start = ftell($stream)) !== false && ($record = fgetcsv($stream, separator: $delimiter, escape: '')) !== false) {
            $record = array_map(fn (?string $cell) => trim((string) $cell), $record);

            if (array_filter($record) !== []) {
                $records[$line] = $record;
            }

            $line += substr_count(substr($text, $start, ftell($stream) - $start), "\n");
        }

        fclose($stream);

        $headerLine = array_key_first($records);
        $headers = $records[$headerLine] ?? [];
        unset($records[$headerLine]);

        return ['headers' => $headers, 'rows' => $records];
    }

    /**
     * One field per column, each field at most once.
     *
     * @param  list<string>  $headers
     * @return list<string>
     */
    public static function suggest(array $headers): array
    {
        $taken = [];

        return array_map(function (string $header) use (&$taken) {
            $key = preg_replace('/[^\p{L}\p{N}]/u', '', mb_strtolower($header));
            $field = collect(self::HEADERS)->search(fn (array $names) => in_array($key, $names, true));

            if ($field === false || isset($taken[$field])) {
                return '';
            }

            return $taken[$field] = $field;
        }, $headers);
    }

    /**
     * @param  list<string>  $row
     * @param  list<string>  $mapping  the field each column feeds, '' for none
     * @return array<string, mixed>
     */
    public static function attributes(array $row, array $mapping): array
    {
        $values = [];

        foreach ($mapping as $index => $field) {
            if ($field !== '') {
                $values[$field] = $row[$index] ?? '';
            }
        }

        $value = fn (string $field) => ($values[$field] ?? '') === '' ? null : $values[$field];

        $profiles = collect(SocialPlatform::cases())
            ->filter(fn (SocialPlatform $platform) => $value($platform->value) !== null)
            ->map(fn (SocialPlatform $platform) => ['platform' => $platform->value, 'value' => $values[$platform->value]])
            ->values()->all();

        return [
            'name' => $value('name') ?? trim($value('first_name').' '.$value('last_name')),
            'email' => $value('email'),
            'phone' => $value('phone'),
            'street' => $value('street'),
            'postcode' => $value('postcode'),
            'city' => $value('city'),
            'social_profiles' => $profiles ?: null,
        ];
    }
}
