<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Only the rules this application actually uses are translated. A rule that
    | appears here and is never used is a string nobody proofreads; when a new
    | rule is introduced, its line is added with it.
    |
    */

    'uploaded' => 'Die Datei für :attribute konnte nicht hochgeladen werden, sie ist zu groß.',
    'mimes' => 'Das Feld :attribute muss den Dateityp :values haben.',
    'image' => 'Das Feld :attribute muss ein Bild sein.',
    'url' => 'Das Feld :attribute muss eine gültige Adresse sein.',
    'timezone' => 'Das Feld :attribute muss eine gültige Zeitzone sein.',
    'distinct' => 'Das Feld :attribute enthält einen doppelten Wert.',
    'filled' => 'Das Feld :attribute darf nicht leer sein.',
    'gte' => [
        'array' => 'Das Feld :attribute muss mindestens :value Einträge haben.',
        'file' => 'Das Feld :attribute muss mindestens :value Kilobyte groß sein.',
        'numeric' => 'Das Feld :attribute muss mindestens :value sein.',
        'string' => 'Das Feld :attribute muss mindestens :value Zeichen lang sein.',
    ],
    'lte' => [
        'array' => 'Das Feld :attribute darf höchstens :value Einträge haben.',
        'file' => 'Das Feld :attribute darf höchstens :value Kilobyte groß sein.',
        'numeric' => 'Das Feld :attribute darf höchstens :value sein.',
        'string' => 'Das Feld :attribute darf höchstens :value Zeichen lang sein.',
    ],
    'between' => [
        'array' => 'Das Feld :attribute muss zwischen :min und :max Einträge haben.',
        'file' => 'Das Feld :attribute muss zwischen :min und :max Kilobyte groß sein.',
        'numeric' => 'Das Feld :attribute muss zwischen :min und :max liegen.',
        'string' => 'Das Feld :attribute muss zwischen :min und :max Zeichen lang sein.',
    ],
    'accepted' => 'Das Feld :attribute muss akzeptiert werden.',
    'after' => 'Das Feld :attribute muss ein Datum nach dem :date sein.',
    'after_or_equal' => 'Das Feld :attribute muss ein Datum nach dem :date oder gleich diesem sein.',
    'before' => 'Das Feld :attribute muss ein Datum vor dem :date sein.',
    'boolean' => 'Das Feld :attribute muss wahr oder falsch sein.',
    'confirmed' => 'Die Bestätigung für :attribute stimmt nicht überein.',
    'current_password' => 'Das angegebene Passwort ist nicht korrekt.',
    'date' => 'Das Feld :attribute muss ein gültiges Datum sein.',
    'date_format' => 'Das Feld :attribute entspricht nicht dem Format :format.',
    'email' => 'Das Feld :attribute muss eine gültige E-Mail-Adresse sein.',
    'enum' => 'Der gewählte Wert für :attribute ist ungültig.',
    'exists' => 'Der gewählte Wert für :attribute ist ungültig.',
    'in' => 'Der gewählte Wert für :attribute ist ungültig.',
    'integer' => 'Das Feld :attribute muss eine ganze Zahl sein.',
    'max' => [
        'array' => 'Das Feld :attribute darf höchstens :max Einträge haben.',
        'file' => 'Das Feld :attribute darf höchstens :max Kilobyte groß sein.',
        'numeric' => 'Das Feld :attribute darf höchstens :max sein.',
        'string' => 'Das Feld :attribute darf höchstens :max Zeichen lang sein.',
    ],
    'min' => [
        'array' => 'Das Feld :attribute muss mindestens :min Einträge haben.',
        'file' => 'Das Feld :attribute muss mindestens :min Kilobyte groß sein.',
        'numeric' => 'Das Feld :attribute muss mindestens :min sein.',
        'string' => 'Das Feld :attribute muss mindestens :min Zeichen lang sein.',
    ],
    'numeric' => 'Das Feld :attribute muss eine Zahl sein.',
    'prohibited' => 'Das Feld :attribute ist unzulässig.',
    'regex' => 'Das Feld :attribute hat ein ungültiges Format.',
    'prohibited_unless' => 'Das Feld :attribute ist unzulässig.',
    'required' => 'Das Feld :attribute muss ausgefüllt werden.',
    'required_without' => 'Das Feld :attribute muss ausgefüllt werden, wenn :values fehlt.',
    'size' => [
        'array' => 'Das Feld :attribute muss genau :size Einträge haben.',
        'file' => 'Das Feld :attribute muss genau :size Kilobyte groß sein.',
        'numeric' => 'Das Feld :attribute muss genau :size sein.',
        'string' => 'Das Feld :attribute muss genau :size Zeichen lang sein.',
    ],
    'string' => 'Das Feld :attribute muss eine Zeichenkette sein.',
    'unique' => ':attribute ist bereits vergeben.',
    'form_field_type_locked' => 'Die Frage ist beantwortet, der Typ bleibt.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The field names as they appear on screen, so an error message reads the
    | way the label above the field does.
    |
    */

    'attributes' => [
        'captured_via' => 'Eingangskanal',
        'code' => 'Code',
        'contact_email' => 'E-Mail',
        'contact_id' => 'Kontakt',
        'contact_name' => 'Name',
        'contact_phone' => 'Telefon',
        'date' => 'Haupttermin',
        'datum' => 'Datum',
        'email' => 'E-Mail',
        'inquiry_text' => 'Anfragetext',
        'name' => 'Name',
        'notification_body' => 'Text',
        'notification_subject' => 'Betreff',
        'partner_name' => 'Partner/in',
        'password' => 'Passwort',
        'phone' => 'Telefon',
        'portal_contact_avatar' => 'Bild',
        'portal_contact_email' => 'E-Mail',
        'portal_contact_name' => 'Name',
        'portal_contact_phone' => 'Telefon',
        'portal_contact_response_time' => 'Erreichbarkeit',
        'project_name' => 'Projektname',
        'reason' => 'Grund',
        'source' => 'Quelle',
        'stage' => 'Status',
        'type' => 'Typ',
    ],

];
