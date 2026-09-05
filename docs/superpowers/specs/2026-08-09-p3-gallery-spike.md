# P3 Gallery — Technical Spike (G-00)

**Date:** 2026-08-09
**Status:** Complete. Verdict **go with changes**; see §7.
**Parent:** [Product Vision](2026-08-07-photography-client-management-vision-design.md) §5, §6, §9 · [Backlog](../../backlog.md) P3 · [Clickdummy](2026-08-07-clickdummy-design.md) §5
**Scope:** the four unknowns the vision defers to this spike. This is not the P3 spec and not an implementation plan — it exists to make those two writable.

The vision's §9 names four unknowns — vips throughput, zip streaming at 30 GB, signed URLs versus CDN caching, real egress cost — and refuses to commit a phase plan until they are retired. Three turn out to be cheaper than assumed and the fourth is not the question it was framed as. The interesting result is what moved into their place.

---

## 0. Verdicts

| # | Unknown | Verdict |
|---|---|---|
| 1 | libvips throughput | **Retired, and it was never the constraint.** 1.38 CPU-s per 24 MP asset for the full eight-derivative ladder. A wedding is 1.2–1.8 CPU-hours; four workers finish it in ~27 min against 1.7–6.8 h to *upload* the same 30 GB. Same-day delivery is bounded by the studio's upstream link, not by the queue. |
| 2 | Zip streaming at 30 GB | **Retired.** Store-mode assembly runs at 340 MB/s per PHP process with **23.8 MB peak RSS**, flat regardless of archive size. Split threshold **3.9 GiB**, forced by consumer unzip tooling rather than by transfer time — and that choice retires the multipart-limit question as a side effect. |
| 3 | Signed URLs vs CDN caching | **Resolved, mechanism named.** They only fight when the signature is in the cache key. bunny.net excludes the query string from the cache key by default, so a per-request HMAC token and a year-long `immutable` cache coexist. Three tiers, three gates, three revocation granularities. §5. |
| 4 | Real egress cost | **Retired, and the vision's framing is wrong.** ~37 GB egress per delivered wedding gallery — about *one* copy of the originals, of which the eighty guests are 9%. Egress costs €0.00–€3.19 per gallery. **Storage growth is the cost that compounds**, at 3–4 TB/year forever, and by year five it breaches the vision's own §10 cost criterion on every provider. |

**Backlog verdict: go with changes.** P3 is buildable in sprints 8–11, but only after four stories are corrected (G-01 mis-ordered, G-03 under-specified, G-08 factually wrong about HTML, G-11 carries a correctness bug), one story is added, one is moved to P4, and one half of the added story is recognised as a P2 dependency. Details in §7; sprint split in §8; the five questions that stayed open in §9.

---

## 1. Method

### What was measured

Everything ran on an **Apple M3 Pro (12 cores, 19 GB), macOS 27.0, Docker Desktop 29.5.2**, inside the Docker Linux VM, which exposes **6 vCPU and 8 GB RAM** to containers. Two throwaway images. No cloud infrastructure, no spend.

| Probe | Image | Script |
|---|---|---|
| libvips derivative throughput, per-format encode cost, peak RSS, output sizes | `debian:bookworm-slim` + `libvips-tools` (**libvips 8.14.1**, libheif with AV1) | [`spikes/vips-throughput.sh`](../../../spikes/vips-throughput.sh) |
| Store-mode zip primitives (CRC-32, stream copy, SHA-256, deflate), peak RSS | `php:8.4-cli` (**PHP 8.4.24**) | [`spikes/zip-stream.php`](../../../spikes/zip-stream.php) |
| Gallery grid HTML weight and compressibility | `debian:bookworm-slim` + `gzip`, `brotli` | ad hoc; generator reproduced in §6.3 |

Both scripts are runnable and left in `spikes/`. Neither has a framework, a fixture, or any dependency on `apps/studio`.

**Source images.** Three real photographs (Unsplash, 3000 px) upscaled to 6000×4000 (24 MP) and 8215×5476 (45 MP) with gaussian noise (σ=6) added back, so high-frequency energy — and therefore decode cost and file size — lands in camera-realistic range. The resulting originals are **7.7–8.3 MB at 24 MP** and **14.3–15.2 MB at 45 MP**, matching a fine-JPEG export from a full-frame body. Derivative *byte sizes* were measured separately from the **unmodified 3000 px photographs**, because measuring them off an upscaled source would have flattered every number.

### What was estimated, and from where

- **Per-core derating.** An M3 Pro performance core beats a commodity cloud vCPU. Every CPU figure is quoted as measured and then derated **1.5×** for a generic EU cloud vCPU. That factor is judgement, not measurement, and is flagged at each use.
- **Provider pricing and platform limits.** Vendor pricing pages, price-list APIs and product docs, all fetched 2026-08-09, cited inline. *Estimated* — no billed workload was run.
- **Everything that crosses a network.** No S3-compatible endpoint was exercised. Every wall-clock figure involving a network is arithmetic over a stated link speed and is labelled as such.
- **Derivative sizes carry a 1.5× planning margin** over measurement, because the Unsplash sources are already CDN-recompressed and a straight RAW export retains more detail. Both figures are given.

### What could not be measured here

Real multipart behaviour under a 30 GB streamed upload; real CDN cache-hit ratios; real guest browsing behaviour; the studio's actual upstream bandwidth; ZIP64 support in the consumer clients that matter. Each is a named open question in §9 with the experiment that closes it. None is presented as a number.

---

## 2. Unknown 1 — libvips throughput

### Measurement

Per image, one worker pinned to `VIPS_CONCURRENCY=1` (production workers are queue processes, not threads), 24 MP source:

| Derivative set | CPU-s / image | At 6 workers |
|---|---|---|
| 3× JPEG (400 / 1600 / 2560) — the vision's §6 ladder | 0.68 | 6.8–7.5 img/s |
| 3× WebP | 1.00 | 5.4 img/s |
| 1× AVIF 1600, `effort=4` | 0.75 | 3.4 img/s — *does not scale* |
| 32 px source for blurhash | 0.10 | 56 img/s |
| SHA-256 of the original | 0.018 | 260 img/s |
| **Full ladder, naive** — 4 widths × (AVIF `effort=2`, WebP) | **2.48** | 2.4 img/s |
| **Full ladder, chained** — one decode at 2560, then walk down | **1.38** | 4.3 img/s |

At 45 MP the same work costs **1.56×** more (3× JPEG: 1.06 CPU-s against 0.68).

Scaling from one to six workers is ~85% efficient for JPEG and WebP. **AVIF plateaus at ~3.4 img/s regardless of worker count**, because the AV1 encoder spawns its own threads and ignores `VIPS_CONCURRENCY`. Worker count and encoder threading must be reconciled explicitly or the box oversubscribes itself.

**Peak RSS, measured:** 39.7 MB (24 MP → 400 JPEG), 61.8 MB (45 MP → 2560 JPEG), 107.0 MB (45 MP → 1600 AVIF). libvips is flat-memory as the vision claims; four workers fit inside 512 MB.

### Three findings that rewrite G-03

**a. The single-decode chain is worth 1.8×, measured.** Calling `vipsthumbnail` once per output decodes the original eight times. Decoding once at 2560 px and walking down (2560 → 1600 → 800 → 400, encoding at each stop) costs 1.38 CPU-s against 2.48, with outputs identical to within 2%:

```
        avif naive / chained     webp naive / chained
 400      5,687 /  5,663          10,794 / 10,716
 800     13,270 / 13,048          22,666 / 22,614
1600     31,598 / 31,284          55,880 / 57,678
2560     60,383 / 60,383         149,170 / 149,170
```

This is an instruction for the ingest job, not an optimisation to revisit. It is a different loop shape, and retrofitting it means rewriting the job.

**b. AVIF `effort` is the largest single cost lever, and the libvips default is wrong here.** At 2560 px, `effort=4` costs 1.362 CPU-s against 0.483 at `effort=2` — **2.8×** — for a size difference in the low single-digit percent. Pin `effort=2`. Full table (CPU-s, effort 4 / effort 2): 400 px 0.195 / 0.122 · 800 px 0.406 / 0.190 · 1600 px 0.753 / 0.344 · 2560 px 1.362 / 0.483.

**c. The vision's 400 / 1600 / 2560 ladder has a hole at 2× DPR.** The clickdummy's own grid (`mockups/gallery/09-raster.html`) requests tiles at `w=600`/`w=700` for a cell that renders near 400 CSS px — it is already hand-compensating for retina. 400 px is the 1× tile; the next rung is 1600, four times larger than needed. **Add an 800 px rung.** Measured 800 px sizes, real photographic detail, mean of three: AVIF Q60 **24.7 KB**, AVIF Q50 16.7 KB, WebP Q80 35.7 KB, JPEG Q82 53.6 KB. Serving a 1600 px tile where an 800 belongs is a 2.3× egress multiplier on the single most-requested object in the system.

### What same-day turnaround actually costs

Per wedding, on the vision's own volumetrics (§1: ~30 GB, 3–4 TB/year over ~100 weddings → **3,000 assets at ~10 MB**):

| | Measured (M3 Pro) | Derated 1.5× (*estimated*, generic cloud vCPU) |
|---|---|---|
| CPU per asset — chained ladder + SHA-256 + blurhash | 1.42 CPU-s | 2.13 CPU-s |
| CPU per wedding (3,000 assets) | 1.18 CPU-h | 1.78 CPU-h |
| Wall-clock, 4 workers | 18 min | **27 min** |
| Wall-clock, 2 workers | 35 min | 53 min |
| Whole year — 100 weddings + 200 portraits ≈ 360k assets | 142 CPU-h | **213 CPU-h** |

213 CPU-hours a year is a **0.6% duty cycle on one 4-vCPU node**. The derivative pipeline is not a scaling problem. It is a rounding error that happens to be spiky.

Now set it against ingest — 30 GB uploaded *from* the studio (*arithmetic over stated link speeds*):

| Studio upstream | Upload 30 GB |
|---|---|
| 10 Mbit/s (VDSL 50/10) | **6.8 h** |
| 40 Mbit/s (cable/VDSL 250/40) | 1.7 h |
| 100 Mbit/s (fibre) | 41 min |
| 1 Gbit/s | 4 min |

**Decision.** Four derivative workers, `VIPS_CONCURRENCY=1`, AVIF `effort=2`, single-decode chain, 512 MB reserved. That keeps derivatives ahead of the upload on any link above 100 Mbit/s and finishing within the hour on any link above 40. Autoscaling the derivative tier is **explicitly not needed and should not be built**; the ingest queue's own backlog is the only signal worth alerting on.

**The unknown that replaced it** is the studio's actual upstream. It is the entire difference between "the client gets the gallery today" and "tomorrow", and it is not a software problem. §9.

---

## 3. Unknown 2 — zip streaming at 30 GB

A ZIP of JPEGs is written with compression method 0 (store), because JPEG is already entropy-coded. The only per-byte work is the CRC-32 that every local file header requires, plus the copy itself. Measured over 1 GB of incompressible random data at 8 MiB chunks, PHP 8.4.24:

| Operation | Throughput | Per 30 GB |
|---|---|---|
| read only | 1357 MB/s | 0.4 min |
| read + incremental `crc32b` | 327 MB/s | 1.6 min |
| **read + `crc32b` + write — store-mode zip** | **340 MB/s** | **1.5 min** |
| read + `sha256` + write — ingest verification | 147 MB/s | 3.5 min |
| deflate level 1 — *what not to do* | 66 MB/s | 7.7 min |

**Peak RSS 23.8 MB, peak PHP heap 18.1 MB** against a 256 MB `memory_limit`, streaming 1 GB. Memory is O(chunk), not O(archive): the vision's "memory stays flat regardless of gallery size" is confirmed, and the number is 24 MB.

Three consequences.

**The build is network-bound by an order of magnitude.** 340 MB/s of CPU against a storage link that will realistically deliver 50–200 MB/s. A 30 GB build reads 30 GB and writes 30 GB; at 100 MB/s each way on a full-duplex link that is ~10 minutes, at 25 MB/s ~40 minutes (*arithmetic*). CPU contributes 1.5 minutes. Do not spend a sprint optimising the archiver.

**Never compress.** Deflate level 1 is 5× slower and saves essentially nothing on JPEG. Store mode, set explicitly, asserted in a test.

**Timeouts are the real failure mode, and there are four of them.** A 10–40 minute job trips every default in the stack:

| Layer | Default | Required |
|---|---|---|
| Laravel queue job `$timeout` | 60 s | ≥ 3600 s on `media.zip` |
| Queue `retry_after` | 90 s | must exceed the job timeout, or the job is retried while still running and the archive is built twice |
| Storage client request timeout | 60–120 s | applies **per part**, not per upload — which is precisely what makes a 40-minute transfer tractable |
| PHP `max_execution_time` | 0 on CLI | already correct, but only because this is never an HTTP request |

That last row is the architectural constraint. **Zip assembly is a queued job on its own queue with its own worker class, never a request** — and it must not share a pool with `media.ingest`, because one 40-minute archive would sit in front of 3,000 derivative jobs.

### 3.1 The split threshold

Two independent forces. The tighter one wins, and it is not the one the backlog assumes.

**Transfer time** is survivable. Germany's median fixed-broadband download is **102.03 Mbit/s** (Ookla Speedtest Global Index, data collected December 2025 — *secondary source, [worldpopulationreview](https://worldpopulationreview.com/country-rankings/internet-speeds-by-country); Ookla's own page blocks automated fetch, so verify before quoting externally*). The Bundesnetzagentur's tenth Breitbandmessung report (period to 2025-09-30, published 2026-06-18) publishes contract-fulfilment rather than an absolute median — 85.9% of users reach at least half their contracted rate, 45.9% reach the full rate ([BNetzA](https://www.bundesnetzagentur.de/SharedDocs/Pressemitteilungen/DE/2026/20260618_Breitbandmessung.html)) — which argues for a ~30% haircut. At 8.9 MB/s effective, 30 GB is **56 minutes** and 5 GB is 9 minutes. Annoying; not disqualifying.

**Consumer unzip tooling is the constraint that binds**, and it is not ours to fix. An archive that stays under 4 GiB needs no ZIP64 extension at all, because the classic end-of-central-directory record's 32-bit size and offset fields still fit. Above that, the archive is ZIP64 — and ZIP64 read support in the clients wedding clients actually use is **undocumented by every vendor that matters**:

| Client | ZIP64 > 4 GiB | Source |
|---|---|---|
| Windows 11 File Explorer | **No vendor statement exists.** Microsoft's page documents formats but no size limit; the 2023 libarchive work added tar/7z/rar reading and did not replace the legacy `.zip` path | [support.microsoft.com](https://support.microsoft.com/en-us/windows/zip-and-unzip-files-8d28fa72-f2f9-712f-67df-f80cf89fd4e5) |
| macOS Archive Utility / Finder | **No Apple statement exists.** (`/usr/bin/unzip` is Info-ZIP 6.00, which *is* ZIP64-capable — but that is not the double-click path) | `man ditto`, `unzip -v` |
| iOS / iPadOS Files | **No Apple statement exists** | — |
| Android Files by Google | Help page says only "Only .zip files are supported" | [support.google.com/files](https://support.google.com/files/answer/9048509) |
| 7-Zip | **Documented: "7-Zip supports the Zip64 extension of ZIP format."** | [7-zip docs](https://7-zip.opensource.jp/chm/general/formats.htm) |
| The Unarchiver | **Documented:** "Zip64 extensions for large files" | [XADMaster README](https://github.com/mikemcduffie/XADMaster/blob/master/README.md) |

Exactly two clients publish a guarantee, and neither is preinstalled anywhere. A 30 GB ZIP64 that silently refuses to open on a client's iPhone is a failure we cannot reproduce, cannot debug remotely, and will hear about as "the download is broken".

**Decision: split at 3.9 GiB (4,187,593,113 bytes), as a config value, not a constant.** Reasons, in order: it keeps every archive inside classic ZIP with no ZIP64 record, so every client opens it; a dropped connection costs five minutes rather than fifty-six even where range-resume fails; and it sits under every CDN's cacheable-object ceiling. A 12 GB full-resolution delivery is four parts; the 30 GB worst case is eight. A guest-tier web-size archive (§6) is ~300 MB and is never split at all.

Two implementation consequences that must be in G-10's acceptance line:

- **ZIP64 must be explicitly disabled**, not merely avoided by size. A streaming writer with unknown member sizes emits ZIP64 headers defensively. Member sizes are known from `AssetDerivative.bytes`, so pass them and turn the extension off.
- **Split by `GallerySection`, not by byte boundary.** `GallerySection` already exists (vision §3.4), and `Trauung.zip` is a filename a client understands where `teil-3-von-8.zip` is not. Sections exceeding the threshold split by index within the section.

### 3.2 The multipart question the split threshold retires

Streaming into object storage without touching local disk means multipart upload, which has hard limits:

| | Max parts | Min part | Max part | Max object |
|---|---|---|---|---|
| AWS S3 | 10,000 | 5 MiB (none on last) | 5 GiB | 48.8 TiB |
| **Cloudflare R2** | 10,000 | 5 MiB (except last) | 5 GiB | 5 TiB |
| Backblaze B2 (S3 API) | 10,000 | 5,000,000 B | 5 GiB | 10 TB |
| Hetzner Object Storage | 10,000 | — | 5 GB | 5 TB |

Sources: [S3 quick facts](https://docs.aws.amazon.com/AmazonS3/latest/userguide/qfacts.html), [R2 multipart](https://developers.cloudflare.com/r2/objects/multipart-objects/), [B2 create-multipart](https://www.backblaze.com/apidocs/s3-create-multipart-upload), [Hetzner overview](https://docs.hetzner.com/storage/object-storage/overview).

R2 adds a constraint the others do not: **"All parts except the last must be the same size"** ([R2 docs](https://developers.cloudflare.com/r2/objects/multipart-objects/)), enforced at `CompleteMultipartUpload` rather than at each part. A fixed part size satisfies it for free — but a naive "one part per source object" implementation, which is the obvious way to write this, would fail on R2 and pass everywhere else. That is exactly the bug that ships.

**Use a fixed 16 MiB part size.** A 3.9 GiB archive is 250 parts; the 10,000-part ceiling is 156 GiB, so it is not reachable. The buffer is 16 MiB, consistent with the 24 MB RSS measured above. R2's equal-size rule is satisfied by construction, and the final short part is legal on all four providers.

Having chosen a 3.9 GiB split, every multipart limit above has three orders of magnitude of headroom. The split threshold retires this question rather than merely surviving it.

---

## 4. Unknown 3 — signed URLs versus CDN caching

### The tension, stated precisely

G-08 wants "CDN cache per `gallery_version`". G-06 wants three independently rotatable gates. These are held to be in conflict because a per-request signature is a unique cache key, so every guest misses cache; and an immutable long-lived key cannot be revoked.

Both halves are true only under one assumption: **that the signature is part of the cache key.** It does not have to be.

> "By default, query strings are ignored when constructing the cache key."
> — [bunny.net, Vary Cache](https://bunny.net/docs/cdn/vary-cache)

That is the whole resolution, and bunny states the design intent explicitly: "The original system disabled vary cache based on the Query Parameters because the tokens would change with each request and cause nothing to get cached… Token Authentication V2 solved this by including the query parameters into the token as well" ([bunny blog](https://bunny.net/blog/were-bringing-token-authentication-to-the-next-level/)). The pull-zone field is `IgnoreQueryStrings`, and it defaults to true; query-string vary is opt-in.

CloudFront has the same property by a different route — "Other values from the viewer request are not included in the cache key, by default" ([AWS](https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/understanding-the-cache-key.html)) — but CloudFront is not in this stack for the reasons in §6. Cloudflare below Enterprise has only blunt all-or-nothing query-string ignoring, and its HMAC edge function `is_timed_hmac_valid_v0()` requires **Pro or above** ([Cloudflare](https://developers.cloudflare.com/waf/custom-rules/use-cases/configure-token-authentication/)); surgical "exclude just the token from the cache key" is Enterprise-only ([cache keys](https://developers.cloudflare.com/cache/how-to/cache-keys/)).

### The mechanism

Three tiers, three different gates, three different revocation granularities. Writing them as one mechanism is what created the apparent conflict.

```
TIER 1 — gallery HTML (password page, grid, lightbox shell)
  path    /g/{gallery_public_id}/                     password page
          /g/{gallery_version_public_id}/             grid, after the gate
  gate    origin-side. Astro SSR reads the signed gallery-session cookie and
          validates it against the api surface. No cookie -> 302 to the
          password page.
  cache   Cache-Control: private, no-store. Pull-zone rule: do not cache this
          prefix. The edge cannot validate a session cookie, so the edge does
          not get to answer.
  cost    one SSR render per guest per gallery version. Measured page weight
          for 1,200 tiles: 500 KB of markup, 61 KB over the wire (§6.3).

TIER 2 — web-size derivatives (400/800/1600/2560, AVIF + WebP)
  path    /i/{gallery_version_public_id}/{asset_public_id}/{width}.{fmt}
  gate    bunny Advanced token, query-string mode (isDirectory=false), signed
          server-side at the moment the view password is accepted:
            token = "HS256-" + b64url(HMAC-SHA256(key,
                      signature_path + expires + signing_data))
          with token_path=/i/{gallery_version_public_id}/ so ONE token covers
          every image in the version, and expires = now + 7 d to match the
          vision's §4 guest-session TTL.
  cache   public, max-age=31536000, immutable. The token lives in the query
          string, which bunny excludes from the cache key by default, so one
          cached object serves all eighty guests. Signing and caching do not
          compete.
  header  origin sets CDN-Tag: gallery-{gallery_version_public_id}

TIER 3 — full-resolution originals and zip parts
  path    storage-native; never routed through the CDN at all
  gate    storage presigned GET, 24 h TTL, minted only after the PIN gate and
          the verified-email gate
  cache   no-store. Never edge-cached, never public, never reused.
  resume  presign directly to object storage so Accept-Ranges / 206 survives.
          Do NOT proxy through PHP: Storage::download() and StreamedResponse
          do not honour Range, which silently converts every resumed download
          into a restart.
```

### The three rotations, and what each one costs

| Gate rotated | Mechanism | Latency | Blast radius |
|---|---|---|---|
| **Republish** (new `GalleryVersion`) | new `gallery_version_public_id` → new path → new cache key | instant | none. Nothing is purged; the old objects simply age out. This is why versioning beats invalidation. |
| **View password** | stop minting tokens; outstanding tokens die at their own `expires` | **bounded by the token TTL** | that gallery only |
| **Download PIN** | tier 3 only — no new presigned URLs are minted | instant | that gallery only; viewers are untouched, which is exactly the vision's §5 requirement |
| **Incident** | `POST /pullzone/{id}/resetSecurityKey` | instant | **every gallery on the zone.** Nuclear, and correct for an incident. |
| **Single version, surgical** | tag purge on `CDN-Tag: gallery-{version}` ([purge docs](https://bunny.net/docs/cdn/purge-cache)) | seconds | that version's images |

**The cost, stated plainly: view-password revocation is eventually consistent within the token TTL.** A guest who already holds a token keeps viewing web-size images until it expires. That is an acceptable trade for a secret deliberately shared with eighty people, and the studio has `resetSecurityKey` when it is not. Shortening the TTL to one hour tightens revocation at the price of an hourly re-mint round trip; make it a per-gallery setting rather than a constant, defaulting to 7 d.

### Why the image path is a capability URL, and the conditions that makes acceptable

Tier 2's path is unguessable and its object is publicly cacheable — a capability URL by the W3C TAG's definition ([Good Practices for Capability URLs](https://w3ctag.github.io/capability-urls/); note this is a 2014 First Public Working Draft that never advanced, so it is considered advice, not a standard). The vision already commits to this in §6 and is right to, but the TAG's conditions have to be met rather than assumed:

- **Entropy ≥ 120 bits.** A CUID2 `public_id` is 24 base36 characters ≈ 124 bits. It clears the bar. Nothing else in the system may.
- **`Referrer-Policy: no-referrer`** on gallery documents. The modern browser default of `strict-origin-when-cross-origin` already prevents path leakage, but this is one header.
- **No third-party scripts on any gallery page.** The TAG is blunt: scripts can always read `location`. The vision's "functional cookies only, no third-party analytics" (§8) already forbids this — the reason is now recorded.
- **`robots.txt` disallows the path prefix**, never individual URLs.
- **Expired or unknown → `404`**, matching the vision's §5 uniform-failure requirement.

OWASP's position is stricter than what we are doing: ASVS 5.0 §14.2.1 requires that "the URL and query string do not contain sensitive information". A capability URL is a deliberate, compensated exception, not a compliant design. Say that in review rather than arguing the letter — the compensations are the five bullets above plus the fact that tier 2 is web-size only and tier 3 never uses this pattern at all.

### Two traps that would silently destroy this

- **Bunny Optimizer turns on URL-query-string vary for images automatically** ([vary cache docs](https://bunny.net/docs/cdn/vary-cache)). Enabling it would shatter the cache into one entry per token and take the hit ratio to zero without any error. It must stay off, and that must be a documented deploy check, not a memory.
- **Enabling token authentication disables IPv6 zone-wide** ([token auth docs](https://bunny.net/docs/cdn/security/token-authentication/index)). German mobile carriers are predominantly dual-stack, so this is probably harmless — but "probably" is doing work here, and it is listed as an open question in §9.

### What this changes in G-08

G-08 as written — "CDN cache per `gallery_version`" — is **correct for images and wrong for HTML**. The HTML sits behind a session cookie the edge cannot validate, so it must be `no-store`. And "purged on publish" (vision §6) is unnecessary work: publishing mints a new `gallery_version_public_id`, so the path changes and there is nothing to purge. Version, don't invalidate.

---

## 5. Unknown 4 — real egress cost

### 5.1 The traffic model

Per delivered wedding gallery, on the vision's volumetrics: **3,000 assets ingested (30 GB originals), 1,200 delivered in the published version, one couple plus eighty guests.** Derivative sizes are measured (real photographic detail) with a 1.5× planning margin applied.

| Flow | Volume | Bytes |
|---|---|---|
| Guest browsing — 70% of grid at 800 px AVIF (24.7 KB measured → 37 KB planning) + 30 lightbox opens at 1600 px AVIF (39.2 → 59 KB) | 80 guests × ~40 MB | **3.2 GB** |
| Couple browsing — full grid, ~4 visits, 300 lightbox opens | 1 | 0.2 GB |
| Guest-tier zip, web size (1600 JPEG, 164 KB measured → 246 KB planning; ~295 MB per archive) | 15 of 80 download | **4.4 GB** |
| Couple full-resolution zip — 1,200 originals ≈ 12 GB, two devices, ×1.25 for one repeated or resumed attempt | 1 | **30 GB** |
| | **total** | **≈ 37.8 GB** |

**This contradicts the vision's §6 claim** that "one wedding gallery shared with eighty guests can move more bytes in a weekend than its originals occupy". The total is about right — 37.8 GB against 30 GB stored, so 1.25× — but the *cause* is wrong, and the cause is what determines the mitigation. **The eighty guests are 9% of it (3.2 GB).** Ninety percent is the couple downloading their own archive. Capping the guest tier does nothing; the only lever that moves this number is the retrieval budget on the couple's own download link.

### 5.2 Cost per gallery, four providers

All pricing fetched 2026-08-09. *Estimated — no billed workload was run.*

| Stack | Storage, 30 GB · 1 yr | Egress, 37.8 GB | Operations | **Year 1** |
|---|---|---|---|---|
| **Backblaze B2 EU Central + bunny.net** | $2.50 (`$0.00695/GB-mo`) | B2 → bunny **free**; bunny $0.38 (`$0.01/GB`) | free (B2 Class A/B/C free) | **$2.88** |
| Hetzner Object Storage + bunny.net | €2.25 (`€0.00626/GB-mo`) | Hetzner → edge ≈ €0.01; bunny $0.38 | free | **≈ €2.60** |
| Cloudflare R2 (EU jurisdiction) + Cloudflare CDN | $5.40 (`$0.015/GB-mo`) | **$0.00** | $0.14 (30k Class A @ `$4.50/M`) | **$5.54** |
| AWS S3 eu-central-1 + CloudFront | $8.82 (`$0.0245/GB-mo`) | $3.19 (`$0.085/GB`) | $0.16 | **$12.17** |

Sources: [B2 pricing](https://www.backblaze.com/cloud-storage/pricing) (incl. "unlimited free egress when downloading to or through partner content delivery networks… including Fastly, Cloudflare, bunny.net"), [bunny pricing](https://bunny.net/pricing/), [Hetzner Object Storage](https://www.hetzner.com/storage/object-storage/), [R2 pricing](https://developers.cloudflare.com/r2/pricing/), [AWS S3 price list eu-central-1](https://pricing.us-east-1.amazonaws.com/offers/v1.0/aws/AmazonS3/current/eu-central-1/index.json), [CloudFront pay-as-you-go](https://aws.amazon.com/cloudfront/pricing/pay-as-you-go/).

Egress is **between zero and three euros nineteen per delivered wedding**. It is not a risk. It was never going to be a risk once the tiering in §4 keeps guests on web size, and it is bounded further by G-11's retrieval cap.

### 5.3 The cost that actually compounds

At 100 weddings and 200 portraits a year the studio adds 3–4 TB of originals annually, forever. Egress stays flat at ~4.75 TB/year. Storage does not.

| Stack | Year 1 (avg 1.75 TB) | Year 5 (avg 15.75 TB) | Egress/yr | **Year 5 total** |
|---|---|---|---|---|
| B2 EU + bunny | $146 | $1,313 | $48 | **$1,361** |
| Hetzner + bunny | €131 | €1,183 | ≈ €50 | **≈ €1,233** |
| R2 + Cloudflare | $315 | $2,835 | $0 | **$2,835** |
| AWS S3 + CloudFront | $514 | $4,631 | $404 | **$5,035** |

**Vision §10 sets "total infrastructure and tooling below the incumbent subscription at 100 weddings per year" as a success criterion.** A Pixieset/Studio Ninja-class subscription is roughly €480–1,080 a year. On the cheapest provider the storage bill alone crosses the bottom of that band in **year 3** and the top of it in **year 5** — before compute, mail, Sentry, or Stripe fees, and before the vision's §8 cross-account replication of `originals/`, which **doubles the storage line** ($2,626/yr at year 5 on B2).

The lever is not provider choice; the spread between the cheapest and second-cheapest is under 10%. **The lever is a retention policy for originals, and the vision does not have one.** §6 describes lifecycle *tiering* (hot 90 d → infrequent access → archive at delivery + 18 mo), which turns out to buy nothing here: B2's flat $0.00695/GB and Hetzner's €0.00626/GB are both **below R2's Infrequent Access tier at $0.01/GB plus $0.01/GB retrieval**. On a flat-rate provider there is no cold tier to move to. Tiering is complexity that saves zero.

**Decision: Backblaze B2 EU Central (Amsterdam) for objects, bunny.net for delivery.** Reasons: cheapest of the two providers that have a proven track record at this size; B2→bunny egress is free and uncapped by documented partnership, which bypasses B2's 3×-average-storage free-egress ceiling entirely; bunny is an EU company with the token mechanism §4 requires and no plan tier gating it; and B2's flat rate deletes the tiering story.

Two caveats stated rather than buried. **B2 region selection is account-scoped and permanent** — "You can assign only one region to a Backblaze account" and it cannot be changed after creation ([B2 data regions](https://www.backblaze.com/docs/cloud-storage-data-regions)) — so the EU account must be created correctly the first time, and that is a provisioning checklist item, not a code concern. And **Backblaze is a US company with an EU data region**, so DSGVO rests on a DPA plus standard contractual clauses. If the studio's posture requires no US processor at all, **Hetzner Object Storage + bunny is the fallback**: ~10% cheaper, German, no third-country transfer question — at the price of a newer product with a documented 100 TB and 10 Gbit/s per-bucket ceiling and no operational track record at this workload. Re-evaluate at year 3, when the storage line starts to matter more than the operational risk.

### 5.4 Grid page weight, since it was cheap to settle

The clickdummy explicitly left this open (§5: "Scroll performance, virtualization, and pagination remain P3 questions"). Generated markup, real CUID2 entropy, one unique blurhash string per tile, full `<picture>` with a 3-entry AVIF `srcset`:

| Tiles | Raw | gzip -9 | brotli -5 | brotli -11 |
|---|---|---|---|---|
| 300 | 125 KB | 19 KB | 16 KB | 15 KB |
| 600 | 250 KB | 39 KB | 32 KB | 30 KB |
| 1,200 | 500 KB | 77 KB | 64 KB | 61 KB |
| 1,500 | 625 KB | 97 KB | 80 KB | 76 KB |

**Transfer is a non-issue: 76 KB brotli for the vision's worst case.** The cost is DOM size — 1,500 `<picture>` elements is ~6,000 nodes of parse, style and layout, which is where a mid-range Android on venue wifi will actually spend its time. The fix is native and one line: `content-visibility: auto` with a `contain-intrinsic-size` on each `.sec` section wrapper, which the mockup's section structure already provides. Virtualization, pagination and infinite scroll are not needed and should not be built until a measurement says otherwise. G-08's acceptance line should name the CSS property and the k6 target from vision §8 rather than leaving "grid" open.

---

## 6. What the spike changes in the P3 backlog

| Story | Finding | Change |
|---|---|---|
| **G-01** `api` surface + Sanctum + `packages/contracts` | Mis-ordered. Its only consumers are the gates (G-06) and the Astro gallery (G-08). Ingest (G-02) is an *admin*-surface flow. Shipping it in sprint 8 means two sprints of a generated OpenAPI client that nothing typechecks against — which is exactly the drift window §2 of the vision claims to have closed. | **Move to sprint 9**, immediately ahead of G-05/G-06. Size unchanged (L). |
| **G-03** ingest queue + derivatives | Under-specified in three ways the measurements settle: the ladder needs an **800 px rung** for 2× DPR; derivatives must come from a **single decode chained downward** (1.8× measured); AVIF must pin **`effort=2`** (2.8× measured). | Size unchanged (L). Acceptance line must name all three, plus `VIPS_CONCURRENCY=1` with 4 workers. |
| **G-08** Astro gallery, "CDN cache per `gallery_version`" | Factually wrong for the HTML: it sits behind a session cookie the edge cannot validate, so it must be `no-store`. What caches per version is the *images*, and their key is already version-scoped by path. "Purged on publish" is unnecessary — publishing mints a new version id, so there is nothing to purge. | Reword to "images cached immutably under a version-scoped path; HTML `no-store`; publishing versions rather than invalidates". Add `content-visibility: auto` and the k6 p95 target. Size unchanged (L). |
| **G-10** `ZipBuild`, "split above threshold" | Threshold now known: **3.9 GiB**, chosen by consumer ZIP64 support, not transfer time. Also needs: store mode explicit, ZIP64 explicitly *disabled*, **fixed 16 MiB multipart parts** (R2 requires equal part sizes and enforces it only at completion — the naive one-part-per-file implementation passes everywhere except R2), split by `GallerySection`, dedicated queue with a ≥3600 s timeout and a larger `retry_after`. | Size unchanged (L); acceptance line rewritten. |
| **G-11** `DownloadEvent` + "signed 24 h links with bounded retrievals" | **Correctness bug.** A 12–30 GB download is resumed via HTTP Range, and a resumed transfer is a *second request*. Counting retrievals per request means a browser that reconnects twice burns the whole budget — the exact failure the vision's §5 bounded-retrieval rule exists to prevent, reintroduced by the counter. Mockup 12-email promises "Bricht der Download ab, starten Sie ihn einfach neu", which the naive counter breaks. | Count **distinct requester-days**, not HTTP requests; range continuations of an in-flight transfer do not decrement. Also: presign direct to storage — `Storage::download()` and `StreamedResponse` drop `Accept-Ranges`, silently turning every resume into a restart. Size unchanged (M). |
| **G-09** lightbox with comments rail | Forward dependency on P4. Vision §3.4 says `AssetComment` "emits Engagement events so it joins the same unread stream" — that stream is E-06/E-07, in P4. Building it in P3 means building it twice. | **Cut the comments rail.** G-09 becomes lightbox + favorite toggle (M). |
| **G-12** favorites, comments, Lightroom export | Same dependency, and P3 does not fit four sprints with it (see §8). | **Move to P4, sprint 12**, alongside E-06/E-07. Lightroom selection export moves with it; it reads favorites. |
| **NEW — G-01a Media infrastructure** (M) | **The infrastructure P3 assumes does not exist.** `docker-compose.yml` has `php`, `postgres`, `mailpit` — no Redis, no object store, no worker service. `apps/studio/composer.json` has no Horizon, no Sanctum, no S3 filesystem driver. The queue runs on the database driver. There is nowhere for G-02 or G-03 to land. | Add as the **first** P3 story: S3 driver, MinIO in compose, `media.ingest` and `media.zip` queues with per-queue timeouts, worker container in compose and in the deploy. |
| **…and half of it belongs to P2** | Redis, Horizon and the worker container are **already P2 dependencies** — M-01 renders PDFs through Gotenberg and M-04/M-05 process Stripe webhooks, neither of which should run in a request. P3 inheriting them is P3 paying P2's bill. | Move the queue-runtime half into **P2 sprint 7**. G-01a keeps only the object-storage half. |

Two things the spike **confirms unchanged**: G-06's three independently rotatable gates survive intact — but only because §4 puts them at three different layers (origin session / edge token TTL / storage presign), and G-06's acceptance line must say so or an implementer will collapse them into one. And G-02's presigned multipart ingest is correctly sized and correctly placed; the 30 GB upload is the single longest operation in the phase and it belongs first.

---

## 7. Verdict

**Go with changes.** Nothing in the four unknowns threatens P3. The pipeline is 10× cheaper than the vision budgeted for, zip assembly is a solved streaming problem with a flat 24 MB memory ceiling, the signed-URL tension dissolves the moment the CDN is chosen for its cache-key semantics rather than its brand, and egress is between zero and three euros per wedding.

The three things that did move are worth the spike on their own:

1. **The bottleneck is the studio's upstream link, not the queue.** Every hour of P3 spent on derivative throughput is an hour spent on the wrong problem.
2. **Storage, not egress, is the cost that compounds** — and it breaches the vision's own §10 criterion around year 3 unless somebody writes a retention policy for originals. That is a business decision, and it is now on the table before the code exists rather than after 15 TB does.
3. **P3 as written does not fit sprints 8–11.** Six L, five M and one S is roughly 43 working days against roughly 40 available, in a side-build, with a missing infrastructure story underneath it. It fits only with G-12 moved to P4 and the comments rail cut from G-09.

---

## 8. Proposed sprint split, P3 across sprints 8–11

### The dependency that gates everything

**None of the schema P3 builds on exists.** `apps/studio/database/migrations` currently holds four files: `cache`, `jobs`, `staff_users`, `audit_logs`. P1a shipped foundation and staff auth and nothing else.

| P3 needs | Comes from | Used by |
|---|---|---|
| `projects` | **P1b C-02** | `Gallery.project_id` |
| `project_contacts` | **P1b C-02** | who may reach a gallery from the portal |
| `shoots` | **P1b C-03** | `Asset.shoot_id` — the vision's §3.4 primary association |
| `contacts` | **P1b C-01** | `GuestIdentity` dedup, `DownloadEvent` attribution |
| `jobs` | **P1b P-05** | `requires_settled_balance` resolves against the job's payment plan |
| `payment_installments`, `InstallmentPaid` | **P2 M-03, M-05** | the optional gallery payment gate |
| `portal` surface, `ClientAccess` | **P2 A-01, A-02** | the couple reaches their gallery from the portal, not from a guest link |
| Redis, Horizon, worker container | **P2** (see §6) — needed there for Gotenberg and Stripe webhooks | every queue in P3 |

**P3 cannot start before P1b C-02 and C-03 are merged.** That is a hard gate: `Asset.shoot_id` is a foreign key to a table that does not exist, and shoot-scoping is not something to retrofit — the vision's §3.4 turns "every frame from the reception" into a query over a shoot, and a nullable placeholder column would quietly make that query wrong.

**P3 can complete without P2's payment schema, degraded.** `Gallery.requires_settled_balance` ships as a column defaulting false with an `InstallmentPaid` listener that is inert until P2 delivers the event. If P2 slips, P3 still ships; galleries simply cannot be payment-gated. State that as an accepted degradation rather than discovering it in sprint 11.

### The split

| Sprint | Stories | Entry gate | Milestone |
|---|---|---|---|
| **8 — Ingest** | **G-01a** (new, M — object-storage half only), **G-02** (L), **G-03** (L, rewritten per §2) | `projects`, `shoots`, `project_contacts` merged (P1b C-02, C-03). Redis/Horizon/worker already shipped in P2 sprint 7. | 30 GB uploads from the admin browser to a quarantine prefix; derivatives, EXIF, hashes and blurhashes land within the hour |
| **9 — Publishing** | **G-01** (L, moved from 8), **G-05** (L), **G-04** (S) | sprint 8 green | a gallery version is published and immutable; failed assets are visible and re-runnable |
| **10 — Gates and grid** | **G-08** (L, corrected per §4/§5.4), **G-06** (L), **G-07** (M) | sprint 9 green; B2 EU account provisioned, bunny pull zone configured, both DPAs signed — this is a lead-time item, start it in sprint 8 | a guest enters a password and browses 1,200 images from the edge, p95 under 400 ms |
| **11 — Delivery** | **G-10** (L, split at 3.9 GiB), **G-11** (M, retrieval counting fixed), **G-09** (M, no comments rail) | sprint 10 green | password → PIN → email → multi-part download, audited end to end |
| *deferred* | **G-12** + the comments rail cut from G-09 | P4 sprint 12, with E-06 / E-07 | proofing joins the unread stream it was always meant to |

Ordering inside sprint 10 matters: the Astro application must be scaffolded by G-08 before G-06 has a password screen to render. Ordering inside sprint 11 does not.

**The end-to-end Playwright journey the vision's §8 requires** — "gallery password → PIN → email → download", plus the negative test proving a guest cannot obtain full resolution — spans sprints 10 and 11 and belongs in sprint 11's Definition of Done, not sprint 10's. The negative test in particular can only be meaningful once tier 3 exists.

---

## 9. Open questions

Five things this spike could not close. Each names the experiment that would.

1. **The studio's actual upstream bandwidth.** §2 shows this is the only thing standing between same-day and next-day delivery, and it varies by a factor of forty across plausible connections. *Experiment:* one `speedtest-cli --json` from the studio's edit workstation, plus one real 30 GB multipart upload to a trial B2 bucket, timed. Half a day, effectively free, and it should happen before sprint 8 because it determines whether G-02 needs a resumable-upload UI or merely a progress bar.

2. **ZIP64 support in the clients that matter.** §3.1 chose 3.9 GiB precisely *because* Microsoft, Apple and Google document nothing. That is a defensible choice under uncertainty, not knowledge. *Experiment:* build one 5 GB and one 3.9 GiB archive, open both on Windows 11 Explorer, macOS Finder, iOS Files, and Files by Google. Four devices, one afternoon. If Explorer and Finder both handle 5 GB, the threshold moves to 10 GB and a 30 GB delivery drops from eight parts to three — a real improvement in the client's experience, worth the afternoon.

3. **Real CDN cache-hit ratio for a gallery under publish-day load.** §4's whole economic argument rests on eighty guests sharing one cached object. The mechanism is documented; the hit ratio is not, and a cold edge on a Saturday evening in one PoP could look very different. *Experiment:* the k6 run vision §8 already requires (80 concurrent guests), pointed at a real bunny pull zone with a real gallery, reading bunny's cache-hit statistics. Belongs in sprint 10, not before.

4. **Whether disabling IPv6 zone-wide costs anything.** Enabling bunny token authentication turns IPv6 off for the entire pull zone. German fixed and mobile networks are predominantly dual-stack, so this is probably invisible — but "probably" is carrying weight, and an IPv6-only guest on a NAT64 carrier would see a gallery that simply does not load. *Experiment:* check whether a token-authenticated bunny zone is reachable from a NAT64 mobile network before sprint 10 closes. If it is not, the alternative is Cloudflare Pro with `is_timed_hmac_valid_v0()`, which costs a subscription and a blunter cache key.

5. **The retention policy for originals.** §5.3 shows the vision's own cost criterion fails around year 3 without one, and that no provider choice fixes it. This is not an experiment. It is a decision the studio has to make — how long originals are kept, whether that promise appears in the contract, and whether replication applies to all of them or only to the most recent N years. It should be settled before P3 ships, because it determines the storage prefix layout and lifecycle rules that G-01a creates, and moving 15 TB later is not free.

One question the clickdummy left open and this spike deliberately did not close: **whether three gates is one too many** (clickdummy §5, screen 11). That is a question for a real client on a real phone, and it survives this exercise exactly as the clickdummy said it would.
