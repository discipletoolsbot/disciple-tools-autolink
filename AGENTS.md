# AGENTS.md

This file provides guidance to Claude Code (claude.ai/code) and other coding agents when working with code in this repository.

## Project Overview

Disciple.Tools - Autolink is a WordPress **plugin** that requires the Disciple.Tools theme (>= 1.35). It gives downstream leaders a stripped-down, mobile-first UI — reached through a magic link, not wp-admin — for registering, creating/editing groups ("churches"), reporting health metrics, and viewing their group tree / gen map.

It does not register post types or fields of its own. Everything reads and writes the theme's `groups` and `contacts` post types through `DT_Posts`.

### The `2.0` branch — do not merge it

`origin/2.0` is an abandoned from-scratch rewrite onto CodeZone's plugin framework, with
**no shared history** with `master` (`git merge-base` returns nothing, so `cherry-pick`
does not work). We are not adopting that framework; 2.0 is a parts donor only.
See [`docs/2.0-port-candidates.md`](docs/2.0-port-candidates.md) for the feature port
list, verified commit hashes, and the 2.0 → master path mapping.

## Commands

```bash
npm install                      # install JS deps
composer install                 # install PHPCS + WPCS (dev)

npm run dev                      # build dist/ (development)
npm run prod                      # build dist/ (production, minified)
npm run watch                     # mix watch + BrowserSync (needs .env with MIX_URL, see .env.example)

npm run lint                     # vendor/bin/phpcs (whole repo, per phpcs.xml)
./test/test_phpcs.sh             # same, as CI runs it
./vendor/bin/phpcbf              # auto-fix PHPCS violations
./test/test_for_syntax_errors.sh # php -l over every non-vendor PHP file
```

`dist/` is gitignored and is built by the release workflow. After changing anything under `magic-link/` or `admin/*.js`, run `npm run prod` — the PHP enqueues `dist/magic-link.js`, `dist/magic-link.css`, and `dist/admin.js` directly, and `filemtime()` on those files will fatal if they're missing.

### PHPUnit

Tests need the WordPress test library plus a checkout of the DT theme. `test/bootstrap.php` reads `WP_TESTS_DIR`, `WP_CORE_DIR`, `WP_THEME_DIR`, and `WP_PLUGIN_FILE`, registers the theme, and requires the plugin file.

```bash
./test/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host]
vendor/bin/phpunit
vendor/bin/phpunit --filter test_plugin_installed   # single test
```

Test files must be named `unit-test-*.php` under `test/` to be picked up (see `phpunit.xml.dist`).

### Release

`.github/workflows/release.yml` fires on a tag push (`*.*`): it runs `npm run prod`, `composer install --no-dev`, then **copies an explicit list of paths** into the zip. A new top-level directory will be silently missing from releases unless it is added to that `cp -r` line. Version numbers live in three places and must be bumped together: `disciple-tools-autolink.php` header, `package.json`, `version-control.json` (which is also the update-checker manifest).

## Architecture

### Bootstrap

`disciple-tools-autolink.php` hooks `after_setup_theme` (priority 20), bails unless `Disciple_Tools` exists at the required version, then the `Disciple_Tools_Autolink` singleton `require`s every controller, query, chart, and magic-link class. There is no autoloader and no namespacing — classes are `Disciple_Tools_Autolink_*` and files are included in dependency order, so a new class must be added to that constructor.

### Three magic-link apps, one root

All three extend the theme's `DT_Magic_Url_Base` and share the root `autolink`:

| File | type | URL | Purpose |
|---|---|---|---|
| `magic-link/login-app.php` | `page` | `/autolink` | Public login/register. Opens `dt_blank_access` and `dt_allow_non_login_access`. If already logged in → activates and redirects to the app. |
| `magic-link/user-app.php` | `app` | `/autolink/app/{key}` | The authenticated app. `post_type` is `user`. |
| `magic-link/contact-app.php` | `share` | `/autolink/share/{key}` | A leader's shareable link: sets the `dt_autolink_leader_id` cookie, then redirects to `/autolink`. |

The cookie is the coaching-attribution mechanism: `Magic_Functions::add_session_leader()` runs after login/registration, reads it, and sets `coached_by` + `assigned_to` on the new user's contact record if they have no coach yet.

### Routing and controllers

Routing is a hand-rolled `switch` on `$_GET['action']` × `$_SERVER['REQUEST_METHOD']` inside each app's `routes()`, called from the `dt_blank_body` action. Adding a screen means: a `case` in `routes()`, a method on a controller, and a template.

`user-app.php` GET actions: `survey`, `edit-group`, `create-group`, `delete-group`, `genmap`, `tree`, `logout`, `training`, `group`, default → app. POST: `survey`, `create-group`, `edit-group`. The default GET path force-redirects to `?action=survey` until `survey_completed()` is true.

Controllers extend `Disciple_Tools_Autolink_Controller`, which supplies `$this->functions` (the magic-functions singleton), `$this->settings`, and `global_data()` — the shared label/link/contact bundle. The pattern throughout is: build local variables, `extract( $this->global_data() )`, then `include __DIR__ . '/../templates/x.php'`. Controllers **echo**; they don't return markup. Templates in `templates/` compose shared fragments from `templates/parts/` (`app-header.php`, `app-greeting.php`, `church-view-tabs.php`, `app-footer.php`).

### REST

`user-app.php::add_endpoints()` registers exactly two routes — `GET` and `POST` on `autolink/v1/app` — and dispatches on a `action` body/query param to controller methods (`tree`, `onItemDrop`, `update_field`, `parent_group_field`, `groups`). Permission is `DT_Magic_URL::verify_rest_endpoint_permissions_on_post()`, so requests must carry the magic-link `parts`. New endpoints belong in those switches, not as new `register_rest_route` calls.

`Field_Controller::update()` is the generic inline-edit endpoint. It parses the DOM id as `{post_type}_{id}_{field}` and **only** writes fields returned by the `autolink_updatable_group_fields` filter. A new inline-editable field must be added there or the write is rejected.

### Queries and charts

`queries.php` (`dt_autolink_queries()`) holds two large raw `$wpdb` queries — `tree( 'groups' )` and `tree( 'coaching' )` — that flatten group/contact hierarchies with health-metric existence checks in one pass. They bypass `DT_Posts` for performance, so permission filtering is not applied by the query itself.

`charts/groups-tree.php` builds the drag-and-drop tree (jQuery doMenu; reparenting writes straight to `$wpdb->p2p` inside a transaction in `Tree_Controller::process()`). `charts/groups-genmap.php` is loaded only when the separate DT Genmapper plugin is present — genmap routes degrade to a redirect when `DT_Genmapper_Groups_chart` is missing.

### Frontend

Laravel Mix (`webpack.mix.js`) builds three entries into `dist/`: `magic-link/magic-link.js`, `admin/admin.js`, `magic-link/magic-link.css`.

JS components are Lit elements extending `DtBase` (or a concrete component such as `DtNumberField`) from `@disciple.tools/web-components`, registered with `window.customElements.define`, and most override `createRenderRoot()` to return `this` (light DOM) so page CSS applies. Declare reactive properties with `static get properties()` — decorators are **not** available; the `@babel/plugin-proposal-decorators` plugin was removed when the plugin moved to lit 3.

**`@disciple.tools/web-components` is not bundled.** `webpack.mix.js` marks it `external` as the global `DtWebComponents`, which is the copy the theme enqueues (handle `web-components`, `dt-assets/build/components/index.js`). The plugin therefore always runs against whatever version the installed theme ships, and `lit` must stay on the major the theme's library uses (3.x). Two consequences to remember:

- `web-components` and `web-components-css` must stay on the magic-link allow lists in `magic-link/functions.php`, and `web-components` is a declared dependency of `magic_link_scripts` so it loads first.
- Library APIs are not ours to pin. `dt-modal` lost its `openButton` slot in 1.0, which is why `app-church-counts` renders its own trigger button and opens the modal by dispatching an `open` event at it.

Known noise, not a plugin bug: `dt-modal` dispatches events named `open` and `close`, which collide with Foundation's trigger names. The theme's Foundation handler sees them on `document` and logs `'close' is not an available method for this element`. Harmless.

PHP → JS data flows two ways: `wp_localize_script('magic_link_scripts', 'app'|'magic', ...)` for URLs, nonces, and translations; and JSON-in-attributes on components, e.g. `posts='<?php echo esc_attr( wp_json_encode( $churches['posts'] ) ); ?>'`.

`magic-link/magic-link.css` sets the whole DT web-component CSS custom-property theme at `html` scope, then imports `css/__index.css`, which pulls in the `_*.css` partials. Restyling components means overriding `--dt-*` variables there, not reaching into shadow DOM.

### Admin and settings

`admin/admin-menu-and-tabs.php` adds a submenu under `dt_extensions` gated on `manage_dt`, delegating to `Admin_Controller` → `templates/admin/`. `admin/settings.php` is the option layer: `defaults()` is the single source of truth, `setup_options()` seeds missing options on instantiation, and `get_option()` falls back to the default. Add new settings there — including the Vimeo training-video list, which is stored as a JSON option but seeded from localized defaults.

## Conventions and gotchas

- **Text domain is `disciple-tools-autolink`**, not the theme's `disciple_tools`. A handful of mapbox strings deliberately reuse `disciple_tools` to inherit theme translations.
- **"Church" is UI vocabulary for the `groups` post type.** Labels are pulled from `get_post_type_labels( get_post_type_object( 'groups' ) )` so copy follows the site's configured naming — don't hardcode "Church"/"Group" in new strings; concatenate the label.
- **Nonces are per-form and named**: `dt_autolink_group` (`Group_Controller::NONCE`), `dt_autolink_login`, `dt_autolink_register`, `dt_autolink_survey`, `dt_autolink_delete_group`, plus `wp_rest` on `Group_Controller::index()`.
- **Magic-link activation** (`Magic_Functions::activate()`) writes the `{$wpdb->prefix}autolink_app_magic_key` user meta and the `autolink-app` user option (and the `share` pair) on first login. `is_activated()` only checks the `app` pair.
- `user-app.php::user_has_cap()` force-grants `view_any_contacts` for the duration of a magic-link request — leaders are typically low-privilege users.
- Extension points this plugin exposes: filters `dt_autolink_survey`, `autolink_updatable_group_fields`, `autolink_health_fields`; actions `dt_autolink_group_created`, `dt_autolink_group_updated`.
- **Indentation is inconsistent** (tabs in most `controllers/` and `magic-link/functions.php`, 4 spaces in others). `phpcs.xml` excludes the space/tab indent sniffs, so match the file you are editing rather than reformatting it, and let `./test/test_phpcs.sh` be the authority.
