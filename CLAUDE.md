# CLAUDE.md

Rules for AI agents working in this repo. Violations are defects, same as a
failing test. Read [README.md](README.md) and [docs/architecture.md](docs/architecture.md)
for the stack; this file is only about how you work.

## Zero tolerance

These get the change rejected outright:

- **Explanatory prose nobody asked for.** No summaries of what you just did,
  no "here's how this works", no walkthroughs, no design notes, no option
  surveys. Code, then at most three lines: what you skipped, when to add it.
- **Comments that narrate the code.** `// increment counter`, `// loop over
  users`, `# call the service`, docblocks that restate the signature.
  A comment is allowed only for *why* — a non-obvious constraint, a workaround
  with a link, a `ponytail:` ceiling. If the code needs a comment to be
  understood, rewrite the code.
- **AI-slop copy in the UI.** Applies to `de.json`, to Blade and Vue, and
  to mockup text — a draft ships its wording into the product. These are
  strings with controls around them; for e-mail and other prose see the
  next rule, which is not this one. Labels stay short and native German
  (`Wunschtermin`, not `Bitte gib hier deinen gewünschten Termin ein`).
  One noun beats a sentence. No emoji, no exclamation marks. Generic
  masculine throughout (`Fotograf`, `Kunden`), never `:in` or `*in`.
  Five tells, each of which already shipped once:
  - *Reassurance.* "Speichern ist trotzdem möglich", "blockieren aber
    nichts", "bleibt danach frei änderbar" — calming the reader about a
    consequence nobody asked about.
  - *System narration.* "Christian bekommt eure Änderung als Nachricht."
    Notifying, syncing and storing are the system's job, never announced.
  - *Empty states as prose.* "Noch kein Angebot." An empty state is the
    controls that fill it. Write there only what no control can show.
  - *Restating the control.* Hint text repeating its own label, or
    explaining why a disabled field is disabled. A form holds only fields
    you can operate; the rest belongs in the read view.
  - *Rule of three.* "bei Anfragen, auf dem Board und bei Crew-Zuweisung"
    where one place suffices.
  Test before shipping a string: read its three neighbours in `de.json`.
  Longer than they are, or explaining rather than naming, means rewrite.
- **Prose copy that arrives without a UI.** E-mail, PDF body text, anything
  read where no control stands next to it. The label rule above does NOT
  reach here: an inbox has nothing around the words to explain the rest, so
  two terse lines are a config dump, not a message. Write sentences, then
  cut every one that fails this test: **does it say something the reader
  cannot see on their own screen?** Eight tells, all of them shipped in a
  single sitting:
  - *Feature tour.* "Anfragen landen auf einem Board und wandern nach
    rechts." He is looking at the board.
  - *Announcing what just happened.* A mail fired on wizard completion
    saying "Auri CRM ist eingerichtet". He pressed the button a second ago.
    Check the trigger before writing a word; some mails have nothing to say
    at their moment and should not exist.
  - *Never naming the product.* A welcome mail that does not write
    "Auri CRM" welcomes nobody.
  - *Naming something that does not exist.* "Anfrageformular einrichten" —
    there is an endpoint and an example snippet in `docs/`, no feature and
    no screen. A doc is not proof a feature shipped.
  - *A route nobody checked.* `/angebote/neu`. Grep `routes/` before a CTA
    ships; quotes hang off a project.
  - *Copy contradicting the code.* `StaffInvited` announces a 2FA step that
    `InvitationController` made opt-in.
  - *Lecturing.* Instruction, then the reason for it, then the consequence
    of skipping it, then a reward for compliance. A reminder is a nudge;
    an instruction the reader genuinely needs stands without its
    justification appended.
  - *Invented numbers.* "Acht Minuten, dann kann die erste Anfrage
    reinkommen." Nothing measured that. A figure in shipped copy has a
    source or it does not ship.
  The register is nudge and nurture: warm, unhurried, on the reader's side.
  Stripping a mail to one sentence and a button is the over-correction, not
  the fix. But warmth is a greeting or a good wish ("Schön, dass du da
  bist", "Viel Erfolg mit dem ersten Auftrag") — something about the sender
  or about what is ahead. It is NEVER a sentence that answers a worry the
  reader has not voiced. "Was du schon eingetragen hast, ist gespeichert",
  "lass dir Zeit, dein Studio läuft dir nicht weg", "wenn du soweit bist",
  "kannst du später ändern": each reaches for kindness and lands on
  reassurance, and naming the fear is what plants it. Position rescues
  nothing — a closing line is not harmless for sitting at the end. What
  earns an inbox is what a screen cannot show, said kindly.
- **Filler in messages, commits, PRs.** No "Certainly", "Great question",
  "I hope this helps", "Let me know if…", "In summary". No hedging on facts you
  can verify. State the thing.
- **Speculative code.** No abstractions with one implementation, no config
  for constants, no "for later" scaffolding, no feature flags nobody asked
  for, no defensive branches for states that cannot occur.
- **Reinventing what exists.** Before writing a helper, grep for it. Laravel,
  Vue, Tailwind, shadcn-vue, the domain `Actions/`, `Data/` DTOs and existing
  components come first. New dependency for what ten lines do = rejected.
- **Cosmetic churn.** No reformatting, renaming, reordering or "cleanup" of
  lines the task did not touch. Diff shows the change and nothing else.
- **Fake completion.** Never claim tests pass, a page renders or a command
  works without having run it in this session and seen the output. If a step
  was skipped, say so in one line.
- **Rule of three, em-dash cascades, negative parallelisms.** "Not X, but Y",
  "It's not just A — it's B", triplets for rhythm. Write plainly.

## How to work

- Understand before you edit: read the callers, trace the flow, then make the
  smallest change at the root. A tiny diff in the wrong place is a second bug.
- Fix root causes. A symptom report is not a spec.
- Shortest working diff. Fewest files. Deletion beats addition.
- Non-trivial logic ships with one test that fails if the logic breaks.
  Pest for PHP (`apps/studio/tests`), Vitest for TS. No fixture zoos.
- Answer the question that was asked. Do not widen or narrow the scope
  silently; if scope must change, say why in one line and continue.
- Prefer native platform features: DB constraint over app check, CSS over JS,
  `<input type="date">` over a picker lib.

## Project specifics

- Backlog lives in GitHub issues/project board, never in files.
- Studio tests need the admin surface: `-e APP_SURFACE=admin` on the container.
  Do not hardcode it in `phpunit.xml`.
- Domain-first layout under `apps/studio/app` (`Crm`, `Quotes`, `Portal`, …),
  each with `Actions/`, `Data/`, `Http/`, `Models/`. Put new code where its
  siblings are.
- Commit messages: Conventional Commits, subject ≤ 50 chars, body only when
  the *why* is not obvious from the diff.
