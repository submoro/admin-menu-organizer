# Decisions log

## Confirmed with the product owner

| Date | Decision | Value |
|---|---|---|
| 2026-07-26 | Public slug | `menu-organizer-collapsible-admin-menu` — checked against the directory, no plugin page exists at that slug (wordpress.org falls through to search, its behaviour for an unknown slug). Nearest neighbours (`wpb-accordion-menu-or-category`, `collapse-magic`, `vertical-sidebar-menu-block`) do not collide. Meets SPEC §2: no leading trademark, no "WordPress"/"WP" brand claim. |
| 2026-07-26 | Display name | `Menu Organizer - Collapsible Admin Menu` |
| 2026-07-26 | Licence | GPLv2 or later (unchanged from spec) |
| 2026-07-26 | Core source for recon | Official wordpress.org release zips, read-only, in the scratchpad. Not committed. |
| 2026-07-26 | Verification toolchain | PHP + Composer locally (phpcs, PHPCompatibilityWP); PHPUnit matrix and Plugin Check in GitHub Actions, since both need a database and a real WordPress install. |

## Changes forced by core

### D-001 — Group headers cannot be separator items (SPEC §3.3 step 5)

**Spec said:** inject the accordion header as a `wp-menu-separator`-classed
pseudo item "so core renders it without a link target".

**Core says:** a separator-classed item renders as
`<li … aria-hidden="true"><div class="separator"></div></li>` — title discarded,
icon discarded, screen-reader-hidden, and vulnerable to core's
adjacent-separator and trailing-separator cleanup passes. Verified by execution;
see [core-notes.md](core-notes.md) §2.

**Decision:** the header carrier is instead an inert `$menu` item with a unique
slug and the `do_not_allow` capability, spliced in on the `add_menu_classes`
filter. That renders a bare `<li>` which keeps our classes and id and is **not**
`aria-hidden`. Rationale and full pipeline in core-notes.md §5.

### D-002 — Header injection moves from `admin_menu` to `add_menu_classes`

**Spec said:** hook `admin_menu` at priority 9999 to inject headers.

**Core says:** anything injected at `admin_menu` still passes through the nopriv
removal loop (`includes/menu.php:175–200`), which deletes a `do_not_allow` item
that has no submenu — i.e. it would delete every header.

**Decision:** class decoration and header injection both happen on
`add_menu_classes`, the last filter to touch `$menu`. `admin_menu` @ 9999 is
still used, but only to *read* the resolved menu and register the settings page.
Side benefit: our synthetic items are invisible to every other plugin.

### D-003 — "Never drop a slug" reframed

**Spec said:** "Never drop a slug from the `menu_order` array."

**Core says:** `menu_order` **cannot delete a menu item** at all. Slugs missing
from the returned array survive and sort to the end in their original relative
order. Verified by execution; see core-notes.md §4.

**Decision:** the rule stands, but its *reason* changes — a dropped slug is an
ordering bug (item silently jumps to the bottom of the sidebar), not a data-loss
bug. Two genuine hazards replace it, and both get tests in Phase 5:

- **duplicate** slugs corrupt `array_flip()`'s index map → must dedupe;
- returning a **non-array** is a fatal `TypeError` on PHP 8+ → the filter must
  be unconditionally array-returning.

### D-004 — JS is required for the header control only

SPEC §3.3 wants JS to be "progressive enhancement only". Core makes a
server-rendered `<button>` inside the sidebar impossible (core-notes.md §6: no
hook exists between menu items, and no `$menu` field survives unescaped except
the title, which the header carrier's render branch never reaches).

**Decision:** ordering, grouping and collapsed state stay 100 % server-rendered,
so the zero-flash requirement is met in full. Only the header's inner control is
JS-injected, into a row whose height CSS reserves, so there is no layout shift.
With JS disabled the stylesheet force-expands all groups, so the menu degrades
to a grouped, fully usable sidebar and **no item is ever unreachable**.

### D-005 — phpcs alone does not prove PHP 7.4 compatibility

**Spec said (§10.5, §15):** pass `phpcs` against `PHPCompatibilityWP` with
`testVersion 7.4-`, and treat that as the PHP 7.4 gate.

**Reality:** the current stable release of `phpcompatibility/php-compatibility`
is **9.3.5, from May 2019 — eighteen months before PHP 8.0 shipped**. It has no
knowledge of PHP 8 syntax whatsoever. Verified by writing a canary class using
`?->`, `match` and PHP 8 functions and running it through
`--standard=PHPCompatibility --runtime-set testVersion 7.4-`: 117 sniffs
registered, file processed, **zero findings**.

So the spec's gate runs, and it still catches genuine PHP 7.x-era problems, but
it would happily wave through a nullsafe operator that fatals on the plugin's
own declared minimum.

**Decision:** keep the phpcs rule, but do not rely on it. The binding PHP 7.4
check is parsing every shipped file with a real **PHP 7.4.33** binary:

- **Locally**, after every phase. Verified the check has teeth — the same canary
  is rejected with `syntax error, unexpected '->'`.
- **In CI**, as the dedicated `syntax-php74` job, which covers files no test
  happens to execute. The PHPUnit matrix then covers runtime behaviour.

## Environment notes

- The build machine had **no** WordPress install, PHP, Composer, WP-CLI or
  Docker at the start of Phase 1. Installed during Phases 1 and 2:
  - **PHP 8.3.32** (winget), the primary development runtime.
  - **Composer 2.10.2**, installer SHA-384 verified against
    `composer.github.io/installer.sig` before running.
  - **PHP 7.4.33** (official windows.php.net archive build) at
    `~/.claude-php-tools/php74/`, used solely as a syntax oracle. PHP 7.4 is not
    in winget, being end of life.
- **No database server and no Docker**, so the PHPUnit suite cannot run locally.
  It runs in CI only. See the open question logged at the end of Phase 2.
- Local verification command for the PHP 7.4 gate:

  ```
  Get-ChildItem -Recurse -Filter *.php |
    Where-Object { $_.FullName -notmatch '\\vendor\\|\\docs\\recon\\' } |
    ForEach-Object { & "$HOME\.claude-php-tools\php74\php.exe" -l $_.FullName }
  ```
- Current WordPress stable at time of writing is **7.0.2**. The
  `Requires at least: 6.4` floor from SPEC §10.2 was validated by diffing both
  menu files across 6.4 and 7.0.2 — no relevant change.

## Deferred

Ideas noted but explicitly **not** in v1 scope, per SPEC §14.

- Network admin menu grouping on multisite (SPEC §1.4 no-op for v1).
- Grouping of *submenu* items within a top-level item.
- Drag-and-drop reordering of items *within* a group (v1 orders by group
  membership only).
- Import/export of role presets as a bundle (v1 exports the layout only).

## Manual test matrix

To be filled in from Phase 12 onward, per SPEC §12.2. No manual testing has been
performed yet.

| Case | Result | Notes |
|---|---|---|
| WordPress 6.4 | not run | |
| WordPress current stable (7.0.2) | not run | |
| PHP 7.4 / 8.1 / 8.3 | not run | |
| All eight admin colour schemes | not run | |
| LTR and RTL (Arabic) with WPML | not run | |
| WooCommerce + page builder + security + SEO together | not run | |
| Alongside Admin Menu Editor | not run | |
| Folded sidebar, mobile, 782 px, 960 px | not run | |
| Subscriber / Editor / Shop Manager / Administrator | not run | |
| Multisite network-admin no-op | not run | |
| Keyboard-only navigation | not run | |
| Screen reader (NVDA or VoiceOver) | not run | |
