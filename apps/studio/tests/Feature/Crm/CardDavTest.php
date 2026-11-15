<?php

use App\Crm\Models\Contact;
use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const DAV_NAMESPACES = ['d' => 'DAV:', 'card' => 'urn:ietf:params:xml:ns:carddav', 'cs' => 'http://calendarserver.org/ns/'];

function dav(string $method, string $uri, ?string $body = null, array $headers = [], ?User $user = null, ?string $secret = null)
{
    $server = ['HTTP_DEPTH' => $headers['Depth'] ?? null, 'CONTENT_TYPE' => 'application/xml'];

    if ($user !== null) {
        $server['HTTP_AUTHORIZATION'] = 'Basic '.base64_encode($user->email.':'.($secret ?? $user->carddavSecret()));
    }

    return test()->call($method, $uri, [], [], [], array_filter($server), $body);
}

function davProps(string ...$names): string
{
    $inner = collect($names)->map(fn ($n) => str_contains($n, ':') ? "<{$n}/>" : "<d:{$n}/>")->implode('');

    return '<?xml version="1.0"?><d:propfind xmlns:d="DAV:" xmlns:card="urn:ietf:params:xml:ns:carddav" xmlns:cs="http://calendarserver.org/ns/"><d:prop>'.$inner.'</d:prop></d:propfind>';
}

function davXpath(string $xml): DOMXPath
{
    $doc = new DOMDocument;
    $doc->loadXML($xml);
    $xpath = new DOMXPath($doc);

    foreach (DAV_NAMESPACES as $prefix => $uri) {
        $xpath->registerNamespace($prefix, $uri);
    }

    return $xpath;
}

function davMultiget(string ...$hrefs): string
{
    return '<?xml version="1.0"?><card:addressbook-multiget xmlns:d="DAV:" xmlns:card="urn:ietf:params:xml:ns:carddav"><d:prop><d:getetag/><card:address-data/></d:prop>'
        .collect($hrefs)->map(fn ($h) => "<d:href>{$h}</d:href>")->implode('').'</card:addressbook-multiget>';
}

it('redirects well-known carddav to the dav root', function () {
    $response = dav('PROPFIND', '/.well-known/carddav')->assertStatus(301);

    expect($response->headers->get('Location'))->toEndWith('/dav/');
});

it('challenges a request without credentials', function () {
    $user = staffMember();

    for ($i = 0; $i < 25; $i++) {
        $response = dav('PROPFIND', '/dav/')->assertStatus(401);
    }

    expect($response->headers->get('WWW-Authenticate'))->toStartWith('Basic realm=');
    dav('PROPFIND', '/dav/', null, ['Depth' => '0'], $user)->assertStatus(207);
});

it('rejects a wrong secret', function () {
    dav('PROPFIND', '/dav/', null, [], staffMember(), 'nope')->assertStatus(401);
});

it('does not accept the login password', function () {
    $user = staffMember();
    $user->carddavSecret();

    dav('PROPFIND', '/dav/', null, [], $user, 'password')->assertStatus(401);
});

it('matches the user name case-insensitively', function () {
    $user = staffMember();
    $secret = $user->carddavSecret();

    $this->call('PROPFIND', '/dav/', [], [], [], [
        'HTTP_AUTHORIZATION' => 'Basic '.base64_encode(ucfirst($user->email).':'.$secret),
    ])->assertStatus(207);
});

it('throttles repeated failures per user name', function () {
    $user = staffMember();
    $secret = $user->carddavSecret();

    for ($i = 0; $i < 20; $i++) {
        dav('PROPFIND', '/dav/', null, [], $user, 'wrong')->assertStatus(401);
    }

    expect(dav('PROPFIND', '/dav/', null, [], $user, $secret)->assertStatus(429)->headers->get('Retry-After'))->not->toBeNull();
});

it('refuses a role without manage_contacts', function () {
    dav('PROPFIND', '/dav/', null, [], staffMember(StaffRole::Contributor))->assertStatus(403);
});

it('stops accepting the old secret after regeneration', function () {
    $user = staffMember();
    $old = $user->carddavSecret();

    dav('PROPFIND', '/dav/', null, ['Depth' => '0'], $user, $old)->assertStatus(207);
    $new = $user->regenerateCarddavSecret();
    dav('PROPFIND', '/dav/', null, ['Depth' => '0'], $user, $old)->assertStatus(401);
    dav('PROPFIND', '/dav/', null, ['Depth' => '0'], $user, $new)->assertStatus(207);
});

it('runs without session or csrf', function () {
    $response = dav('PROPFIND', '/dav/', null, ['Depth' => '0'], staffMember())->assertStatus(207);

    $response->assertHeaderMissing('Set-Cookie');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('advertises carddav on options', function () {
    expect(dav('OPTIONS', '/dav/', null, [], staffMember())->assertOk()->headers->get('DAV'))->toContain('addressbook');
});

it('discovers principal and address book home', function () {
    $user = staffMember();

    $root = davXpath(dav('PROPFIND', '/dav/', davProps('d:current-user-principal'), ['Depth' => '0'], $user)->getContent());
    expect($root->evaluate('string(//d:current-user-principal/d:href)'))->toBe('/dav/principal/');

    $principal = davXpath(dav('PROPFIND', '/dav/principal/', davProps('card:addressbook-home-set', 'd:displayname'), ['Depth' => '0'], $user)->getContent());
    expect($principal->evaluate('string(//card:addressbook-home-set/d:href)'))->toBe('/dav/books/')
        ->and($principal->evaluate('string(//d:displayname)'))->toBe($user->name);
});

it('lists the studio address book in the home', function () {
    $x = davXpath(dav('PROPFIND', '/dav/books/', davProps('d:resourcetype', 'd:displayname', 'cs:getctag', 'd:supported-report-set'), ['Depth' => '1'], staffMember())->getContent());
    $book = '//d:response[d:href="/dav/books/studio/"]';

    expect($x->query("{$book}//d:resourcetype/card:addressbook")->length)->toBe(1)
        ->and($x->query("{$book}//cs:getctag")->length)->toBe(1)
        ->and($x->query("{$book}//d:supported-report-set//card:addressbook-multiget")->length)->toBe(1);
});

it('answers unknown properties with 404 propstat', function () {
    $x = davXpath(dav('PROPFIND', '/dav/books/studio/', davProps('d:quota-available-bytes', 'd:sync-token', 'cs:getctag'), ['Depth' => '0'], staffMember())->getContent());

    expect($x->query('//d:propstat[contains(d:status, "404")]/d:prop/d:quota-available-bytes')->length)->toBe(1)
        ->and($x->query('//d:propstat[contains(d:status, "404")]/d:prop/d:sync-token')->length)->toBe(1)
        ->and($x->query('//d:propstat[contains(d:status, "200")]/d:prop/cs:getctag')->length)->toBe(1);
});

it('lists one card per contact with an etag', function () {
    $contact = Contact::factory()->create();

    $x = davXpath(dav('PROPFIND', '/dav/books/studio/', davProps('d:getetag'), ['Depth' => '1'], staffMember())->getContent());

    expect($x->evaluate("string(//d:response[d:href='/dav/books/studio/{$contact->public_id}.vcf']//d:getetag)"))->toStartWith('"');
});

it('returns vcards for an addressbook-multiget', function () {
    $contact = Contact::factory()->create(['name' => 'Lena Meier', 'email' => 'lena@example.test', 'phone' => '0151 1234']);

    $body = davMultiget("/dav/books/studio/{$contact->public_id}.vcf", '/dav/books/studio/nope.vcf');
    $x = davXpath(dav('REPORT', '/dav/books/studio/', $body, ['Depth' => '1'], staffMember())->assertStatus(207)->getContent());

    $data = $x->evaluate("string(//d:response[d:href='/dav/books/studio/{$contact->public_id}.vcf']//card:address-data)");

    expect($data)->toContain('FN:Lena Meier')->toContain('EMAIL;TYPE=INTERNET:lena@example.test')->toContain('TEL;TYPE=VOICE:0151 1234')
        ->and($x->evaluate('string(//d:response[d:href="/dav/books/studio/nope.vcf"]/d:status)'))->toContain('404');
});

it('returns every card for an addressbook-query', function () {
    Contact::factory()->count(2)->create();

    $body = '<?xml version="1.0"?><card:addressbook-query xmlns:d="DAV:" xmlns:card="urn:ietf:params:xml:ns:carddav"><d:prop><d:getetag/></d:prop></card:addressbook-query>';
    $x = davXpath(dav('REPORT', '/dav/books/studio/', $body, [], staffMember())->assertStatus(207)->getContent());

    expect($x->query('//d:response')->length)->toBe(2);
});

it('serves a single card by get', function () {
    $contact = Contact::factory()->create();
    $user = staffMember();

    $etag = davXpath(dav('PROPFIND', "/dav/books/studio/{$contact->public_id}.vcf", davProps('d:getetag'), ['Depth' => '0'], $user)->getContent())
        ->evaluate('string(//d:getetag)');

    $response = dav('GET', "/dav/books/studio/{$contact->public_id}.vcf", null, [], $user)->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('text/vcard; charset=utf-8')
        ->and($response->headers->get('ETag'))->toBe($etag)
        ->and($response->getContent())->toStartWith("BEGIN:VCARD\r\n");
});

it('leaves dsgvo-erased contacts out', function () {
    $contact = Contact::factory()->create(['dsgvo_erased_at' => now()]);
    $user = staffMember();
    $href = "/dav/books/studio/{$contact->public_id}.vcf";

    $x = davXpath(dav('PROPFIND', '/dav/books/studio/', davProps('d:getetag'), ['Depth' => '1'], $user)->getContent());
    expect($x->query("//d:href[.='{$href}']")->length)->toBe(0);

    $x = davXpath(dav('REPORT', '/dav/books/studio/', davMultiget($href), [], $user)->getContent());
    expect($x->evaluate('string(//d:response/d:status)'))->toContain('404');

    dav('GET', $href, null, [], $user)->assertNotFound();
});

it('moves the ctag when a contact changes or is erased', function () {
    $user = staffMember();
    $contact = Contact::factory()->create();
    $ctag = fn () => davXpath(dav('PROPFIND', '/dav/books/studio/', davProps('cs:getctag'), ['Depth' => '0'], $user)->getContent())->evaluate('string(//cs:getctag)');

    $before = $ctag();
    $contact->update(['phone' => '0151 99999']);
    $changed = $ctag();
    $contact->forceFill(['dsgvo_erased_at' => now()])->save();

    expect($changed)->not->toBe($before)->and($ctag())->not->toBe($changed);
});

it('refuses writes', function (string $method) {
    $contact = Contact::factory()->create(['name' => 'Lena Meier']);

    dav($method, "/dav/books/studio/{$contact->public_id}.vcf", 'BEGIN:VCARD', [], staffMember())->assertForbidden();

    expect($contact->fresh()->name)->toBe('Lena Meier');
})->with(['PUT', 'DELETE', 'PROPPATCH', 'MKCOL', 'MOVE']);

it('rejects malformed xml', function () {
    dav('PROPFIND', '/dav/', '<d:propfind', ['Depth' => '0'], staffMember())->assertStatus(400);
});
