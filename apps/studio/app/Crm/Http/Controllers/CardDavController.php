<?php

namespace App\Crm\Http\Controllers;

use App\Crm\Models\Contact;
use App\Crm\Support\ContactCard;
use App\Http\Controllers\Controller;
use App\Models\StudioSettings;
use App\Models\User;
use App\Support\AuditLogger;
use Closure;
use DOMDocument;
use DOMElement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * A read-only CardDAV address book for the iPhone. Hand-rolled: iOS needs
 * about six request shapes against four fixed resources, and the vCard parts
 * that are easy to get wrong already live in FormatsIcs. A phone cannot hold a
 * session, so every request carries Basic credentials: the staff e-mail and
 * the per-user secret, never the login password.
 */
class CardDavController extends Controller
{
    public const METHODS = ['OPTIONS', 'GET', 'HEAD', 'PROPFIND', 'REPORT', 'PUT', 'DELETE', 'PROPPATCH', 'MKCOL', 'COPY', 'MOVE', 'POST', 'LOCK', 'UNLOCK'];

    private const MAX_ATTEMPTS = 20;

    private const DECAY_SECONDS = 300;

    private const D = 'DAV:';

    private const C = 'urn:ietf:params:xml:ns:carddav';

    private const CS = 'http://calendarserver.org/ns/';

    private const PREFIXES = [self::D => 'd', self::C => 'card', self::CS => 'cs'];

    private const WRITES = ['PUT', 'DELETE', 'PROPPATCH', 'MKCOL', 'COPY', 'MOVE', 'POST', 'LOCK', 'UNLOCK'];

    public function __construct(private readonly ContactCard $card) {}

    public function wellKnown(): Response
    {
        return new Response('', 301, ['Location' => '/dav/', 'Cache-Control' => 'no-store, private']);
    }

    public function __invoke(Request $request, ?string $path = null): SymfonyResponse
    {
        $response = $this->handle($request, trim((string) $path, '/'));
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    public function regenerate(): RedirectResponse
    {
        $user = auth('staff')->user();
        $user->regenerateCarddavSecret();

        AuditLogger::record('staff.carddav.secret_regenerated', $user);

        return back();
    }

    private function handle(Request $request, string $path): SymfonyResponse
    {
        $user = $this->authenticate($request, $denied);

        if ($user === null) {
            return $denied;
        }

        if (Gate::forUser($user)->denies('viewAny', Contact::class)) {
            return new Response('', 403);
        }

        $method = $request->method();

        if (in_array($method, self::WRITES, true)) {
            return new Response('', 403);
        }

        $resource = $this->resolve($path);

        if ($resource === null) {
            return new Response('', 404);
        }

        return match ($method) {
            'OPTIONS' => new Response('', 200, [
                'DAV' => '1, 3, addressbook',
                'Allow' => 'OPTIONS, GET, HEAD, PROPFIND, REPORT',
            ]),
            'PROPFIND' => $this->propfind($request, $resource, $user),
            'REPORT' => $this->report($request, $resource, $user),
            default => $this->get($resource),
        };
    }

    private function authenticate(Request $request, ?SymfonyResponse &$denied): ?User
    {
        $username = (string) $request->getUser();
        $challenge = new Response('', 401, ['WWW-Authenticate' => 'Basic realm="Auri CRM", charset="UTF-8"']);

        // iOS probes without credentials first; counting that would lock every phone out.
        if ($username === '') {
            $denied = $challenge;

            return null;
        }

        $keys = ['carddav-ip:'.$request->ip(), 'carddav-user:'.mb_strtolower(trim($username))];

        foreach ($keys as $key) {
            if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
                $denied = new Response('', 429, ['Retry-After' => (string) RateLimiter::availableIn($key)]);

                return null;
            }
        }

        $user = User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($username))])->first();

        if ($user === null || $user->carddav_secret === null || ! hash_equals($user->carddav_secret, (string) $request->getPassword())) {
            foreach ($keys as $key) {
                RateLimiter::hit($key, self::DECAY_SECONDS);
            }

            $denied = $challenge;

            return null;
        }

        return $user;
    }

    /** @return array{kind: string, href: string, card?: Contact}|null */
    private function resolve(string $path): ?array
    {
        $resource = match ($path) {
            '' => ['kind' => 'root', 'href' => '/dav/'],
            'principal' => ['kind' => 'principal', 'href' => '/dav/principal/'],
            'books' => ['kind' => 'books', 'href' => '/dav/books/'],
            'books/studio' => ['kind' => 'studio', 'href' => '/dav/books/studio/'],
            default => null,
        };

        if ($resource !== null || ! preg_match('#^books/studio/([^/]+)\.vcf$#', $path, $match)) {
            return $resource;
        }

        $card = $this->contacts()->where('public_id', $match[1])->first();

        return $card === null ? null : ['kind' => 'card', 'href' => $this->cardHref($card), 'card' => $card];
    }

    private function contacts(): Builder
    {
        return Contact::query()->whereNull('dsgvo_erased_at')->orderBy('public_id');
    }

    private function cardHref(Contact $card): string
    {
        return "/dav/books/studio/{$card->public_id}.vcf";
    }

    private function get(array $resource): SymfonyResponse
    {
        if ($resource['kind'] !== 'card') {
            return new Response('', 405);
        }

        return new Response($this->card->render($resource['card']), 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'ETag' => '"'.$this->card->etag($resource['card']).'"',
        ]);
    }

    private function propfind(Request $request, array $resource, User $user): SymfonyResponse
    {
        $body = trim($request->getContent());
        $xml = null;

        if ($body !== '') {
            $xml = $this->parse($body);

            if ($xml === null) {
                return new Response('', 400);
            }
        }

        $wanted = $xml === null ? null : $this->requestedProps($xml);
        $targets = [$resource];

        if ($request->header('Depth') !== '0') {
            $targets = [...$targets, ...$this->children($resource)];
        }

        $out = new DOMDocument('1.0', 'utf-8');
        $multistatus = $this->multistatus($out);

        foreach ($targets as $target) {
            $multistatus->appendChild($this->response($out, $target, $user, $wanted));
        }

        return $this->xml($out);
    }

    private function report(Request $request, array $resource, User $user): SymfonyResponse
    {
        $xml = $this->parse($request->getContent());

        if ($xml === null) {
            return new Response('', 400);
        }

        $root = $xml->documentElement;

        if ($resource['kind'] !== 'studio' || $root->namespaceURI !== self::C || ! in_array($root->localName, ['addressbook-multiget', 'addressbook-query'], true)) {
            return new Response('', 403);
        }

        $wanted = $this->requestedProps($xml);
        $out = new DOMDocument('1.0', 'utf-8');
        $multistatus = $this->multistatus($out);

        if ($root->localName === 'addressbook-query') {
            foreach ($this->contacts()->get() as $card) {
                $multistatus->appendChild($this->response($out, ['kind' => 'card', 'href' => $this->cardHref($card), 'card' => $card], $user, $wanted));
            }

            return $this->xml($out);
        }

        $requested = collect($root->getElementsByTagNameNS(self::D, 'href'))
            ->mapWithKeys(function (DOMElement $href) {
                $path = rawurldecode((string) parse_url(trim($href->textContent), PHP_URL_PATH));

                return [trim($href->textContent) => preg_match('#^/dav/books/studio/([^/]+)\.vcf$#', $path, $m) ? $m[1] : null];
            });

        $cards = $this->contacts()->whereIn('public_id', $requested->filter()->values())->get()->keyBy('public_id');

        foreach ($requested as $href => $id) {
            $card = $id === null ? null : $cards->get($id);

            if ($card !== null) {
                $multistatus->appendChild($this->response($out, ['kind' => 'card', 'href' => $this->cardHref($card), 'card' => $card], $user, $wanted));

                continue;
            }

            $missing = $this->el($out, self::D, 'response', $this->el($out, self::D, 'href', $href));
            $missing->appendChild($this->el($out, self::D, 'status', 'HTTP/1.1 404 Not Found'));
            $multistatus->appendChild($missing);
        }

        return $this->xml($out);
    }

    private function parse(string $body): ?DOMDocument
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = trim($body) !== '' && $doc->loadXML($body, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $doc : null;
    }

    /**
     * Null means "everything except the address data": an empty body, allprop
     * and propname all land here.
     *
     * @return list<array{0: string, 1: string}>|null
     */
    private function requestedProps(DOMDocument $xml): ?array
    {
        $prop = $xml->documentElement->getElementsByTagNameNS(self::D, 'prop')->item(0);

        if ($prop === null) {
            return null;
        }

        $props = [];

        foreach ($prop->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $props[] = [(string) $child->namespaceURI, $child->localName];
            }
        }

        return $props;
    }

    /** @return list<array{kind: string, href: string, card?: Contact}> */
    private function children(array $resource): array
    {
        return match ($resource['kind']) {
            'root' => [$this->resolve('principal'), $this->resolve('books')],
            'books' => [$this->resolve('books/studio')],
            'studio' => $this->contacts()->get()
                ->map(fn (Contact $card) => ['kind' => 'card', 'href' => $this->cardHref($card), 'card' => $card])
                ->all(),
            default => [],
        };
    }

    private function response(DOMDocument $doc, array $resource, User $user, ?array $wanted): DOMElement
    {
        $known = $this->known($doc, $resource, $user);
        $found = [];
        $missing = [];

        if ($wanted === null) {
            unset($known[self::C.' address-data']);
            $found = array_map(fn (Closure $make) => $make(), array_values($known));
        } else {
            foreach ($wanted as [$ns, $name]) {
                if (isset($known["$ns $name"])) {
                    $found[] = $known["$ns $name"]();
                } else {
                    $missing[] = $doc->createElementNS($ns, $name);
                }
            }
        }

        $response = $this->el($doc, self::D, 'response', $this->el($doc, self::D, 'href', $resource['href']));

        foreach ([[$found, 'HTTP/1.1 200 OK'], [$missing, 'HTTP/1.1 404 Not Found']] as [$props, $status]) {
            if ($props !== []) {
                $response->appendChild($this->el(
                    $doc,
                    self::D,
                    'propstat',
                    $this->el($doc, self::D, 'prop', ...$props),
                    $this->el($doc, self::D, 'status', $status),
                ));
            }
        }

        return $response;
    }

    /** @return array<string, Closure(): DOMElement> */
    private function known(DOMDocument $doc, array $resource, User $user): array
    {
        $e = fn (string $ns, string $name, string|DOMElement ...$children) => $this->el($doc, $ns, $name, ...$children);
        $href = fn (string $path) => $e(self::D, 'href', $path);
        $collection = fn (string ...$more) => $e(self::D, 'resourcetype', $e(self::D, 'collection'), ...array_map(fn ($m) => $e(self::C, $m), $more));
        $readOnly = fn () => $e(self::D, 'current-user-privilege-set', $e(self::D, 'privilege', $e(self::D, 'read')));

        $props = [
            self::D.' current-user-principal' => fn () => $e(self::D, 'current-user-principal', $href('/dav/principal/')),
        ];

        $kind = $resource['kind'];

        if ($kind === 'root' || $kind === 'books') {
            $props[self::D.' resourcetype'] = fn () => $collection();
        }

        if ($kind === 'books') {
            $props[self::D.' current-user-privilege-set'] = $readOnly;
        }

        if ($kind === 'principal') {
            $props[self::D.' resourcetype'] = fn () => $e(self::D, 'resourcetype', $e(self::D, 'principal'));
            $props[self::D.' principal-URL'] = fn () => $e(self::D, 'principal-URL', $href('/dav/principal/'));
            $props[self::D.' displayname'] = fn () => $e(self::D, 'displayname', $user->name);
            $props[self::C.' addressbook-home-set'] = fn () => $e(self::C, 'addressbook-home-set', $href('/dav/books/'));
        }

        if ($kind === 'studio') {
            $props[self::D.' resourcetype'] = fn () => $collection('addressbook');
            $props[self::D.' displayname'] = fn () => $e(self::D, 'displayname', StudioSettings::current()->display_name ?? config('app.name'));
            $props[self::D.' current-user-privilege-set'] = $readOnly;
            $props[self::D.' supported-report-set'] = fn () => $e(self::D, 'supported-report-set', ...array_map(
                fn ($report) => $e(self::D, 'supported-report', $e(self::D, 'report', $e(self::C, $report))),
                ['addressbook-multiget', 'addressbook-query'],
            ));
            $props[self::C.' supported-address-data'] = function () use ($e) {
                $type = $e(self::C, 'address-data-type');
                $type->setAttribute('content-type', 'text/vcard');
                $type->setAttribute('version', '3.0');

                return $e(self::C, 'supported-address-data', $type);
            };
            $props[self::CS.' getctag'] = fn () => $e(self::CS, 'getctag', $this->ctag());
        }

        if ($kind === 'card') {
            $card = $resource['card'];
            $props[self::D.' resourcetype'] = fn () => $e(self::D, 'resourcetype');
            $props[self::D.' getetag'] = fn () => $e(self::D, 'getetag', '"'.$this->card->etag($card).'"');
            $props[self::D.' getcontenttype'] = fn () => $e(self::D, 'getcontenttype', 'text/vcard; charset=utf-8');
            $props[self::C.' address-data'] = fn () => $e(self::C, 'address-data', $this->card->render($card));
        }

        return $props;
    }

    private function ctag(): string
    {
        // ponytail: renders every card on each book-level PROPFIND. Fine at
        // studio volume; switch to max(updated_at) plus count at tens of thousands.
        /** @var Collection<int, Contact> $cards */
        $cards = $this->contacts()->get();

        return md5($cards->map(fn (Contact $card) => $this->card->etag($card))->implode(''));
    }

    private function multistatus(DOMDocument $doc): DOMElement
    {
        $root = $this->el($doc, self::D, 'multistatus');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:card', self::C);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cs', self::CS);
        $doc->appendChild($root);

        return $root;
    }

    private function xml(DOMDocument $doc): Response
    {
        return new Response($doc->saveXML(), 207, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    private function el(DOMDocument $doc, string $ns, string $name, string|DOMElement ...$children): DOMElement
    {
        $element = $doc->createElementNS($ns, self::PREFIXES[$ns].':'.$name);

        foreach ($children as $child) {
            $element->appendChild($child instanceof DOMElement ? $child : $doc->createTextNode($child));
        }

        return $element;
    }
}
