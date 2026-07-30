# Claude Code Build Prompt - WordPress Admin Menu Categories Plugin

**How to use this file:** open Claude Code in an empty directory and paste everything below the line into the first message. Keep this file in the repo root as `SPEC.md` so later sessions can re-read it.

---

## ROLE

You are a senior WordPress plugin engineer building a plugin for public release on the WordPress.org plugin directory. You write PHP 7.4+ compatible, WordPress Coding Standards compliant, fully internationalised, fully escaped code. You do not invent WordPress internals - when you are unsure how core renders something, you read the core file in the local WordPress install and confirm before writing code.

---

## 1. PRODUCT BRIEF

### 1.1 Problem

A production WordPress site accumulates 30-60 plugins. Each one injects a top-level item into the `wp-admin` sidebar. The sidebar becomes a flat, unsorted, multi-screen scroll of unrelated items (Yoast next to WP Mail SMTP next to GEO my WP next to Wordfence). There is no grouping, no hierarchy, and no way for an administrator to impose order without editing code.

### 1.2 Solution

A plugin that groups top-level admin menu items into named, collapsible categories inside the native WordPress sidebar. It reorders and visually groups - it never removes, hides or replaces WordPress's own menu system.

### 1.3 Confirmed product decisions (do not re-litigate)

| Decision | Value |
|---|---|
| Assignment method | Automatic rules engine with a full manual drag-and-drop override |
| UI pattern | Collapsible accordion inside the native sidebar, per-user open/closed memory |
| Governance | Site-wide default set by an administrator, plus per-user personal override and reset |
| Distribution | Free, GPLv2 or later, hosted on WordPress.org |
| Monetisation | None in v1. No upsells, no telemetry, no external calls |

### 1.4 Non-goals for v1

- Do not hide or remove menu items (that is a different plugin category and a support burden)
- Do not restyle the WordPress admin beyond the menu
- Do not touch the network admin menu on multisite in v1 (detect and no-op, document it)
- Do not build a block editor sidebar, a settings framework, or an update server

---

## 2. NAMING AND SLUG

- Proposed slug: `admin-menu-categories`
- Proposed display name: `Admin Menu Categories`
- Text domain: identical to the slug
- PHP prefix: `AMORG_` for constants, `amorg_` for functions, `AMORG\` namespace for classes
- CSS/JS/data prefix: `amorg-`
- Option and meta prefix: `amorg_`

Slug constraints you must respect:

- Must not begin with, or lead with, a third-party trademark
- Must not contain "WordPress" or "WP" as a leading brand claim
- Must be unique against the existing directory - before finalising, tell me to check availability at `https://wordpress.org/plugins/<slug>/` and pause for my confirmation

---

## 3. TECHNICAL APPROACH

### 3.1 Core principle

**Reorder and decorate the existing menu. Never rebuild it.**

WordPress builds `$GLOBALS['menu']` and `$GLOBALS['submenu']` during the `admin_menu` action, then renders them in `wp-admin/menu-header.php`. Every capability check, every update-count bubble, every `current` highlight, and every third-party integration depends on that pipeline staying intact.

### 3.2 Required first step - verify core internals

Before writing any rendering code:

1. Read `wp-admin/menu-header.php` in the local WordPress install
2. Read `wp-admin/includes/menu.php`
3. Confirm the exact structure of a `$menu` item array, index by index
4. Confirm exactly how core detects and renders a `wp-menu-separator` item
5. Confirm whether the menu title string is escaped on output or emitted raw
6. Write your findings into `docs/core-notes.md` with the WordPress version number you inspected

Do not proceed on assumption. If your findings contradict anything in this spec, follow your findings and tell me what changed.

### 3.3 Rendering strategy

**Strategy A (preferred) - server-side, zero flash**

1. Hook `admin_menu` at priority `9999` (after every plugin has registered)
2. Read the resolved layout (see section 4)
3. Enable `custom_menu_order` (return `true`) and use the `menu_order` filter to output menu slugs in the resolved grouped order, so all members of a category are contiguous
4. Append a plugin-specific class to index `4` of each `$menu` item: `amorg-item amorg-group-<group-id>`
5. Inject one pseudo menu item per group to act as the accordion header, styled as a separator-class item so core renders it without a link target
6. Print the collapsed state as classes on `<body>` via the `admin_body_class` filter: `amorg-collapsed-<group-id>`
7. CSS hides members of collapsed groups. Because state is server-rendered, there is no flash of an expanded menu on load
8. JavaScript is progressive enhancement only - it toggles classes and persists state

**Strategy B (fallback) - only if step 3.2 proves headers cannot be injected safely**

Keep steps 1-4 and 6 identical. Inject the header rows client-side on `DOMContentLoaded`, and ship inline critical CSS in `admin_head` that reserves the header row height so nothing shifts.

Pick a strategy, document the reason in `docs/core-notes.md`, and state it to me before implementing.

### 3.4 Hooks you will use

| Hook | Purpose |
|---|---|
| `admin_menu` (priority 9999) | Read layout, decorate `$menu` classes, inject group headers |
| `custom_menu_order` | Return `true` to unlock reordering |
| `menu_order` | Return the reordered array of top-level menu slugs |
| `admin_body_class` | Emit per-group collapsed-state classes |
| `admin_enqueue_scripts` | Enqueue CSS and JS, only on admin, only for logged-in users with a menu |
| `admin_menu` (settings page) | Register the settings screen under Settings |
| `rest_api_init` or `wp_ajax_*` | Persist collapsed state and save layouts |
| `plugin_action_links_{plugin}` | Add a Settings link on the Plugins screen |

### 3.5 Hard compatibility rules

- If another plugin already returns `true` from `custom_menu_order` and filters `menu_order`, run at a **later** priority and preserve any slug you do not recognise, in its relative position
- Never drop a slug from the `menu_order` array. Any item not present in the saved layout goes into a permanent, always-visible `Ungrouped` bucket at the bottom
- Never modify index `1` (capability) of any `$menu` item
- If `$GLOBALS['menu']` is empty or malformed, bail silently
- Escape hatches, both must work:
  - Query parameter `?amorg=off` on any admin URL disables the plugin for that request
  - Constant `define( 'AMORG_DISABLE', true );` in `wp-config.php` disables it entirely
- Do not run on the login screen, front end, network admin, or during AJAX/REST/CRON/CLI requests
- Do not run for users who cannot `read`

---

## 4. DATA MODEL

### 4.1 Site-wide layout

Option key: `amorg_layout` (autoloaded)

```json
{
  "schema": 1,
  "groups": [
    {
      "id": "content",
      "label": "Content",
      "icon": "dashicons-admin-post",
      "default_open": true,
      "items": ["edit.php", "upload.php", "edit.php?post_type=page", "edit-comments.php"]
    }
  ],
  "ungrouped_label": "Other"
}
```

### 4.2 Per-user override

User meta key: `amorg_user_layout` - same shape, or absent. Absent means "inherit site-wide".

### 4.3 Per-user collapsed state

User meta key: `amorg_collapsed` - array of group IDs currently collapsed.

### 4.4 Per-role presets

Option key: `amorg_role_layouts` - map of `role_slug => layout`. Resolution order:

1. `amorg_user_layout` (if present and the user has `amorg_personalise_menu`)
2. `amorg_role_layouts[ primary_role ]`
3. `amorg_layout`
4. Auto-detected default

### 4.5 Capabilities

- `manage_options` - edit the site-wide layout and role presets
- New meta capability `amorg_personalise_menu` - mapped by default to any role with `read`. Administrators can switch personalisation off globally with a checkbox

### 4.6 Schema versioning

Store `schema` in every saved payload. On load, run a migration chain. Never assume a shape.

---

## 5. AUTO-DETECTION RULES ENGINE

### 5.1 Default categories

| ID | Label | Typical members |
|---|---|---|
| `dashboard` | Dashboard | `index.php`, activity, site health |
| `content` | Content | Posts, Media, Pages, Comments, custom post types |
| `commerce` | Commerce | WooCommerce, Products, Orders, Payments, Analytics, marketplace and vendor plugins |
| `design` | Design & Layout | Appearance, page builders, theme option screens, sliders, popups, sidebars |
| `seo` | SEO & Marketing | SEO plugins, sitemaps, schema, redirects, email marketing |
| `security` | Security & Backup | Firewalls, malware scanners, login hardening, backup and migration |
| `performance` | Performance | Caching, image optimisation, CDN, database cleanup |
| `users` | Users & Access | Users, roles, memberships, login customisation |
| `integrations` | Integrations | Forms, chat, CRM, mail delivery, maps, translation |
| `tools` | Tools & System | Tools, Settings, Plugins, updates, file managers, logs |
| `ungrouped` | Other | Everything unmatched. Always present, always visible |

### 5.2 Matching order

For each top-level menu item, evaluate in this order and stop at the first hit:

1. **Explicit override** - a manual assignment saved in the layout
2. **Exact slug map** - a bundled table of known slugs, e.g. `woocommerce => commerce`, `wpseo_dashboard => seo`, `Wordfence => security`, `elementor => design`, `wp-mail-smtp => integrations`, `sitepress-multilingual-cms => integrations`
3. **Core slug map** - hardcoded for `index.php`, `edit.php`, `upload.php`, `edit-comments.php`, `themes.php`, `plugins.php`, `users.php`, `tools.php`, `options-general.php`, `edit.php?post_type=*`
4. **Keyword heuristics** - case-insensitive keyword scoring against the menu title and slug, with a weighted keyword table per category, requiring a minimum confidence score
5. **Fallback** - `ungrouped`

### 5.3 Rules data

- Ship the known-slug table as a PHP array in `includes/data/known-slugs.php`, not JSON, to avoid a filesystem read on every request
- Never fetch rules from a remote server. The plugin must make zero outbound HTTP requests
- Expose filters so other developers can extend without forking:
  - `amorg_known_slugs` - array of slug => group id
  - `amorg_category_definitions` - array of group definitions
  - `amorg_resolve_group` - `( string $group_id, array $menu_item )`
  - `amorg_resolved_layout` - final layout array before rendering

### 5.4 Detection must be non-destructive

Auto-detection runs once, on activation, to seed the initial layout. It also runs for **newly seen slugs only** on subsequent loads, appending them to their detected group. It must never re-sort or overwrite an item a human has moved.

---

## 6. FRONT-END BEHAVIOUR (the sidebar)

### 6.1 Group header markup

- Rendered as a button, not a link: `<button type="button" class="amorg-group-toggle" aria-expanded="true" aria-controls="amorg-group-content">`
- Contains a Dashicon, the group label, and a chevron
- Screen-reader text conveys expand/collapse state
- Never focusable when the group has zero visible members - hide the whole group instead

### 6.2 Interaction

- Click or `Enter`/`Space` toggles the group
- CSS transition on height, disabled under `@media (prefers-reduced-motion: reduce)`
- State persists per user, saved via a debounced (400ms) authenticated REST call to `amorg/v1/state`, nonce protected
- Optimistic UI - toggle instantly, save in the background, never block
- If the save fails, keep the visual state and retry once. Do not show an error toast for a cosmetic preference

### 6.3 Auto-expand rules

- The group containing the currently active menu item is always force-expanded on page load, regardless of saved state
- Collapsed groups whose members have an update-count bubble show an aggregated count badge on the header

### 6.4 States you must handle

- Folded sidebar (`body.folded`) - headers collapse to icon only, groups expand on hover flyout using core's existing flyout CSS patterns
- Auto-fold and responsive breakpoints (`body.auto-fold`, under 960px, under 782px)
- Mobile menu open state (`body.mobile-open`)
- Admin colour schemes - all eight core schemes must look correct. Use core CSS custom properties and colour-scheme classes, never hardcoded hex for backgrounds
- RTL - use CSS logical properties (`margin-inline-start`, `padding-inline-end`, `inset-inline-start`). Ship `rtl.css` generated by `rtlcss` only if logical properties prove insufficient. **This is a hard requirement** - the target site runs WPML with Arabic
- High contrast mode and `forced-colors`
- Keyboard-only navigation, full tab order, visible focus rings

---

## 7. SETTINGS SCREEN

Location: `Settings > Menu Categories` (`options-general.php?page=amorg`)

### 7.1 Tabs

1. **Layout** - drag-and-drop editor
2. **Groups** - create, rename, reorder, set icon and default state, delete (members fall to Ungrouped)
3. **Roles** - assign a saved layout per role, toggle personalisation on/off
4. **Advanced** - reset to auto-detected default, export layout as JSON, import layout, safe-mode instructions

### 7.2 Drag-and-drop editor

- Use **`jquery-ui-sortable`**, which ships with WordPress core. Do not bundle SortableJS, React, or any third-party library. This is a WordPress.org directory requirement
- Two-column layout: group columns on the left, an Ungrouped pool on the right
- Every drag operation must have a keyboard equivalent - a "Move to group" select on each item. Drag-only is an accessibility failure and a review risk
- Live preview panel showing the resulting sidebar
- Explicit Save button. No auto-save on the settings screen
- Unsaved-changes warning via `beforeunload`

### 7.3 Personal layout

A "Personalise my menu" panel visible to any user with `amorg_personalise_menu`, offering the same editor scoped to their own user meta, plus a prominent "Reset to site default" button.

---

## 8. SECURITY REQUIREMENTS

Non-negotiable. Every one of these will be checked in review.

- `defined( 'ABSPATH' ) || exit;` at the top of every PHP file
- Every form and AJAX/REST endpoint protected by `wp_verify_nonce` / `check_ajax_referer` / a REST `permission_callback` that calls `current_user_can()`
- `permission_callback` must never be `__return_true`
- Every input: `wp_unslash()` then a type-specific sanitiser (`sanitize_key`, `sanitize_text_field`, `absint`). Layout arrays get a recursive whitelist sanitiser - unknown keys are dropped, not passed through
- Every output escaped at the point of output: `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`. No exceptions, including inside `printf`
- Group labels are user input. Store raw-sanitised, escape on output, and never allow HTML
- Menu slugs used as array keys must be validated against the live `$menu` global before use - never trust a stored slug
- No `eval`, no `create_function`, no variable functions, no `extract`
- No direct database queries. Options API only
- No file writes. No `file_get_contents` on remote URLs. No `curl`. Zero outbound requests
- Capability check before every state-changing operation, checked server-side, never inferred from the UI

---

## 9. INTERNATIONALISATION

- Every user-facing string wrapped in `__()`, `_e()`, `esc_html__()`, `esc_attr__()`, `_n()`, or `_x()` with the text domain `admin-menu-categories`
- Text domain must be a literal string in every call - never a variable or constant
- No string concatenation to build sentences. Use `printf` with numbered placeholders (`%1$s`, `%2$s`)
- Generate `languages/admin-menu-categories.pot` with WP-CLI: `wp i18n make-pot . languages/admin-menu-categories.pot`
- JavaScript strings via `wp_set_script_translations()` and `wp.i18n`
- Ship an Arabic translation (`ar.po`/`ar.mo`) as a starter and confirm RTL rendering against it

---

## 10. WORDPRESS.ORG DIRECTORY COMPLIANCE

### 10.1 Licence

- `GPLv2 or later` in the plugin header and in `readme.txt`
- Include `LICENSE` (full GPLv2 text)
- Every bundled asset (icons, screenshots, fonts) must be GPL-compatible, with sources documented in `readme.txt`

### 10.2 Plugin header

```php
/**
 * Plugin Name:       Admin Menu Categories
 * Plugin URI:        https://example.com/menu-organizer
 * Description:       Groups WordPress admin menu items into named, collapsible categories. Auto-sorts known plugins and lets you rearrange everything by drag and drop.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            <your name>
 * Author URI:        https://example.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       admin-menu-categories
 * Domain Path:       /languages
 */
```

### 10.3 readme.txt

Must follow the official readme standard exactly and validate against the WordPress.org readme validator:

- `=== Plugin Name ===`
- `Contributors:` - WordPress.org usernames only
- `Tags:` - **maximum 5**, no competitor names, no keyword stuffing
- `Requires at least:`, `Tested up to:`, `Requires PHP:`, `Stable tag:`
- `License:` and `License URI:`
- Short description under 150 characters
- Sections: Description, Installation, Frequently Asked Questions, Screenshots, Changelog, Upgrade Notice
- An explicit statement that the plugin makes no external requests and collects no data
- No affiliate links, no promotional spam

### 10.4 Assets

Directory `.wordpress-org/` (not shipped in the plugin zip):

- `icon-128x128.png`, `icon-256x256.png`
- `banner-772x250.png`, `banner-1544x500.png`
- `screenshot-1.png` through `screenshot-5.png`, matching the numbered captions in `readme.txt`

### 10.5 Pre-submission gate

The plugin must pass, with zero errors:

- The official **Plugin Check (PCP)** plugin, both the Plugin Repo and Plugin Review check sets
- `phpcs` against `WordPress`, `WordPress-Extra`, `WordPress-Docs` and `PHPCompatibilityWP` (testVersion `7.4-`)
- `WP_DEBUG`, `WP_DEBUG_DISPLAY` and `SCRIPT_DEBUG` all `true` with no notices, warnings or deprecations

### 10.6 Other directory rules

- No bundled copies of jQuery or any library core already provides
- No code obfuscation or minification without shipping the source
- No update mechanism outside WordPress.org
- No calling home, no analytics, no opt-out tracking. If you ever add telemetry it must be opt-in only
- A complete, working plugin at first submission - no placeholders, no "coming soon" screens
- Version number must increment on every release

---

## 11. FILE STRUCTURE

```
admin-menu-categories/
├── admin-menu-categories.php   # bootstrap only, no logic
├── uninstall.php                               # delete options and user meta
├── readme.txt
├── LICENSE
├── includes/
│   ├── class-plugin.php                        # singleton, hook registration
│   ├── class-activator.php
│   ├── class-menu-reader.php                   # safe reads of $menu / $submenu
│   ├── class-menu-renderer.php                 # class injection, header injection
│   ├── class-menu-order.php                    # custom_menu_order / menu_order
│   ├── class-layout-repository.php             # get/save/resolve layouts
│   ├── class-layout-sanitizer.php              # recursive whitelist sanitiser
│   ├── class-detector.php                      # rules engine
│   ├── class-migrations.php
│   ├── class-capabilities.php
│   ├── class-rest-controller.php
│   ├── admin/
│   │   ├── class-settings-page.php
│   │   └── views/
│   └── data/
│       ├── known-slugs.php
│       └── keyword-map.php
├── assets/
│   ├── css/admin-menu.css
│   ├── css/settings.css
│   ├── js/admin-menu.js
│   └── js/settings.js
├── languages/
├── docs/
│   ├── core-notes.md
│   └── decisions.md
├── tests/
│   ├── bootstrap.php
│   ├── test-detector.php
│   ├── test-layout-sanitizer.php
│   ├── test-menu-order.php
│   └── test-migrations.php
├── .wordpress-org/
├── composer.json
├── phpcs.xml.dist
├── phpunit.xml.dist
├── .wp-env.json
└── .distignore
```

---

## 12. TESTING

### 12.1 Automated

- PHPUnit via `wp-env` and the WordPress test suite
- Minimum coverage:
  - Detector assigns known slugs correctly and falls back to `ungrouped`
  - Sanitiser strips unknown keys, rejects non-existent slugs, survives malformed input
  - `menu_order` output contains every input slug exactly once, no additions, no losses
  - Migration from schema 0 to 1 preserves user assignments
  - Capability gates reject a subscriber on every write endpoint

### 12.2 Manual matrix

Test and record results in `docs/decisions.md`:

- WordPress 6.4 and current stable
- PHP 7.4, 8.1, 8.3
- All eight admin colour schemes
- LTR and RTL (Arabic), with WPML active
- WooCommerce + a page builder + a security plugin + an SEO plugin installed together (this is the real-world case)
- Alongside another menu-order plugin (Admin Menu Editor) - confirm no fatal, no lost items
- Folded sidebar, mobile, 782px and 960px breakpoints
- Subscriber, Editor, Shop Manager, Administrator
- Multisite - confirm clean no-op in network admin
- Keyboard-only navigation, and a screen reader pass (NVDA or VoiceOver)

---

## 13. BUILD ORDER

Work in these phases. Stop at the end of each and report before continuing.

1. **Recon** - read core files per section 3.2, write `docs/core-notes.md`, state your chosen rendering strategy and why
2. **Skeleton** - bootstrap, autoloader, activation/deactivation, uninstall, phpcs and phpunit config, everything passing with zero output
3. **Read layer** - `class-menu-reader.php` plus a debug screen dumping the resolved `$menu` array. Confirm against a real menu
4. **Detector** - rules engine plus unit tests. No UI yet
5. **Reorder** - `custom_menu_order` and `menu_order`, with the never-lose-a-slug guarantee proven by tests
6. **Render** - class injection, group headers, CSS accordion, server-rendered state, zero flash
7. **JS** - toggling, persistence, REST endpoint, accessibility
8. **Settings screen** - layout editor, groups, roles, advanced
9. **Per-user layer** - personal override, capability, reset
10. **i18n + RTL + colour schemes** - POT generation, Arabic starter, full visual pass
11. **Hardening** - full security audit against section 8, line by line
12. **Compliance** - readme.txt, assets, Plugin Check, phpcs, debug pass
13. **Release prep** - `.distignore`, build script producing the submission zip, SVN instructions in `docs/`

---

## 14. WORKING RULES

- After each phase, output a short summary: what you built, what you verified, what is still open
- Never mark a phase complete with a failing test or a phpcs error
- When a spec instruction conflicts with what you find in WordPress core, follow core and tell me
- Do not add features not in this spec. Log ideas in `docs/decisions.md` under "Deferred"
- Commit per phase with a conventional-commit message
- Ask me before choosing anything that affects the public slug, the plugin name, or the licence

---

## 15. DEFINITION OF DONE

- [ ] Plugin Check passes with zero errors on Plugin Repo and Plugin Review check sets
- [ ] `phpcs` clean against WordPress-Extra, WordPress-Docs, PHPCompatibilityWP 7.4-
- [ ] All PHPUnit tests pass on PHP 7.4, 8.1, 8.3
- [ ] Zero PHP notices, warnings or deprecations with `WP_DEBUG` on
- [ ] Zero outbound HTTP requests, verified by logging `pre_http_request`
- [ ] No menu item is ever lost, hidden, or made inaccessible in any configuration
- [ ] Full keyboard operation with no mouse
- [ ] Correct rendering in RTL with WPML active
- [ ] Correct rendering in all eight admin colour schemes
- [ ] Deactivating the plugin restores the exact stock menu with no residue
- [ ] Uninstalling removes every option and every user meta key
- [ ] `readme.txt` validates, with 5 tags or fewer
- [ ] Submission zip builds cleanly from `.distignore`

---

## AMENDMENTS

Decisions taken after the spec was written live in [`docs/decisions.md`](docs/decisions.md).
Where a finding in [`docs/core-notes.md`](docs/core-notes.md) contradicts this document,
**core-notes.md wins**, per §3.2 and §14. As of Phase 1 that applies to §3.3 step 5,
§3.4's header-injection hook, and the framing of §3.5's never-drop-a-slug rule.
