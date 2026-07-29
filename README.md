# WP Admin Menu Organizer

Groups WordPress admin menu items into named, collapsible categories inside the
native sidebar. Sorts what it recognises automatically, and lets you rearrange
everything else by drag and drop.

[![CI](https://github.com/submoro/wp-admin-menu-organizer/actions/workflows/ci.yml/badge.svg)](https://github.com/submoro/wp-admin-menu-organizer/actions/workflows/ci.yml)
&nbsp;License: GPLv2 or later &nbsp;·&nbsp; Requires WordPress 6.4+ &nbsp;·&nbsp; Requires PHP 7.4+

---

## The problem

A production site accumulates thirty to sixty plugins, and every one of them adds
a top-level item to `wp-admin`'s sidebar. The result is a flat, unsorted list
several screens long, with an SEO plugin next to a mail-delivery plugin next to a
firewall. There is no grouping and no way to impose order without editing code.

## What this does

It **reorders and decorates** the existing menu. It never rebuilds it, never
removes an item, and never touches a capability.

- Groups top-level items into categories: Content, Commerce, Design, SEO,
  Security, Performance, Users, Integrations, Tools.
- Recognises plugins on sight and files them correctly — including plugins it has
  never heard of, see [Detection](#detection).
- Full drag-and-drop override, with a keyboard equivalent for every drag.
- Per-user open/closed memory; the group containing the current page always opens.
- Collapsed groups show an aggregated update-count badge.
- Site-wide default, optional per-role layouts, optional per-user personalisation.

### What it deliberately does not do

- **Never hides an item.** Anything unrecognised goes to an always-visible
  *Other* group. Nothing becomes unreachable in any configuration.
- Never modifies index `1` of a menu item, so it cannot grant or revoke access.
- Does not restyle the admin beyond the menu.
- Does not touch the network admin menu on multisite; that screen is left alone.
- Makes **zero outbound HTTP requests**. No telemetry, no analytics, no calling
  home. This is enforced by the test suite, not merely promised.

## Detection

The interesting part. Rather than a lookup table that goes stale, detection is an
**eight-layer cascade**, each layer more general than the last:

| Layer | Signal |
|---|---|
| 1 | Explicit human override in the saved layout |
| 2 | Core slug map (`edit.php`, `themes.php`, …) |
| 3 | Curated table of **343** known plugin menu slugs |
| 4 | Post-type defaulting — `edit.php?post_type=X`, with unknown types → Content |
| 5 | Vendor prefixes (`wpseo`, `wc-`, `elementor`, `yith_`, `tribe_`, …) |
| 6 | Namespaced capabilities (`manage_woocommerce`, `wpseo_manage_options`, …) |
| 7 | Weighted keyword scoring over title and slug, with a confidence floor |
| 8 | Dashicon as a tiebreak (`dashicons-cart`, `dashicons-shield`, …) |

Measured against 40 real plugins **deliberately excluded** from the table:
**40 / 40 correct.** The highest-value rule is layer 4 — an unrecognised custom
post type is content, which files every CPT on every site without naming any.

When signals tie or score too low, it declines rather than guesses, and the item
lands in the always-visible *Other* group.

Extend it without forking:

```php
add_filter( 'wpamo_known_slugs',          function ( $map ) { $map['my-plugin'] = 'commerce'; return $map; } );
add_filter( 'wpamo_category_definitions', function ( $groups ) { /* add your own */ return $groups; } );
add_filter( 'wpamo_resolve_group',        function ( $group_id, $item ) { return $group_id; }, 10, 2 );
add_filter( 'wpamo_resolved_layout',      function ( $layout ) { return $layout; } );
```

## If something goes wrong

Two escape hatches, both of which always work and neither of which discards your
saved arrangement:

```
?wpamo=off                          # disables it for one page load
define( 'WPAMO_DISABLE', true );    # in wp-config.php, disables it entirely
```

## How it works

WordPress builds `$GLOBALS['menu']` during `admin_menu` and renders it in
`wp-admin/menu-header.php`. Every capability check, update bubble, `current`
highlight and third-party integration depends on that pipeline staying intact, so
this plugin leaves it intact.

| Hook | Purpose |
|---|---|
| `custom_menu_order` | Unlock reordering |
| `menu_order` | Return every slug, deduped, grouped contiguously |
| `add_menu_classes` | Decorate items; inject group header rows |
| `admin_body_class` | Emit collapsed state, server-side |

Ordering, grouping and collapsed state are **all server-rendered**, so there is no
flash of an ungrouped menu. JavaScript only fills in the header's button, into a
row whose height CSS already reserves — so no layout shift. With JavaScript off,
every group renders expanded and fully usable.

Design notes, and the four places WordPress core contradicted the original
specification, are in [`docs/core-notes.md`](docs/core-notes.md) and
[`docs/decisions.md`](docs/decisions.md).

## Development

```bash
composer install
composer run test          # unit suite, needs no database
composer run lint          # phpcs: WordPress, -Extra, -Docs, PHPCompatibilityWP
composer run audit         # security audit against the project's own rules
composer run build         # full gate, then produce the release zip
```

The test suite is split. `tests/unit/` needs nothing but PHP and runs anywhere;
`tests/integration/` needs WordPress and a database and runs in CI. That split
forces the detector, sanitiser, reorderer and migration chain to be pure
functions of their arguments, which is better structure regardless.

> **A caveat worth knowing:** `phpcs` does **not** prove PHP 7.4 compatibility
> here. The current stable PHPCompatibility release predates PHP 8.0 and cannot
> see PHP 8 syntax — verified with a canary using `?->` and `match` that it waved
> straight through. The binding check is the `syntax-php74` CI job, which parses
> every shipped file with a real PHP 7.4 binary.

### Verification

- Unit suite: **295 tests, 2995 assertions**
- `phpcs` clean; security audit clean across 25 shipped files
- Every shipped file parses on a real PHP 7.4.33 binary
- The `$menu` internals, the `menu_order` semantics, the finished accordion render
  and the compiled `.mo` are all proven by **executing real WordPress core code**
  rather than a reimplementation of it — see [`docs/recon/`](docs/recon/README.md)

Not yet verified, honestly: the integration suite and Plugin Check have never
executed, and nothing has been seen in a browser. Full status in
[`docs/decisions.md`](docs/decisions.md).

## Releasing

See [`docs/release.md`](docs/release.md) for the pre-submission gate, the zip
build and the WordPress.org SVN layout.

## License

GPLv2 or later. See [LICENSE](LICENSE).

Icons and banners are original artwork generated by
[`bin/build-assets.php`](bin/build-assets.php). No third-party assets, fonts or
libraries are bundled; the drag-and-drop editor uses jQuery UI Sortable, which
ships with WordPress.
