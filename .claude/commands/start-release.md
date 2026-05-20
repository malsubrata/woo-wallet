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

In `readme.txt`, find the `== Changelog ==` section and insert a new entry **directly
above the most recent existing version entry**, matching the existing format exactly:

```
= v<version> (Unreleased) =
– **Tweak:-** Development in progress.
```

(Note the en-dash `–` bullet prefix and the `**Category:-**` style used by existing
entries — categories are `Security`, `New`, `Fix`, `Tweak`.)

## 7. Commit and push

- `git add -A`
- `git commit -m "chore(release): start v<version>"`
- `git push -u origin release/<version>`

## 8. Report

Tell the user:
- The previous version and the new version (from → to).
- The branch name `release/<version>` (created locally and pushed to GitHub).
- A reminder to do all feature development on this branch and to replace the
  `Development in progress.` changelog placeholder with real entries before finishing.
- That they should run `/finish-release` from this branch when the release is ready.
