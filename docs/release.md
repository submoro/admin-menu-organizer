# Release process

## Before you start

Two things must be settled before the first submission, and neither can be done
from this machine:

1. **`Contributors:` in `readme.txt`** currently reads `moamenelabd`, which is a
   guess. It needs the WordPress.org **username** — the slug in your profile URL
   at `profiles.wordpress.org/<username>/`. Not the email you sign in with, and
   not a display name. An email there would fail validation and publish the
   address on the plugin page. Getting it wrong is not fatal, but the plugin will
   not appear on your profile and you will not be able to manage it.

2. **Screenshots.** `readme.txt` describes five, and `.wordpress-org/` has the
   icons and banners but not the screenshots, because capturing them needs a
   running WordPress with the plugin active. See "Capturing screenshots" below.
   The directory simply shows no screenshots until they exist; nothing breaks.

## Pre-submission gate

Run all of these. Every one must pass.

```bash
composer install
composer run lint
composer run test:unit
composer run check:readme
php bin/security-audit.php
php bin/build-zip.php
```

The PHP 7.4 syntax check and the PHPUnit integration matrix run in CI, in
`.github/workflows/ci.yml`. Push the branch and confirm all jobs are green before
submitting. Note that **`phpcs` alone does not prove PHP 7.4 compatibility** —
the bundled PHPCompatibility predates PHP 8 and cannot see PHP 8 syntax. The
binding check is the `syntax-php74` job. See `decisions.md` D-005.

Then, on a real WordPress install:

```bash
wp plugin install plugin-check --activate
```

Run Plugin Check against the plugin with both the **Plugin Repo** and **Plugin
Review** check sets. Zero errors is the requirement.

Finally, set `WP_DEBUG`, `WP_DEBUG_DISPLAY` and `SCRIPT_DEBUG` all to `true` and
click through every screen. Zero notices, warnings or deprecations.

## Building the zip

```bash
php bin/build-zip.php
```

Writes `build/admin-menu-organizer.<version>.zip`, taking its
exclusions from `.distignore` so the result matches what `wp dist-archive` would
produce. The script then verifies its own output: it fails if anything that must
never ship has crept in, or if anything the plugin cannot run without is absent.
That check has already earned its place once, catching `phpunit-unit.xml.dist`
leaking into the archive after it was added without a matching `.distignore`
entry.

## Regenerating generated files

```bash
composer run i18n          # POT plus the Arabic .po and .mo
php bin/build-assets.php   # directory icons and banners
```

Both must be re-run before release if any translatable string changed. The POT is
checked against the source by `tests/unit/test-i18n.php`, so a stale POT shows up
as a test failure rather than as a silently untranslated plugin.

## Capturing screenshots

Five, matching the numbered captions already in `readme.txt`. Save as
`.wordpress-org/screenshot-1.png` … `screenshot-5.png`. Use a 1200px-wide
viewport and the default admin colour scheme.

1. The admin sidebar with items grouped into collapsible categories. Use a site
   with enough plugins that the grouping is obviously doing something — the
   WooCommerce, page builder, security and SEO combination is the case worth
   showing.
2. A collapsed group showing an aggregated update count on its header. Let some
   plugin updates accumulate first, or the badge will not be there.
3. The drag-and-drop layout editor under Settings > Menu Organizer.
4. The Groups tab.
5. The Personalise my menu panel.

## First submission

1. Zip the plugin as above.
2. Upload at <https://wordpress.org/plugins/developers/add/>.
3. Wait for the review. Expect questions; answer them in the same thread.

## Publishing to SVN, once approved

You will be given an SVN URL of the form
`https://plugins.svn.wordpress.org/admin-menu-organizer/`.

```bash
svn checkout https://plugins.svn.wordpress.org/admin-menu-organizer/ amorg-svn
cd amorg-svn
```

The repository has three directories, and what goes where matters:

| Directory | Contents |
|---|---|
| `trunk/` | The plugin's current source. |
| `tags/1.0.0/` | An immutable copy of what 1.0.0 shipped. **This is what users download.** |
| `assets/` | Icons, banners and screenshots. **Not** inside `trunk/`. |

```bash
# Unpack the built zip into trunk, replacing what is there.
rm -rf trunk/*
unzip -j -o /path/to/admin-menu-organizer.1.0.0.zip -d /tmp/amorg
cp -R /tmp/amorg/admin-menu-organizer/* trunk/

# Directory assets go in assets/, not trunk/.
cp /path/to/.wordpress-org/*.png assets/

svn add --force trunk assets
svn commit -m "Release 1.0.0"

# Tag it. The directory serves whatever the readme's Stable tag points at.
svn copy trunk tags/1.0.0
svn commit -m "Tag 1.0.0"
```

**`Stable tag:` in `readme.txt` must match the tag directory name.** If it points
at a tag that does not exist, the directory serves `trunk/` instead; if it points
at an old tag, your new release is invisible no matter what you committed. This
is the single most common way a WordPress.org release goes wrong, which is why
`tests/unit/test-version-consistency.php` asserts the stable tag, the plugin
header and `AMORG_VERSION` all agree.

## Subsequent releases

1. Bump the version in **both** the plugin header and `AMORG_VERSION`, and the
   `Stable tag` in `readme.txt`. The unit suite fails if these three disagree.
2. Add a `== Changelog ==` entry. The suite fails if the current version has none.
3. Update `Tested up to:` if you have tested against a newer WordPress.
4. Run the full gate, build the zip, commit to `trunk/`, then tag.

Never edit a published tag. Ship a new version instead.
