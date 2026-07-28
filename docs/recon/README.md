# Recon harnesses

These two scripts are the evidence behind [`../core-notes.md`](../core-notes.md).
They exist so the findings can be re-verified against any future WordPress
release rather than trusted on the strength of one reading.

Neither script is part of the plugin. Both are excluded from the distribution
zip by `.distignore`.

## What they prove

| Script | Proves |
|---|---|
| `probe-render.php` | How `_wp_menu_output()` treats separator items, raw titles, empty slugs, class escaping, and items whose capability fails. core-notes.md §2, §3. |
| `probe-order.php` | What the `menu_order` filter can and cannot do — dropped slugs, duplicates, phantom slugs, non-array returns. core-notes.md §4. |
| `probe-render-e2e.php` | That the finished accordion actually renders. Pushes the 35-item production fixture through the plugin's own reorder and decorate steps, then through core's `_wp_menu_output()`, and asserts the result: header rows emitted, none `aria-hidden`, active group force-expanded, collapsed groups hidden, no slug lost. |

`probe-render.php` runs the **real** core function, extracted verbatim rather
than reimplemented. `probe-order.php` replicates core's reorder block, which is
inseparable from surrounding procedural code and so cannot be extracted.

## Reproducing

1. Download and extract an official WordPress release next to this directory.

2. Extract `_wp_menu_output()` from core into `core-fn.php`. The function spans
   lines 73–289 in WP 6.4 through 7.0.2 — **re-check the boundaries** if you are
   testing a newer release:

   ```bash
   sed -n '73,289p' /path/to/wordpress/wp-admin/menu-header.php > core-fn.php
   sed -i '1i <?php' core-fn.php
   ```

   `core-fn.php` is GPL WordPress core and is deliberately **not** committed —
   see `.gitignore`.

3. Run both:

   ```bash
   php -d error_reporting=E_ALL -d display_errors=1 probe-render.php
   php -d error_reporting=E_ALL -d display_errors=1 probe-order.php
   ```

Expected output for each claim is recorded inline in `core-notes.md`. Any
divergence means core changed and the rendering strategy needs re-deciding
before shipping against that version.

## Versions this was last run against

- WordPress 7.0.2 and 6.4 (both menu files diffed; no relevant delta)
- PHP 8.3.32
