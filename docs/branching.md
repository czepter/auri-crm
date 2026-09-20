# Branching

```
feat/* fix/* chore/* ...  ->  dev  ->  main
```

`dev` is the integration branch. Everything is proven there before it reaches
`main`, so `main` only ever moves by merging `dev` (or a hotfix).

## Rules

1. Cut work branches from `dev`, never from `main`:
   `git switch dev && git pull && git switch -c feat/lead-board`
2. Name them `<type>/<slug>`, where type is one of
   `feat feature fix bugfix hotfix chore docs refactor ci test`.
3. Open the pull request against `dev`.
4. `main` accepts pull requests from `dev`, `hotfix/*` or `release/*` only.
5. A `hotfix/*` merged into `main` gets merged back into `dev` immediately, so
   `dev` never falls behind `main`.

CI runs only on pull requests into `main`, where the `branch-rules` job in
`.github/workflows/ci.yml` enforces rule 4. The rest is convention.

## Branch protection

The CI job reports a failure, but only GitHub branch protection can stop a
merge. `dev` has no CI, so its protection requires no checks. Protect `main`
with:

```sh
for BRANCH in main; do
  gh api -X PUT "repos/{owner}/{repo}/branches/$BRANCH/protection" \
    -F required_status_checks[strict]=true \
    -f 'required_status_checks[contexts][]=branch-rules' \
    -f 'required_status_checks[contexts][]=studio' \
    -f 'required_status_checks[contexts][]=web' \
    -f 'required_status_checks[contexts][]=e2e' \
    -F enforce_admins=false \
    -F required_pull_request_reviews[required_approving_review_count]=0 \
    -F restrictions=null
done
```

Set `dev` as the repository default branch too, so new pull requests are
targeted at it without anyone having to remember.

`studio`, `web` and `e2e` are safe to require even though they are skipped when
no relevant path changed: GitHub counts a skipped job as a passing check.
