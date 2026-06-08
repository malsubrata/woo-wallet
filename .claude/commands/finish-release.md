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

Review the full diff against the conventions in `CLAUDE.md`. Flag any of:
- Ledger writes that bypass `Woo_Wallet_Wallet::credit/debit/transfer` (direct writes to
  `woo_wallet_transactions` are not allowed).
- Direct `$wpdb` queries without `$wpdb->prepare()`.
- Use of a text domain other than `woo-wallet`.
- New `terawallet/v1` REST controllers not registered in BOTH
  `WooWallet_API::rest_api_includes()` and `register_rest_routes()`.
- A DB schema change without a matching migration registered in the `$db_updates` array
  in `class-woo-wallet-install.php` plus its callback in
  `includes/helper/woo-wallet-update-functions.php`.
- General WordPress/WooCommerce coding-standard issues, missing escaping/sanitization.

## 4. Security review

Dispatch the **`terawallet-security-auditor`** agent (via the Agent tool) with the full
diff and ask it to audit the release branch changes for security vulnerabilities.

If the diff touches money-moving code — `includes/class-woo-wallet-wallet.php`,
anything in `includes/services/`, or REST controllers that mutate balances — ALSO
dispatch the **`wallet-ledger-auditor`** agent on the diff.

Collect every finding from these agents.

## 5. Build, lint & translations

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

In `readme.txt`, find the `= v<version> ... =` changelog entry:
- It must contain real entries — if it still only has the
  `– **Tweak:-** Development in progress.` placeholder, STOP and tell the user to write
  the changelog before finishing.
- Replace `(Unreleased)` in the header with today's date in `Month D, YYYY` format
  (e.g. `(May 20, 2026)`).

Then mirror that finalized entry into `changelog.txt` (the standalone changelog archive):
- Read the full, now-dated `= v<version> (Month D, YYYY) =` block from `readme.txt`
  (the header line plus every `– **...** ...` bullet, up to but not including the next
  `= v... =` header).
- If `changelog.txt` already contains a `= v<version> ` block, STOP and report — it has
  already been synced; do not duplicate it.
- Otherwise insert the copied block at the TOP of the changelog list in `changelog.txt`,
  immediately after the `*** Changelog ***` header line and before the existing newest
  entry, with one blank line separating it from the entry that follows. Do not touch any
  older entries.
- The wording must match `readme.txt` verbatim so the two changelogs never drift.

## 8. Decision gate

- If the code review or security agents found **blocking** issues, or `npm run build`
  failed, or any STOP condition above was hit → **STOP**. Present a clear, organized
  report of every finding. Do NOT merge.
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
- A reminder that deploying the update to the **WordPress.org plugin SVN repo is still a
  manual step** — this command only handles GitHub.
