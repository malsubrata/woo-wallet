---
description: Package the TeraWallet plugin runtime files into dist/ for the WordPress.org SVN repo
allowed-tools: Bash, Read
---

You are packaging the **TeraWallet (`woo-wallet`)** plugin for publication to the
WordPress.org plugin SVN repository. This stages the exact set of runtime files into a
`dist/` folder so the user can copy them into the SVN working copy.

This command does NOT touch git, the SVN repo, or any version metadata. It only (re)creates
`dist/`. It is normally run after `/finish-release` has merged the release to `master`, but
it can be run any time you need a fresh package.

## 1. Preconditions

- Run `git rev-parse --abbrev-ref HEAD`. Packaging is normally done from `master` (the
  released code). If you are NOT on `master`, do not stop — but WARN the user that `dist/`
  will reflect the currently checked-out branch, and ask them to confirm that is intended.
- Confirm `.distignore` exists at the plugin root. If it is missing, STOP — it is the
  source of truth for what gets excluded from the package.

## 2. Ensure a fresh build exists

`build/` is gitignored but is the only thing WordPress loads at runtime, so it MUST be in
the package and MUST be current.

- Verify `build/` exists and is non-empty: `test -d build && [ -n "$(ls -A build)" ]`.
- If it is missing or empty, run `npm run build` and confirm it compiles with no errors
  (both webpack configs must report `compiled successfully`). Do NOT package an unbuilt or
  stale plugin. If the build fails, STOP and report.

## 3. Stage dist/

- `rm -rf dist && mkdir dist`
- `rsync -a --exclude-from=.distignore --exclude='/dist' ./ dist/`

The `.distignore` file controls exactly what is excluded. Everything not listed there ships.
The currently shipped payload is: `woo-wallet.php`, `includes/`, `build/`, `templates/`,
`languages/`, `readme.txt`, `uninstall.php`, `LICENSE`. Dev sources/tooling (`src/`,
`node_modules/`, `vendor/`, `tests/`, build config), project meta (`.git/`, `.claude/`,
`CLAUDE.md`, …) and the repo docs (`README.md`, `ADMIN_GUIDE.md`, `CUSTOMER_GUIDE.md`,
`changelog.txt`) are excluded — `readme.txt` is the canonical user-facing doc on
WordPress.org.

## 4. Verify the payload

- `ls -la dist/` so the user can eyeball it.
- Confirm `dist/build/` is present and non-empty (`test -d dist/build`). If it is missing,
  STOP — the package is unusable without compiled assets.
- Sanity-check that excluded items did NOT leak in: `dist/src`, `dist/node_modules`,
  `dist/vendor`, `dist/tests`, `dist/.git`, `dist/CLAUDE.md`, `dist/README.md`,
  `dist/changelog.txt` must all be absent.
- Confirm `dist/` is gitignored (it must NOT appear in `git status --porcelain`).

## 5. Report

Tell the user:
- That `dist/` is staged and the payload contents (and total size, e.g. `du -sh dist/`).
- The version being packaged (read `Stable tag:` from `readme.txt`).
- The remaining manual SVN steps, using their local WordPress.org SVN checkout:

  ```bash
  # from your woo-wallet SVN working copy:
  cp -a /path/to/woo-wallet/dist/* trunk/
  svn add trunk/* --force
  svn cp trunk tags/<version>
  svn ci -m "Release <version>"
  ```

- A reminder that `dist/` is gitignored and is never committed to git.
