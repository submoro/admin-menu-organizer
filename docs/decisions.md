# Decisions log

## Confirmed with the product owner

| Date | Decision | Value |
|---|---|---|
| 2026-07-28 | Public slug | `wp-admin-menu-organizer` — renamed from the original `menu-organizer-collapsible-admin-menu` at the owner's request, to shorten it and signal WordPress. Availability confirmed against the plugins API, not by scraping the HTML page. See D-014. |
| 2026-07-28 | Display name | `WP Admin Menu Organizer` |
| 2026-07-28 | PHP prefix | `WPAMO_` constants, `wpamo_` functions and keys, `wpamo-` CSS and JS, `WPAMO\` namespace |
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

### D-006 — The test suite is split into unit and integration

**Spec said (§12.1):** "PHPUnit via `wp-env` and the WordPress test suite."

**Problem:** that makes every test require a database, which this build machine
does not have and which the agreed toolchain does not include. Deferring all
verification to CI would mean reporting phases complete on unrun tests, against
§14.

**Decision:** two suites.

| Suite | Location | Needs | Runs |
|---|---|---|---|
| `unit` | `tests/unit/` | Nothing but PHP and Composer | Locally, every phase, plus CI on 7.4/8.1/8.3 |
| `integration` | `tests/integration/` | WordPress, the test library, a database | CI only |

Configured by `phpunit-unit.xml.dist` and `phpunit.xml.dist` respectively;
`composer run test` is aliased to the unit suite because that is the one that
always works.

This has a design consequence worth stating plainly, and it is a good one: the
components §12.1 wants covered — the detector, the layout sanitiser, the
`menu_order` builder and the migration chain — must be written as **pure
functions of their inputs**, taking the menu array and the layout as arguments
rather than reaching for `$GLOBALS['menu']` or calling `get_option()` directly.
The WordPress-facing classes stay thin wrappers that fetch and delegate. That is
better structure regardless of the test arrangement.

### D-007 — Open: the readme Contributors field is not yet correct

`Contributors:` in `readme.txt` currently reads `moamenelabd`, which is a guess.
It requires a **WordPress.org username**, the slug that appears at
`profiles.wordpress.org/<username>/` — not the email address used to sign in,
and not a display name. An email address there would fail directory validation
and would publish the address on the plugin page.

Must be confirmed before submission. Wrong value is not fatal to the plugin, but
the plugin will not appear on the author's profile and the author will not be
able to manage it.

### D-008 — The detector is a cascade, not a lookup table

**Requested mid-build:** cover every plugin in the directory, or provide a
mechanism that can categorise any plugin.

Enumerating ~60,000 plugins is not achievable or maintainable, and a table alone
would fail on the sub-pages and add-ons that plugins register beyond their main
menu item. So SPEC section 5.2's five steps were extended to eight layers, each
more general than the last, first answer wins:

| Layer | Signal | Decisive? |
|---|---|---|
| 1 | Exact menu slug, case-sensitive then case-insensitive | yes |
| 2 | Registered post type from `edit.php?post_type=X` | yes |
| 3 | Distinctive brand token anywhere in the slug | yes |
| 4 | Vendor prefix at the start of the slug, boundary-checked | yes |
| 5 | The capability guarding the item | yes |
| 6 | Weighted keywords over title and slug | only above threshold |
| 7 | The Dashicon the vendor chose | yes, last resort |
| 8 | `ungrouped` | fallback |

Three of these do the heavy lifting for plugins nobody has listed:

- **Post type defaulting.** An unrecognised custom post type is `content`,
  because that is what a post type is. This one rule correctly files every
  custom post type on every site without naming any of them.
- **Vendor prefixes and brand tokens.** `wpseo_titles`, `woo-variation-swatches`
  and `elementor-custom-icons` are all placed without appearing in any table,
  which is how the plugin copes with add-ons and sub-pages.
- **Namespaced capabilities.** A plugin guarding its page with
  `manage_woocommerce` has told us what it is. `manage_options` is deliberately
  excluded, being the default for almost every settings page in existence.

**Declining to answer is a feature.** Layer 6 refuses when no category clearly
leads, or when the leader ties, or when the score is below threshold. An item in
the always-visible Other group is one the administrator will notice and can
move; an item filed somewhere plausible but wrong is one they will never think to
look for.

**Measured, not asserted.** A blind sweep of forty real plugins whose slugs are
deliberately absent from the table scores **40/40**. The thirty-two-item
production fixture is filed **31/32**, the one holdout being a deliberately
meaningless item that must not be guessed at. Both are committed, the sweep as
`test_vendor_tokens_generalise_to_unlisted_slugs` and
`test_keywords_place_unheard_of_plugins`.

Two scoring defects were found by that sweep and fixed:

- **Title and slug are now weighted separately**, title double. Slugs are noisy:
  `custom-login-page` contributes the word "page", a real content keyword that
  says nothing about a login customiser, and it was outvoting the actual subject.
- **Regular plurals were double-counting.** Keyword matching is boundary-aware at
  the start of a word with a free tail, so `setting` already matches `settings`.
  Listing both scored one piece of evidence twice. Eleven such pairs were
  removed and the file now documents the rule.

### D-009 — Two files added beyond SPEC section 11

- `includes/class-categories.php` — category definitions and their translated
  labels. Labels cannot live in the data file: translating them there would run
  `__()` the moment the file is required, which can precede text-domain
  availability and trip WordPress 6.7's `_load_textdomain_just_in_time` notice,
  against SPEC section 15's zero-notice requirement.
- `includes/data/categories.php` — the definitions themselves, kept alongside
  `known-slugs.php` and `keyword-map.php` for consistency.

Also added `bin/check-readme.php`, CLI-only and excluded from the zip, because
SPEC section 10.3 requires the readme to validate and nothing was checking it.

### D-010 — A byte order mark got into a data file, so it is now tested for

A tooling default wrote UTF-8 **with** BOM into `includes/data/keyword-map.php`.
A BOM ahead of `<?php` is emitted as body content, which in WordPress surfaces as
"Cannot modify header information - headers already sent" and breaks redirects,
cookies and the REST API. The unit suite caught it as unexpected output.

`tests/unit/test-file-hygiene.php` now asserts, for every PHP file: no BOM,
`<?php` on the first byte, no closing tag, and a direct-access guard on
everything that ships. The data files are additionally required inside an output
buffer to prove they emit nothing.

### D-011 — Directory artwork is generated, not sourced

SPEC section 10.1 requires every bundled asset to be GPL-compatible with its
source documented. `bin/build-assets.php` draws the icons and banners with GD
from primitives, so the provenance is unambiguous: the script is the source, and
the output is original work under the same licence as the plugin. No fonts, no
stock imagery, no third-party SVG.

The motif is the plugin's own subject matter — a sidebar with grouped indented
rows, one group collapsed — so the artwork says what the plugin does without
needing text, which also means it survives the directory rendering the plugin
name over the banner.

### D-012 — Screenshots cannot be produced here

`readme.txt` describes five screenshots and `.wordpress-org/` holds the icons and
banners, but not `screenshot-1.png` … `screenshot-5.png`. Capturing them requires
a running WordPress with the plugin active and a realistic plugin stack, which
this machine does not have.

The readme entries are kept, because SPEC section 10.3 lists Screenshots as a
required section and the captions are what a capture session works from. A missing
screenshot file makes the directory show no screenshot; it breaks nothing.
`docs/release.md` carries the capture checklist.

**This is the only outstanding item for submission.**

### D-013 — The zip verifies itself, and immediately caught a leak

`bin/build-zip.php` does not only build the archive; it asserts the contents.
Anything matching a never-ship prefix fails the build, and so does a missing file
the plugin cannot run without.

It earned that on first run: `phpunit-unit.xml.dist` was in the archive, because
`.distignore` listed `phpunit.xml.dist` literally and the second suite config was
added later. `.distignore` now uses globs, and the check would have caught it
whatever the cause.

### D-014 — Renamed to `wp-admin-menu-organizer`

Requested mid-build: shorten the name and signal WordPress in it.

On the "WP" question, SPEC §2 forbids `WordPress` or `WP` "as a leading brand
claim". A leading `wp-` is not that. WordPress's trademark policy objects to
`WordPress` implying official endorsement; `WP` is the accepted abbreviation and
is used by many of the directory's most-installed plugins — WP Rocket, WPForms,
WP Mail SMTP, WP Super Cache. So a leading `wp-` is compliant, and the spec's
constraint is satisfied by not using the full word.

Availability was checked against the **plugins API**
(`api.wordpress.org/plugins/info/1.2/`) rather than by fetching the public plugin
page. That matters: an unknown slug on `wordpress.org/plugins/<slug>/` redirects
to a search results page, which is easy to misread as "the page exists". The API
returns a definitive error for an unclaimed slug.

| Candidate | Result |
|---|---|
| `wp-admin-menu-organizer` | free — **chosen** |
| `wp-menu-organizer` | free |
| `admin-menu-organizer` | free |
| `wp-menu-groups`, `wp-admin-menu-groups`, `collapsible-admin-menu` | free |
| `menu-organizer` | **taken**, 80 active installs |
| `admin-menu-groups` | **taken**, 800 active installs |

Two of the shorter, more obvious candidates were already taken, which is the
reason for checking rather than assuming.

`admin` is kept in the slug deliberately. The directory is full of front-end
navigation-menu plugins, and `admin` is the word that distinguishes this from
them for anyone searching.

The rename was mechanical and total: slug, main file name, display name, text
domain across all 107 strings, `@package` tag, PHP prefix and namespace, option
and user meta keys, CSS classes, body classes, REST namespace, settings page
slug, filter names, the `?wpamo=off` escape hatch and the `WPAMO_DISABLE`
constant. Nothing was released beforehand, so no migration path is needed for the
renamed option keys.

Two existing tests made this safe rather than risky: one asserts the text domain
equals the directory basename, and one asserts the plugin header, the version
constant and the readme's stable tag all agree. Both would have failed on a
partial rename.

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

## Verification status

Everything below is stated as it actually is. Where something has not been
verified, it says so.

### Verified automatically, every phase

| Check | Status |
|---|---|
| `phpcs`: WordPress, WordPress-Extra, WordPress-Docs, PHPCompatibilityWP | clean, 36 files |
| Every shipped file parses on a real **PHP 7.4.33** binary | pass, 43 files |
| Unit suite | **291 tests, 2991 assertions**, green |
| Security audit against SPEC §8 (18 rules) | clean, 25 shipped files |
| `readme.txt` against the WordPress.org standard | 20/20 |
| No outbound-request primitive anywhere in shipped code | clean |
| Both JavaScript files parse | pass |
| Release zip contents | 32 files, nothing leaked, nothing missing |

### Verified by executing real WordPress core code

Rather than by reimplementing it. These are the load-bearing claims.

| Claim | How it was proven |
|---|---|
| `$menu` index map, separator rendering, raw title output | Extracted `_wp_menu_output()` from `menu-header.php` and ran it against fixtures. `docs/recon/` |
| `menu_order` cannot delete an item; duplicates corrupt ordering; a non-array is fatal on PHP 8 | Replicated the reorder block from `includes/menu.php` and exercised six failure modes. `docs/recon/` |
| The accordion renders correctly end to end | Pushed the 35-item production fixture through reorder, decorate, and then core's own `_wp_menu_output()`. 11 header rows emitted, **none `aria-hidden`**, all as bare `<li>`; the active group force-expanded; collapsed groups hidden; every original slug still present. |
| The compiled Arabic `.mo` is loadable | Parsed with WordPress's own `wp-includes/pomo` reader, not the writer that produced it. 79 entries, six Arabic round-trips, no invalid UTF-8. |
| Detection generalises beyond its table | Blind sweep of 40 real plugins deliberately absent from `known-slugs.php`: **40/40**. |

### Not yet verified — needs a running WordPress

None of this can be done on the build machine, which has no WordPress, no
database and no browser. It is the honest remainder of SPEC §12.2.

| Case | Status |
|---|---|
| WordPress 6.4 and 7.0.2, live | **not run** — CI matrix is configured but has never executed; there is no git remote |
| PHPUnit integration suite (13 tests) | **written, not run** — needs a database |
| Plugin Check, both check sets | **not run** — needs a WordPress install |
| `WP_DEBUG` + `SCRIPT_DEBUG` notice sweep | **not run** |
| All nine admin colour schemes, visually | **not run** — the CSS derives from values extracted from core's own scheme files, so it is reasoned rather than seen |
| RTL with Arabic and WPML, visually | **not run** — the stylesheet uses logical properties throughout and a real Arabic translation ships to test against |
| WooCommerce + page builder + security + SEO, live | **not run** — modelled as a fixture and passing, but not observed |
| Alongside Admin Menu Editor | **not run** |
| Folded sidebar, mobile, 782 px, 960 px | **not run** |
| Subscriber / Editor / Shop Manager / Administrator | **not run** live; covered by integration tests that have not executed |
| Multisite network-admin no-op | **not run** live; the guard is unit-tested |
| Keyboard-only navigation | **not run** |
| Screen reader (NVDA or VoiceOver) | **not run** |

The first three rows are the ones to clear first: they are cheap on any real
install and would confirm most of the rest.
