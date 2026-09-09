---
name: finishing-a-release
description: Use when a version of this portal is released — a tag pushed, a GitHub release published, images built and deployed, or someone asks whether a release is done. The release log is not part of this repository and is missed otherwise.
---

# Ein Release ist erst mit dem Release-Log fertig

`release-log/` liegt in diesem Verzeichnis, gehört aber nicht zu diesem Repo:
eigenes Git, eigenes Remote, in `.gitignore` ausgeschlossen. `git status` zeigt
davon nichts, kein Commit hier nimmt es mit, und in keiner Datei dieses Repos
steht, dass es existiert.

Nach dem Tag:

```bash
cd release-log
git pull                  # sonst laeuft der Generator gegen alte Dateien
node bin/from-git.mjs
```

Der Lauf hängt die Commits des neuen Tags an `releases/<version>.json` — als
englische Commit-Betreffe mit Klassennamen und Dateipfaden. **Das ist Rohstoff
und kein Release-Log.** Eine Version, deren `headline` `null` ist, ist nicht
fertig.

**REQUIRED:** Lies `release-log/.claude/skills/adding-a-release/SKILL.md` und
folge dem Skill. Es sagt, was aus den Roheinträgen wird.

Bilder, Compose und Coolify stehen in `docs/deployment.md`. Der Release-Log
deployt sich selbst, sobald sein `main` gepusht ist.
