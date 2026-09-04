# Mandantenfähigkeit über Hosts — Subdomains, eigene Domains, Registrierung

Status: Entwurf, Abschnitte 1–5 im Gespräch abgenommen.
Datum: 2026-09-04

## Ziel

Ein Deployment, viele Studios. Jedes Studio bekommt seine drei Flächen unter
`auri-crm.de` und kann jede davon auf eine eigene Domain legen:

| Host | Fläche |
|---|---|
| `{slug}.auri-crm.de` | admin |
| `{slug}-portal.auri-crm.de` | portal |
| `{slug}-galerie.auri-crm.de` | gallery (Astro) |
| `api.auri-crm.de` | api, ein Host für alle |
| `auri-crm.de`, `www.` | admin ohne Mandant: Registrierung, Studio finden |
| eigene Domain | die Fläche, die die Zeile in `tenant_domains` nennt |

Nicht im Umfang: Tarife, Abrechnung, Betreiber-Oberfläche. Die Mandantenliste
bleibt `tinker`, bis ein Grund für mehr da ist.

## Ausgangslage

- Flächen sind Prozesse (`APP_SURFACE`), je Prozess genau eine Routendatei.
  Isolation durch Abwesenheit, nicht durch Middleware. Bleibt.
- Tenancy ist angelegt, aber schlafend: `tenant_id` auf jeder Tabelle,
  `BelongsToTenant` als globaler Scope, Wert aus `config('tenancy.tenant_id')`
  = `TENANT_ID` aus der Umgebung. `users` sind `unique(tenant_id, email)`,
  `studio_settings` `unique(tenant_id)`, Objektpfade `galleries/{tenant_id}/…`.
  `TenantSeamTest` hält den Scope.
- Jobs bekommen die Request-ID über `Queue::createPayloadUsing` in die Payload
  (`ObservabilityServiceProvider`). Dasselbe Muster trägt den Mandanten.
- Coolify vergibt heute je Web-Service eine Domain, Proxy ist Traefik.

## Entscheidungen

| Frage | Entscheidung | Verworfen |
|---|---|---|
| Hostform | `slug-portal.auri-crm.de` | Pfade auf einem Host (Cookies, Remap verliert den Pfad); `portal.slug.` (kein `*.*`-Zertifikat) |
| Mandant erzeugen | Selbstregistrierung | nur Artisan |
| TLS für eigene Domains | Caddy On-Demand TLS mit `ask` | Traefik + generierte Router-Dateien; Cloudflare for SaaS (Auftragsverarbeiter) |
| Mandant auflösen | Middleware setzt `tenancy.tenant_id` je Request, bestehender Scope | stancl/tenancy (auf DB je Mandant gebaut); Deployment je Studio |
| Routing eigener Domains | zustandslos, `forward_auth` gegen die api | Caddy-Admin-API pushen (Coolify überschreibt die Proxy-Konfiguration) |
| Wildcard | `*.auri-crm.de` per DNS-01 über Cloudflare | On-Demand auch für Basis-Hosts: Let's Encrypt erlaubt 50 Zertifikate je Domain und Woche, drei je Studio, also etwa 16 Registrierungen pro Woche |
| Bestandsdaten | keine: Produktion ist Demo-Seed, wird frisch aufgesetzt | Übernahme mit Slug und Umleitung alter Hosts |

## 1. Hosts und Edge

**Slug-Grammatik.** `^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])?$`, darf nicht auf
`-portal` oder `-galerie` enden, Sperrliste `www app api admin portal galerie
static edge`. Als Check-Constraint auf `tenants.slug` und als Formregel.

**Coolify.** Der Proxy wechselt auf Caddy (`caddy-docker-proxy`), gebaut mit
`caddy-dns/cloudflare` per `dockerfile_inline` wie in Coolifys
DNS-Challenge-Anleitung. Labels am Proxy-Container:

```
caddy.acme_dns=cloudflare {env.CLOUDFLARE_API_TOKEN}
caddy.on_demand_tls=
caddy.on_demand_tls.ask=http://api:8080/edge/tls
```

"Override default request handler" bleibt aus. Cloudflare-Token nur mit
`Zone.DNS: Edit` auf `auri-crm.de`.

Coolify braucht je Web-Service weiter eine FQDN, sonst erzeugt es weder
Proxy-Labels noch Health-Routing:

| Service | `SERVICE_FQDN_*` |
|---|---|
| `admin` | `auri-crm.de` |
| `portal` | `portal.auri-crm.de`, leitet auf den Apex um |
| `gallery` | `galerie.auri-crm.de`, dito |
| `api` | `api.auri-crm.de` |
| `rustfs` | wie heute |

`APP_URL` bleibt je Service aus seiner FQDN abgeleitet und ist, was die
mandantenlosen Apex-Routen nutzen. `PORTAL_URL` und `GALLERY_URL` entfallen.
Neu: `EDGE_BASE_DOMAIN=auri-crm.de`.

**Dynamische Datei** `/data/coolify/proxy/caddy/dynamic/auri.caddy`:

```caddyfile
*.auri-crm.de, https:// {
    tls { on_demand }
    forward_auth api:8080 {
        uri /edge/route
        copy_headers X-Upstream X-Tenant-Slug
    }
    reverse_proxy {http.request.header.X-Upstream}
}
```

Der Wildcard-Block bekommt sein Zertifikat über `acme_dns`; `https://` fängt
eigene Domains und wird durch `ask` begrenzt. `reverse_proxy` mit Platzhalter
ist laut Caddy "dynamically static": vier feste Upstreams, ein
Health-Check-Pool. Akzeptiert. Die api-FQDN läuft über Coolifys eigene
Labels, damit `/edge/route` nie durch `forward_auth` schleift.

**DNS bei Cloudflare.** `*.auri-crm.de` A-Record und `edge.auri-crm.de`
(CNAME-Ziel für eigene Domains) ohne Proxy (graue Wolke), sonst treffen
HTTP-01 und `forward_auth` Cloudflare statt Caddy.

**Edge-Endpunkte** auf der api-Fläche, öffentlich, je Host in Redis gecacht,
invalidiert bei jedem Schreiben auf `tenant_domains`:

| Route | Antwort |
|---|---|
| `GET /edge/tls?domain=` | 200 für Apex, `*.auri-crm.de`, verifizierte Zeilen; sonst 404 |
| `GET /edge/route` (Host aus `X-Forwarded-Host`) | 200 mit `X-Upstream: portal:8080`, `X-Tenant-Slug`; unbekannt 404, Caddy antwortet 404 und proxied nicht |

## 2. Mandant auflösen

**`tenants`**: `id` uuid (passt zu den bestehenden `tenant_id`-Spalten),
`public_id`, `slug` unique, Zeitstempel. Keine Fremdschlüssel auf die 40
Tabellen; der Scope ist getestet. Der Blueprint-Makro `tenantId()` verliert
seinen Spalten-Default aus `config('tenancy.tenant_id')`; eine Migration
entfernt die Defaults auf den bestehenden Spalten. `config/tenancy.php` und
`TENANT_ID` entfallen: ein Request ohne Mandant scheitert laut.

**`ResolveTenant`**, global direkt nach `AssignRequestId`, alle drei Flächen:

1. Host ist `api.auri-crm.de`: Mandant aus der Token-Zeile (ohne globalen
   Scope geladen) oder aus `X-Tenant-Slug` für das öffentliche Galerie-JSON.
   Keins von beiden: 404.
2. Sonst Host parsen: `{slug}.`, `{slug}-portal.`, `{slug}-galerie.` unter
   `EDGE_BASE_DOMAIN`, oder Zeile in `tenant_domains`. Abgeleitete Fläche
   muss `Surface::current()` sein, sonst 404.
3. Apex auf admin: mandantenlos, nur die Gruppe `tenantless` (Registrierung,
   Anmelden-Weiche) ist registriert. Alles andere 404.
4. Setzt `config(['tenancy.tenant_id' => …])`, bindet `Tenant` als Singleton.

**Außerhalb des Requests.** `Queue::createPayloadUsing` stempelt `tenant_id`,
`Queue::before` stellt es her und ruft `URL::forceRootUrl($tenant->url('portal'))`,
weil Mails auf das Portal verlinken. Geplante Befehle laufen über
`Tenant::all()`.

**`Tenant::url(surface)`** liefert die verifizierte eigene Domain der Fläche,
sonst den Basis-Host. Ersetzt `config('portal.url')` und
`config('services.gallery.url')` an ihren Aufrufstellen.

Redis-Schlüssel mit Mandantendaten (`StudioSettingsResolver` u. a.) tragen
`tenant_id`. Session-Cookies sind host-gebunden, unverändert.

Test `TenantResolutionTest`: Host-Matrix, falsche Fläche 404, Payload-Rundlauf.

## 3. Eigene Domains

**`tenant_domains`**: `tenant_id`, `host` unique (Kleinschreibung, Punycode),
`surface` enum admin|portal|gallery, `verified_at` nullable, Zeitstempel.
Unique `(tenant_id, surface)`: eine Domain je Fläche.

Ablauf unter Einstellungen → Domains:

1. Host eintragen, Fläche wählen. Zeile unverifiziert. Anzeige einer Zeile:
   `CNAME {host} → edge.auri-crm.de`.
2. `VerifyTenantDomain` löst den CNAME per `dns_get_record` auf und vergleicht
   mit `edge.auri-crm.de`. Button „Prüfen“, dazu ein täglicher Sweep, der
   jede Zeile neu prüft; ein entfernter CNAME fällt binnen eines Tages zurück.
3. Verifiziert: `verified_at` gesetzt, Edge-Cache des Hosts invalidiert,
   `Tenant::url()` liefert ab jetzt diese Domain. Basis-Host bleibt erreichbar,
   keine Umleitung.

Apex-A-Record und `edge.auri-crm.de` zeigen beide auf den Server.

Nicht im Umfang: Zertifikat beim Entfernen räumen (läuft ab), TXT-Nachweis
(der CNAME auf unseren Host ist der Nachweis), mehrere Domains je Fläche.

Test `TenantDomainTest`: Verifizierung mit gefälschtem Resolver, unverifizierter
Host 404 auf beiden Edge-Endpunkten, `Tenant::url()` kippt nach Verifizierung.

## 4. Registrierung auf dem Apex

`auri-crm.de/registrieren`: Studioname, Slug (aus dem Namen vorbelegt, beim
Verlassen des Felds gegen `tenants` und Sperrliste geprüft), E-Mail. Absenden
legt die Mandantenzeile an und mailt einen signierten Link auf
`https://{slug}.auri-crm.de/einrichtung?signature=…`, 24 Stunden gültig.
Rate-Limit je IP.

Warum die Mail: `/einrichtung` steht offen, bis der erste Staff-User existiert.
Ein frischer Host ohne Anspruch gehört sonst dem, der ihn zuerst aufruft.
`EnsureNoStaffExists` bekommt eine Bedingung: bei einem Mandanten ohne Staff
muss der Request eine gültige Signatur tragen. Danach der bestehende
Einrichtungsassistent, unverändert. E-Mail ist damit nebenbei bestätigt.

Täglicher Sweep löscht Mandanten ohne Staff, älter als 7 Tage; der Slug wird
frei.

`auri-crm.de/anmelden` fragt E-Mail und Passwort. `users.email` ist nur je
Mandant unique, also prüft der Apex das Passwort gegen jedes Konto dieser
Adresse (ohne globalen Scope geladen). Ein Treffer: signierte Übergabe auf
`{slug}.auri-crm.de/login/uebergabe?signature=…`, 60 Sekunden gültig, einmal
einlösbar; die Session entsteht auf dem Mandanten-Host, TOTP läuft dort wie
heute. Mehrere Treffer: Studio-Auswahl, nur die Studios, deren Passwort
passte. Kein Treffer: eine Meldung, gleich ob Adresse unbekannt oder
Passwort falsch. Der Apex hält keine Session.

Auf `{slug}.auri-crm.de/login` ändert sich nichts: der Mandant steht durch den
Host fest, geprüft wird nur das Konto dieses Studios, keine Auswahl, keine
Übergabe.

Test `SignupTest`: gesperrter Slug abgelehnt, vergebener Slug abgelehnt,
Assistent 404 ohne Signatur, 200 mit, Sweep lässt beanspruchte Mandanten
stehen. Test `ApexLoginTest`: ein Konto leitet um, zwei Konten zeigen die
Auswahl, Übergabe-Link ist nach dem ersten Einlösen tot.

## 5. Galerie, Deployment, Umstellung, Leitplanken

**Astro-Galerie** liest `X-Tenant-Slug` aus dem Request und sendet ihn auf
jedem api-Aufruf, auch aus dem Browser. api-CORS erlaubt `*.auri-crm.de` und
verifizierte Galerie-Domains. `PUBLIC_API_URL` bleibt ein Wert.

**Compose.** `check-coolify-compose.php` lernt die neuen Invarianten
(`EDGE_BASE_DOMAIN` gesetzt, kein `PORTAL_URL`/`GALLERY_URL` mehr).

**Umstellung.** Produktion trägt nur den Demo-Seed. Kein Datenschritt, keine
Umleitung alter Hosts: Stack neu aufsetzen, Proxy auf Caddy, DNS-Records
anlegen, `migrate:fresh --seed`. Der Seeder legt den Demo-Mandanten mit Slug
an; jeder Seeder, der `tenant_id` setzt, nimmt ihn von dort.

**Leitplanken.** `SurfaceIsolationTest`: jede Route jeder Fläche 404 ohne
aufgelösten Mandanten, Apex-Gruppe `tenantless` als Ausnahme gelistet.
`AudienceMatrixTest`: Edge-Endpunkte als dokumentierte öffentliche Routen.
`NoInternalIdsTest` deckt `Tenant`.

## Teilprojekte

Je eigener Plan und PR, in dieser Reihenfolge:

1. Host-basierte Tenancy (§1, §2, Proxy-Wechsel, Seeder, Umstellung §5).
2. Eigene Domains (§3).
3. Registrierung (§4).

**Spike vor Plan 1.** Ein Caddy auf dem Coolify-Server mit der dynamischen
Datei aus §1 gegen einen Hello-World-Upstream: Wildcard über Cloudflare,
`forward_auth` kopiert Header, On-Demand-Ausstellung für eine Wegwerfdomain.
Ein halber Tag, beantwortet die einzige offene Unbekannte.

## Quellen

- [Coolify: Caddy DNS Challenge](https://coolify.io/docs/knowledge-base/proxy/caddy/dns-challenge)
- [Coolify Discussion #6254: on_demand_tls](https://github.com/coollabsio/coolify/discussions/6254)
- [Caddy: reverse_proxy, Platzhalter in Upstreams](https://caddyserver.com/docs/caddyfile/directives/reverse_proxy)
- [Caddy: forward_auth](https://caddyserver.com/docs/caddyfile/directives/forward_auth)
- [Caddy: globale Optionen](https://caddyserver.com/docs/caddyfile/options)
