# Interface review

What a full pass over the three surfaces turned up, written down as rules
rather than as a report. The screens it came from are the drafts in
`mockups/redesign`; the scripts that produced the evidence are in
`apps/studio/tests/e2e/shots`.

Read this before adding a screen, and before defending a visual decision.
The findings these rules came from are in [ux-audit.md](ux-audit.md).

## Method

**Look, do not argue.** Three disagreements about colour and type were
settled by rendering the same screen in every candidate and putting the
results side by side. `shots/grundton.mjs` does it for base tones,
`shots/schrift.mjs` for typeface pairings. Both take a minute to run and end
a discussion that prose cannot.

**Measure what is measurable.** Contrast ratios, horizontal overflow and tap
target size are numbers, not taste. `shots/mobile-audit.mjs` walks every
draft at 360 and 390 px and reports all three. It found a base-text grey at
2.83:1 and four screens scrolling sideways — none of which had been noticed
by eye across dozens of screenshots.

A measuring tool has to know its own exceptions or it stops being trusted:
the audit reported the filmstrip and the list selector as overflow until it
learned that a child of a horizontally scrollable element is meant to hang
over the edge.

**Compare screenshots byte for byte.** The setup wizard appeared to have
been captured across six steps. The PNGs for steps two through eight were
byte-identical: it had never advanced past step one, and validation errors
sat below the fold. A capture run that only checks "did the page change"
does not catch this; `shasum` does.

## Rules that came out of it

**A section exists when it has content.** An empty state is a different
screen, not the full screen with placeholders. The portal opens at the
inquiry, when there are two names and a preferred date and nothing else —
so that screen is a letter with a typographic head, not a dashboard with
five empty boxes. The head becomes the client's own photograph once a shoot
is delivered, which makes the header itself the progress indicator.

**Editability follows the phase.** While a booking is an inquiry, nothing is
reserved and nothing is signed, so the details belong to the client and they
change them themselves. From `booked` onward the date and the venue are
contract content and become read-only, with a way to ask instead of a field.
Contact data is never phase-bound; it is theirs throughout.

**No record is privileged.** A wedding, a maternity shoot and a company
party are separate orders with their own contract, payments and gallery. The
moment one booking type is treated as the main one, the component can no
longer represent the others — a location's own shoot for its website has no
couple, and it has to fit the same row. Year headings divide for finding.
They are not a ranking, and nothing is collapsed because it is old.

**Order is the argument.** Photograph before information, pictures before
appointments, paperwork last. The shipped portal has the reverse order and
therefore reads like a file. Fixing that costs no logic, only a different
sequence in the template.

**A form contains only fields you can operate.** A disabled row with an
explanation of why it is disabled is noise. The occasion of a booking is not
a field — a different occasion is a different booking — so it appears in the
read view and disappears when editing.

**Do not narrate the system.** "Christian gets your change as a message"
under a save button explains internal plumbing to someone who did not ask.
Notification is the system's job and is never announced. What survives is
the consequence of the user's own action stated before they act: "you sign
in with this address" belongs at the email field.

**`de.json` beats every class name.** `ZipBuild` produced a button reading
"ZIP erstellen", `download_pin_hash` produced "PIN" although the shipped
strings have said "Download-Code" for months. Model names walk to the
surface unless something stops them.

**Two voices for text, not three.** Near-black, mid-grey, light-grey is what
every generator emits, and the third step is usually unreadable — here it
carried the 11 px kickers at 2.83:1. Hierarchy in a design with a real type
scale is carried by size; a third grey is the third marking of the same
thing. Marks that are not text get their own value, but a status dot says
open or settled, so it still clears 3:1 — measured against the hover
background, not against white.

**Narrow screens get a different navigation, not a smaller one.** Four
header links do not fit 390 px. Shrinking them produced a page scrolling to
485 px and 22 px tap targets. Below 760 px the header keeps the wordmark and
a fixed bottom bar takes over.

**One definition per component.** Seven screens had grown seven headers:
four link sets, two badge markups, sticky on three of them, and a separate
component for the one over a cover image. Nobody decided that; it happens
because a per-page `<style>` makes divergence free. Over an image it is the
same bar with a modifier, never a second component.

## Traps that quietly destroy a layout

- **The `padding` shorthand overwrites `padding-inline`.** A section that
  sets `padding: 72px 0 48px` loses the page gutter, and the text sticks to
  the edge of the glass on a phone. Use `padding-block`.
- **Equal specificity, later source wins.** Every page's own `<style>` loads
  after the token file, so `.bar nav { display: none }` in a media query
  there never applied. Raising to `body .bar nav` fixed it. This is the most
  likely reason a "responsive rule that does nothing" is doing nothing.
- **Class-name collisions travel through layout properties.** A section
  named `.open` and an amount modifier `.amount.open` both matched
  `.open { order: -1 }` in the single-column breakpoint, which threw the
  amount above its own heading.
- **A popover inside its own trigger.** A `<button>` cannot contain a
  `<button>`; the inner one breaks out and the trigger inflates to wrap the
  menu. Menu and trigger are siblings inside a positioned wrapper.
- **`<input type="date">` follows the browser's language, not the page's.**
  A German interface shows `mm/dd/yyyy` to someone whose browser is English.
  This is the one place where a custom picker earns itself.

## On not looking generated

Three of my own choices were reasoned and still landed on the current
machine-generated default: warm off-white, Instrument Serif, and a
three-step grey ramp. The reasoning did not rescue any of them.

**A justification does not save a look that reads as generated.** Judge the
outcome, not the argument that produced it.

**Check the order of magnitude.** The warm background was defended with
simultaneous contrast — real, and True Tone does not cancel it, because it
shifts surface and photograph together. But three percent saturation sits
below the spread that ambient light, panel calibration and the viewer's
colour profile introduce anyway. A decision whose effect is smaller than the
noise of the medium is not a decision.

**The free font pool is the generator pool.** Every Google family is a
candidate for the same defaults, and today's discovery is next year's
cliché — Instrument Serif was a find two years ago. A display licence is the
cheapest identity money in the product and the one thing a competitor cannot
reproduce from a prompt. The wordmark in the header is already an image, so
a studio's own typeface lives there without a licence problem.

**Customisation does not solve sameness on its own.** If most studios keep
the default, everything still looks alike. A curated set of pairings, each
with its own metric corrections, does more than a free picker — and a free
picker has a licensing problem, because a studio's corporate typeface is a
desktop licence and serving it makes the product the distributor.

## What the pass found in the shipped product

Defects, with the measurement that produced them:

- The gallery view password protects nothing. With a password set and no
  session at all, `/api/galleries/{id}` answers 200 and the Astro grid
  serves all 33 tiles. `GalleryReadController` carries no gate and says so
  in its own docblock.
- `BuildGalleryZip::resolveMembers()` reads `$version->assets` as public-id
  strings while every read path treats the same column as objects. Against a
  real published version: `InvalidArgumentException: Nested arrays may not
  be passed to whereIn method`. No archive can be built.
- Contract PDF download answers 500 rather than 404 when the stored file is
  gone. Both the portal and the admin controller check `artifact_path` for
  null but never for existence.
- Fonts load from `fonts.googleapis.com` at runtime, so a couple's IP
  address reaches Google from the client portal and the guest gallery.
- Validation messages carry raw column names: "Das Feld bank account holder
  muss ausgefüllt werden."
- Delivery re-encodes the original on every request, and the derivative
  ladder built at ingest is unused by the delivery path — a deliberate
  choice with a `ponytail:` note, but its widths and the allowed widths no
  longer match.

Product gaps rather than drawing gaps: the portal has no account screen, no
way to fill in a questionnaire, and no entry point to the gallery at all.
