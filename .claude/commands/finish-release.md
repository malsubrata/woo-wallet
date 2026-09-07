---
description: Review the current release branch (code + security) and, if clean, merge it into master
allowed-tools: Bash, Read, Edit, Agent
---

You are finishing a release branch for the **TeraWallet (`woo-wallet`)** plugin: review
it, and only if it passes, merge it into `master`.

This command must NOT merge anything if the review finds blocking issues or the build
fails. The review gate is the entire point of this command.

## 1. Preconditions

- Run `git rev-parse --abbrev-ref HEAD`. It MUST match `release/*`. If not, STOP and tell
  the user to check out the release branch first.
- Run `git status --porcelain`. If non-empty, STOP: the working tree must be clean.
- Extract `<version>` from the branch name (`release/<version>`).
- `git fetch origin`.

## 2. Compute the diff

- `git diff master...HEAD --stat` for an overview.
- `git diff master...HEAD` for the full review.

## 3. Code review

Review the full diff yourself against the conventions in `CLAUDE.md`, focusing on what
neither CI nor the agents cover:

- New `terawallet/v1` REST controllers registered in BOTH
  `WooWallet_API::rest_api_includes()` and `TeraWallet_REST_Route_Registry::register_all()` —
  partial registration means a route loads but is unprotected.
- New webpack entries placed in the correct config (`wcBuildConfig` for Blocks-aware code,
  `vanillaAssetsConfig` for plain admin/frontend JS).
- Anything in the diff that contradicts `CLAUDE.md` — if the code has drifted from the
  documented architecture, either the code or the doc is wrong. Say which.
- Dead code, debug output, or commented-out blocks that should not ship.

**Do NOT hand-check what CI already enforces deterministically** — direct writes to
`woo_wallet_transactions`, version agreement across the four locations, `uninstall.php`
table coverage, required plugin headers, the PHP floor, text-domain correctness,
`$wpdb->prepare()` usage, and escaping/sanitization sniffs all run in
`.github/workflows/ci.yml` and `phpcs.xml.dist`. If one of those is wrong, CI says so in
step 5. Re-reading the diff for them by hand is slower and less reliable.

Schema changes are covered by `wallet-ledger-auditor` (L11) and `wc-platform-reviewer` (R-6)
in the next step — don't duplicate them here.

## 4. Security, ledger & platform review

Dispatch the read-only auditors **in parallel, in a single message**, with the full diff.
They have separate remits and do not need each other's output. Route by what the diff
actually touches — this is the same routing table `/review` uses:

- **`security-auditor`** — always, on any release diff that touches PHP. REST/AJAX,
  capabilities, nonces, SQL, IDOR, privilege escalation.
- **`wallet-ledger-auditor`** — if the diff touches money-moving code:
  `includes/class-woo-wallet-wallet.php`, anything in `includes/services/`,
  `includes/helper/woo-wallet-update-functions.php`, `includes/class-woo-wallet-install.php`,
  `class-woo-wallet-cashback.php`, `includes/actions/`, or any REST controller that mutates
  a balance.
- **`wc-platform-reviewer`** — if the diff touches the WooCommerce surface or anything an
  existing store already depends on: order or gateway hooks, `woocommerce_order_status_*`,
  `class-woo-wallet-payment-method.php`, `*-blocks.php`, `src/payment-method/`,
  `src/partial-payment/`, Action Scheduler, the `$db_updates` array, option keys,
  `templates/`, `includes/marketplace/`, `includes/multicurrency/`, or any changed public
  hook signature or REST response shape.

A release diff of any size usually triggers all three. That is expected at the release gate.

All three are read-only. Collect every finding; do not let them apply fixes. Then merge the
reports: deduplicate findings two agents raised from different angles (note the agreement —
it is signal), resolve any disagreement by reading the code yourself, and **open the cited
`file:line` for every CRITICAL and HIGH before treating it as blocking.** A cited line that
does not support the claim gets downgraded or dropped, and you say that you dropped it.

## 5. Build, lint & translations

- `composer test` — the full PHPUnit suite MUST pass. Read the output; do not infer.
- `composer lint` — PHPCS. Errors are blocking, warnings are not.
- `npm run build` — this MUST complete with no errors.
- `npm run lint:js` and `npm run lint:css` — report any warnings/errors (warnings are
  not blocking, errors are).
- `npm run make-pot` — regenerate `languages/woo-wallet.pot` so the shipped translation
  template matches the release strings. This requires WP-CLI; if it fails, STOP and
  report (the release must ship an up-to-date `.pot`). The regenerated file is committed
  in step 9.

## 6. Version consistency check

Confirm all of the following agree on `<version>`:
- `woo-wallet.php` header `Version:` line.
- `woo-wallet.php` `WOO_WALLET_PLUGIN_VERSION` define.
- `readme.txt` `Stable tag:` line.
- The `release/<version>` branch name.

If they disagree, STOP and report the mismatch.

## 7. Changelog check & sync to changelog.txt

### 7a. Write the user-facing changelog

Dispatch the **`terawallet-changelog-writer`** agent over this release's work:

> Write the changelog block and Upgrade Notice for TeraWallet v<version>.
> Commit range: `master..release/<version>`. Release date: <today, Month D, YYYY>.

Replace the accumulated development bullets in `readme.txt`'s `= v<version> ... =` entry
with the block it returns, and replace the `== Upgrade Notice ==` entry with its notice.
Review its output before pasting — you own the wording, it drafts it. If it reports
things it deliberately left out and one of them is genuinely user-visible, add it in the
same style rather than reverting to a long entry.

`readme.txt` must contain **only** this one version's entry, followed by the
`[See changelog for all versions](...)` archive link. If older entries are present,
`/start-release` did not reset them — move them to `changelog.txt` (if not already there)
and remove them here.

### 7b. Date and sync

In `readme.txt`, find the `= v<version> ... =` changelog entry:
- It must contain real entries — if it still only has the
  `Development in progress.` placeholder, STOP and tell the user to write the changelog
  before finishing.
- Replace `(Unreleased)` in the header with today's date in `Month D, YYYY` format
  (e.g. `(May 20, 2026)`).
- The Upgrade Notice body for this version must be **300 characters or fewer** — check it.
- Check `du -b readme.txt`. WordPress.org advises staying near 10 KB; the changelog
  section is the part that must not grow. If the changelog section alone
  (`awk '/== Changelog ==/,/== Upgrade Notice ==/' readme.txt | wc -c`) is over ~2500
  bytes, it was not reset or the entries are too long — fix that before continuing.

Then mirror that finalized entry into `changelog.txt` (the standalone changelog archive):
- Read the full, now-dated `= v<version> (Month D, YYYY) =` block from `readme.txt`
  (the header line plus every `* ...` bullet, up to but not including the archive link
  or the next `= v... =` header).
- If `changelog.txt` already contains a `= v<version> ` block, STOP and report — it has
  already been synced; do not duplicate it.
- Otherwise insert the copied block at the TOP of the changelog list in `changelog.txt`,
  immediately after the `*** Changelog ***` header line and before the existing newest
  entry, with one blank line separating it from the entry that follows. Do not touch any
  older entries.
- The wording must match `readme.txt` verbatim so the two changelogs never drift.

## 8. Decision gate

- If the code review or any of the three auditors found **blocking** issues (CRITICAL or
  HIGH that survived your `file:line` verification), or `composer test` failed, or
  `npm run build` failed, or any STOP condition above was hit → **STOP**. Present a clear,
  organized report of every finding. Do NOT merge.
- Otherwise, present a concise summary (what changed, agent results, build status) and
  continue.

## 9. Commit the changelog finalization & regenerated translations

The changelog date change and `changelog.txt` sync (step 7) and the regenerated
`languages/woo-wallet.pot` (step 5) must land on the release branch before the merge.
Run `git status --porcelain`; if it is non-empty:
- `git add readme.txt changelog.txt languages/woo-wallet.pot`
- `git commit -m "chore(release): finalize v<version> changelog and regenerate translations"`
- `git push`

(If `make-pot` produced no diff and both changelogs were already finalized, there is
nothing to commit — skip this step.)

## 10. Merge into master

- `git checkout master`
- `git pull origin master`
- `git merge --no-ff release/<version> -m "Release v<version>"`
- `git tag v<version>`
- `git push origin master`
- `git push origin v<version>`

## 11. Clean up the release branch

- `git branch -d release/<version>`
- `git push origin --delete release/<version>`

## 12. Report

Tell the user:
- The merge commit hash on `master` and the tag `v<version>` that was pushed.
- A summary of the review (code review + security agent findings, build result).
- That this command handles GitHub only. To publish to the WordPress.org plugin SVN repo,
  they should run the separate **`/build-dist`** command (now on `master`), which stages the
  runtime files into `dist/` and prints the `svn` steps. Do NOT package `dist/` here.
