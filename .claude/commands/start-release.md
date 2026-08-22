---
description: Create a new release branch with the next plugin version and bump version metadata
argument-hint: "[version]  (optional — defaults to next patch bump)"
allowed-tools: Bash, Read, Edit
---

You are starting a new release branch for the **TeraWallet (`woo-wallet`)** plugin.

The optional argument is a target version: `$ARGUMENTS`

Follow these steps exactly. If any precondition fails, STOP and tell the user — do not
work around it.

## 1. Preconditions

- Run `git status --porcelain`. If the output is non-empty, STOP: the working tree must
  be clean. Tell the user to commit or stash first.
- Run `git rev-parse --abbrev-ref HEAD`. If it is not `master`, STOP: this command must
  start from `master`.

## 2. Sync master

- `git checkout master`
- `git pull origin master`

## 3. Determine the target version

- Read the current version from `woo-wallet.php` — the line
  `define( 'WOO_WALLET_PLUGIN_VERSION', '<x.y.z>' );`.
- If `$ARGUMENTS` was provided and is a valid `x.y.z` semver string, use it as the target
  version. It must be strictly greater than the current version — if not, STOP.
- Otherwise, compute the next **patch** version (e.g. `1.6.1` → `1.6.2`). Patch-only
  bumps are the project default.

## 4. Create the release branch

- `git checkout -b release/<version>`

## 5. Bump the version in all four locations

Edit each of these so the version string reads `<version>`:

1. `woo-wallet.php` — the plugin header comment line `Version: <x.y.z>` (near line 6).
2. `woo-wallet.php` — `define( 'WOO_WALLET_PLUGIN_VERSION', '<x.y.z>' );` (near line 35).
3. `readme.txt` — the `Stable tag: <x.y.z>` line (near line 7).
4. `package.json` — the top-level `"version": "<x.y.z>"` (near line 4).

## 6. Add a changelog stub

WordPress.org's readme guidance is to keep **only the current release** in `readme.txt`
and leave the full history in `changelog.txt` (a readme over 10 KB can fail to parse).
`readme.txt` already links to the archive, so this step *resets* both sections rather
than appending to them.

**Guard first.** For every `= v<x.y.z> ... =` block currently in `readme.txt`'s changelog,
confirm a matching `= v<x.y.z> ` block exists in `changelog.txt`. If any is missing,
STOP and tell the user — `/finish-release` step 7 was skipped for that version and
removing it here would lose it.

Then replace the entire `== Changelog ==` section of `readme.txt` (everything from the
`== Changelog ==` line up to, but not including, `== Upgrade Notice ==`) with exactly:

```
== Changelog ==

= v<version> (Unreleased) =
* Tweak - Development in progress.

[See changelog for all versions](https://raw.githubusercontent.com/malsubrata/woo-wallet/master/changelog.txt).

```

And replace the entire `== Upgrade Notice ==` section with a single stub entry:

```
== Upgrade Notice ==

= <version> =
Development in progress.
```

Bullets use the standard WordPress form `* Category - Sentence.` — categories are
`Security`, `New`, `Fix`, `Tweak`, `Performance`. (Entries before v1.6.13 in
`changelog.txt` use an older `– **Category:-**` style; leave those alone.)

## 7. Commit and push

- `git add -A`
- `git commit -m "chore(release): start v<version>"`
- `git push -u origin release/<version>`

## 8. Report

Tell the user:
- The previous version and the new version (from → to).
- The branch name `release/<version>` (created locally and pushed to GitHub).
- A reminder to do all feature development on this branch and to replace the
  `Development in progress.` changelog and upgrade-notice placeholders before finishing.
- That older changelog entries were removed from `readme.txt` and now live only in
  `changelog.txt`, plus the resulting `readme.txt` size (`du -b readme.txt`) — it should
  be well under 10 KB.
- That they should run `/finish-release` from this branch when the release is ready.
