# Core notes — verified findings

**Inspected WordPress versions:** 7.0.2 (current stable, primary) and 6.4 (the
`Requires at least` floor). Both downloaded from the official wordpress.org
release zips.

**Files read:**

- `wp-admin/menu-header.php`
- `wp-admin/includes/menu.php`
- `wp-admin/admin-header.php` (inclusion point only)

**Method:** every claim below marked *verified by execution* was proven by
extracting the real core function verbatim (`_wp_menu_output()`,
`menu-header.php` lines 73–289) and running it under PHP 8.3.32 against
fixtures, plus a faithful replica of the reorder block (`includes/menu.php`
lines 291–345). Nothing here is inferred from reading alone.

---

## 1. `$menu` item array structure

Core states the map at `menu-header.php:77`:

```php
// 0 = menu_title, 1 = capability, 2 = menu_slug, 3 = page_title, 4 = classes, 5 = hookname, 6 = icon_url.
```

| Index | Meaning | How core treats it on output |
|---|---|---|
| `0` | Menu title | **Emitted raw. Never escaped.** See §3. |
| `1` | Capability | Checked at render (`menu-header.php:177`) *and* earlier for removal (`includes/menu.php:176`). **Never modify.** |
| `2` | Menu slug | Used as the `$submenu` key, the link target, and the identity in `menu_order`. |
| `3` | Page title | Not used by `_wp_menu_output()` at all. |
| `4` | Classes | Run through `esc_attr()` (`menu-header.php:111`). Appended to the `<li>` class list. |
| `5` | Hookname | Becomes the `<li>` `id`, filtered by `preg_replace( '|[^a-zA-Z0-9_:.]|', '-', … )` (`:115`). Underscores survive. |
| `6` | Icon URL | Special-cased for `none` / `div`, `data:image/svg+xml;base64,`, and `dashicons-*` (`:130–144`). |

`$menu` keys are **position strings**, sorted by `uksort( $menu, 'strnatcasecmp' )`
at `includes/menu.php:280` — before any reordering.

## 2. How core renders a `wp-menu-separator` item — *verified by execution*

Detection is a **substring test against the already-built class attribute
string** (`menu-header.php:120`):

```php
if ( str_contains( $class, 'wp-menu-separator' ) ) {
	$is_separator = true;
}
```

Rendering a separator item that carried the title `MY GROUP HEADER`, the icon
`dashicons-admin-post` and the hookname `mocam-hook` produced:

```html
<li class="wp-first-item wp-not-current-submenu mocam-group-header wp-menu-separator" id="mocam-hook" aria-hidden="true"><div class="separator"></div></li>
```

**The title is discarded. The icon is discarded. `aria-hidden="true"` is forced
on.** The separator branch is evaluated *before* the link branches
(`:155`) and emits only an empty `<div class="separator"></div>`.

Two further separator hazards in `includes/menu.php`:

- **Adjacent separators are silently deleted** (`:347–364`), matched by
  `stristr( $data[4], 'wp-menu-separator' )` — a substring test, so a compound
  class like `mocam-group-header wp-menu-separator` is still caught. Two
  consecutive group headers (an empty group) would lose one.
- **A trailing separator is deleted** (`:367–372`), but via strict equality
  (`'wp-menu-separator' === $menu[$k][4]`), so a compound class survives that one.

### ⚠️ This contradicts SPEC §3.3 step 5

> *"Inject one pseudo menu item per group to act as the accordion header, styled
> as a separator-class item so core renders it without a link target"*

This is **not possible**. A separator-class item renders with no content and
`aria-hidden="true"`. It cannot carry a label, an icon, a count badge, or a
focusable control. Following the spec's own rule — *follow core and report* —
the header carrier is changed. See §5.

## 3. Is the menu title escaped on output? **No.** — *verified by execution*

Two distinct code paths, and they are inconsistent in core:

- Items **with** a submenu use `$title = wptexturize( $item[0] )` (`:146`),
  echoed raw at `:173`/`:175`, and echoed **a second time** into
  `<li class='wp-submenu-head'>` at `:200`.
- Items **without** a submenu echo `{$item[0]}` **directly**, not even
  texturised (`:192`/`:194`).

A title of `Plugins <span class="update-plugins count-3">…</span><b>RAW</b>`
emitted the `<span>` and the `<b>` as live HTML in both paths.

**Consequences for this plugin:**

- This is *by design* — it is how core renders update-count bubbles.
- Group labels are user input (SPEC §8). They must be escaped **by us** before
  they ever reach index `0`, because core will not do it.
- Index `4` (classes) **is** `esc_attr()`-escaped — an attempted
  `cls"><button>INJECTED</button><li class="` came out fully entity-encoded.
  No markup can be smuggled through the class field.

## 4. What the `menu_order` filter can and cannot do — *verified by execution*

Pipeline at `includes/menu.php:291–345`. Core builds `$menu_order` from the
current `$menu`, applies the filter, then `array_flip()`s the result into a
position lookup and `usort()`s with a fallback to `$default_menu_order`.

| Filter returns | Result |
|---|---|
| Every slug, reordered | Applied exactly. |
| **Only some slugs** | **Nothing is lost.** Unlisted items sort *after* all listed ones, in their original relative order. |
| **An empty array** | **Nothing is lost.** Original order preserved. |
| **Duplicate slugs** | No loss, but `array_flip()` collapses duplicates and shifts every subsequent index — ordering silently corrupts. **Must dedupe.** |
| **Phantom slugs** (not in `$menu`) | Ignored. **They do not create menu items.** |
| **A non-array** | `array_flip(): Argument #1 must be of type array` → **fatal `TypeError`** on PHP 8+, white-screening every admin page. |

Two load-bearing conclusions:

1. **`menu_order` cannot delete a menu item.** The SPEC §3.5 "never drop a slug"
   rule is therefore about *ordering fidelity*, not survival — but we still
   return every slug, because a dropped slug gets shunted to the bottom of the
   sidebar, which is a visible bug even though the item still exists.
2. **`menu_order` cannot create a group header**, because phantom slugs are
   ignored. Headers must come from the `$menu` array itself.

## 5. Chosen rendering strategy

**Strategy A (server-side, zero flash), with the header carrier changed** from
the spec's separator item to an **inert `$menu` item injected on the
`add_menu_classes` filter**.

### Why this carrier

`menu-header.php:177` gates the link branch on
`! empty( $item[2] ) && current_user_can( $item[1] )`. Failing *either* half,
with no submenu present, makes every render branch fall through — core emits a
bare `<li>` that still carries our classes and id and is **not** `aria-hidden`.
Verified:

```html
<!-- slug 'secret.php', capability 'do_not_allow', class 'mocam-item' -->
<li class="wp-first-item wp-not-current-submenu mocam-item"></li>
```

We fail the **capability** half (`do_not_allow`, core's canonical never-cap)
rather than the slug half, because each header needs a **unique** slug to be
addressable, and empty slugs would collide.

### Why `add_menu_classes` and not `admin_menu`

`add_menu_classes` (declared `includes/menu.php:230`, filter at `:277`) is
applied at `:387` — the **last thing that touches `$menu`** before
`_wp_menu_output()` runs. Injecting there means our header items:

- are **not** in `$menu` during `admin_menu`, so no other plugin sees or trips
  over them;
- skip the nopriv removal loop (`:175–200`), which would otherwise delete a
  `do_not_allow` item that has no submenu;
- skip the adjacent-separator cleanup (`:347–364`) entirely;
- skip `user_can_access_admin_page()` (`:375`);
- are positioned by direct array splice, so we get exact placement without
  fighting `array_flip()`.

### Resulting pipeline

| Step | Hook | Action |
|---|---|---|
| 1 | `custom_menu_order` | Return `true`. |
| 2 | `menu_order` | Return every real slug, deduped, grouped contiguously. Always an array. |
| 3 | `add_menu_classes` (late) | Append `mocam-item mocam-group-<id>` to index `4` of each real item; splice one inert header item per group at each group boundary. |
| 4 | `admin_body_class` | Emit `mocam-collapsed-<id>` per collapsed group. |
| 5 | CSS | Hide members of collapsed groups. Reserve header row height. |
| 6 | JS | Fill each empty header `<li>` with the `<button>`, toggle, persist. |

### Flash and no-JS behaviour

Ordering, grouping and collapsed state are **all server-rendered**, so there is
no flash of an ungrouped or wrongly-expanded menu. The only thing JS adds is the
header's inner control, whose row height is reserved by CSS, so filling it
causes **no layout shift**.

Because the header button is JS-injected, the stylesheet force-expands every
group until JS marks the document ready. With JS off, the sidebar is a correctly
grouped, fully expanded, fully usable menu with thin unlabelled spacer rows —
**no item is ever hidden or unreachable**, satisfying SPEC §15.

This is Strategy A for everything the spec claims for it (ordering, grouping,
state, zero flash) and Strategy B *only* for the header's inner control, which
§3.2 has now proven cannot be server-rendered by any means.

## 6. Other verified facts

- **There is no hook between menu items.** The only hooks in the render path are
  `adminmenu` (fires *after* every `<li>`, still inside `<ul id="adminmenu">`,
  `menu-header.php:308`) and `in_admin_header` (after the whole nav,
  `admin-header.php:277`). `menu-header.php` is a bare `require` at
  `admin-header.php:268` — not wrappable via a filter. This independently
  confirms that headers must be `$menu` entries.
- **An item whose capability the user lacks is not removed at render time** — it
  emits an empty `<li>` with its classes. Removal happens earlier, in
  `includes/menu.php:175–200`, and only for items with no accessible submenu.
- **Empty slugs are safe** through `includes/menu.php` (`sanitize_title('')`
  is falsy → `continue` at `:76`), but they collide in `menu_order`.

## 7. 6.4 → 7.0.2 delta in these files

Diffed both files across the two versions. **Nothing this plugin depends on
changed.** The index map, the separator branch, the raw-title output and the
whole `custom_menu_order` / `menu_order` / `array_flip` pipeline are identical.

Two cosmetic changes to be aware of:

- **`<div class="wp-menu-arrow">` was removed** from the link markup after 6.4.
  Our CSS must not depend on it existing.
- 6.4 emitted `aria-haspopup="true"`; 7.0.2 emits `data-ariahaspopup`.
- `sort_menu()` was refactored to the spaceship operator (behaviour equivalent).

## 8. Open risk

`array_flip()` fatals on a non-array. We always return an array from
`menu_order`, so we can never be the cause — but a broken plugin filtering after
us can still white-screen the admin. This is the primary justification for the
`?mocam=off` and `MOCAM_DISABLE` escape hatches in SPEC §3.5.
