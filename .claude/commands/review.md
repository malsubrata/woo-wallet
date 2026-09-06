---
description: Read-only code review of a diff — routes to the security, ledger and platform auditors and reports a gate verdict. Never edits, commits or merges.
allowed-tools: Bash, Read, Grep, Glob, Agent
---

Review a TeraWallet change and report what is wrong with it. **This command changes nothing.**

Unlike `/finish-release`, this runs on any branch at any time, including a dirty working
tree. Use it while the change is still small — a blocking ledger defect found on a
three-commit branch costs minutes; the same defect found at release time costs a release.

## 0. This command is read-only

You may run `git diff`, `git log`, `git show`, `git status`, `grep`, and read files. You may
**not** `Edit`, `Write`, `git add`, `git commit`, `git stash`, `git checkout`, run a build, or
apply any fix an agent suggests. If the user wants a fix, they ask for it in a separate turn.

Do not run `composer test` or `npm run build` here either — this is a review, not a gate, and
those belong to `/finish-release`. If a finding needs the suite to confirm, say so.

## 1. Resolve the scope

`$ARGUMENTS` decides what gets reviewed:

| Argument | Diff to review |
|---|---|
| *(none)* | `git diff HEAD` — uncommitted work. If the tree is clean, fall back to `git diff master...HEAD` and say which you used. |
| `--staged` | `git diff --cached` |
| `<ref>` (e.g. `master`, `HEAD~3`, `release/1.6.16`) | `git diff <ref>...HEAD` |
| one or more paths | those files in full, plus `git log -3 --oneline` on each for context |

Print the scope and `git diff --stat` before doing anything else, so the user can see what is
about to be reviewed. If the diff is empty, say so and stop — do not review the whole repo.

## 2. Route

Read the changed file list and decide which auditors to dispatch. **Do not dispatch all three
by default** — each one re-reads the diff and its surrounding code, and this plugin has
several files over 70 KB.

| The diff touches | Dispatch |
|---|---|
| `includes/api/**`, `class-woo-wallet-ajax.php`, `class-woo-wallet-frontend.php`, any `$wpdb` call, any `current_user_can` / nonce / capability change, `src/admin/settings/**` | `security-auditor` |
| `includes/class-woo-wallet-wallet.php`, `includes/services/**`, `includes/helper/woo-wallet-update-functions.php`, `includes/class-woo-wallet-install.php`, `class-woo-wallet-cashback.php`, `includes/actions/**`, or any refund / partial-payment / clawback path | `wallet-ledger-auditor` |
| order or gateway hooks, `woocommerce_order_status_*`, `class-woo-wallet-payment-method.php`, `*-blocks.php`, `src/payment-method/**`, `src/partial-payment/**`, Action Scheduler use, `$db_updates`, option keys, `templates/**`, `includes/marketplace/**`, `includes/multicurrency/**`, or any changed public hook signature or REST response shape | `wc-platform-reviewer` |
| only `src/**` JS/SCSS with no checkout or amount effect, or only docs | **none** — review it yourself against `CLAUDE.md`, and remind the user to run `npm run lint:js` / `lint:css` |

Routes overlap on purpose. A REST controller that moves money dispatches all three.

State which agents you are dispatching and why, in one line each, before dispatching.

## 3. Dispatch

Dispatch the selected agents **in parallel, in a single message**. They have separate remits
and do not need each other's output. Give each one:

- the scope (the exact `git diff` command you used, so they can reproduce it)
- the changed file list
- any context the user gave you about what the change is meant to do

All three are read-only. If an agent returns a suggested patch, **do not apply it.**

## 4. Merge the findings

Combine the reports into one list. Then do the work the agents cannot do for each other:

- **Deduplicate.** Two agents describing the same defect from different angles is one
  finding. Keep the one with the better failure scenario and note that both flagged it —
  that agreement is signal, so say so.
- **Resolve conflicts.** If two agents disagree, read the code yourself and say which is
  right. Do not pass the disagreement through to the user unresolved.
- **Drop the noise.** Remove anything that is a style preference, a formatting nit, generic
  best-practice advice, a theoretical performance concern with no measurement, or a problem
  that already existed and is untouched by this change. Say how many you dropped and why, in
  one line — visible pruning, not silent pruning.
- **Sanity-check the high-severity ones yourself.** Open the cited `file:line` for every
  CRITICAL and HIGH. If the cited line does not support the claim, downgrade it to Low
  confidence or drop it and say you did. An agent citing a line that does not say what it
  claims is the main way these reviews go wrong.

## 5. Report

```
## Review — <scope>

<one paragraph: what this change does, in your own words from reading it>

Agents dispatched: <names, and why>

### Blocking
<CRITICAL and HIGH findings, or "none">

### Non-blocking
<MEDIUM, LOW, INFO>

### Missing test coverage
<named tests that should exist, and where>

### Not reviewed
<what nobody covered, and why — e.g. "no agent covers the SCSS change; run lint:css">

### Verdict
BLOCK — <n> blocking findings must be resolved
or
PROCEED WITH NOTES — <n> non-blocking findings
or
CLEAN — no findings, coverage stated above
```

Each finding keeps this shape:

```
#### [SEVERITY] <title>   (<agent> · <check id if any>)
* **Where:** file.php:123
* **What:** the defect
* **Failure:** the concrete sequence and the wrong outcome — a number or a state, not an adjective
* **Fix:** specific, respecting the documented architecture
* **Confidence:** High | Medium | Low
```

Order by severity, then by confidence within a severity.

## 6. Stop

Report and stop. Do **not** offer to fix things as part of this command, do not stage
anything, and do not merge. If the verdict is BLOCK, the next step belongs to the user:
they decide what to fix, and a separate turn does the fixing so the diff stays theirs to see.

If a money-path finding needs a regression test, name it — the user can dispatch
`wallet-qa-engineer` to write it. That agent is the one agent here that writes files, and it
is invoked deliberately, never from this command.
